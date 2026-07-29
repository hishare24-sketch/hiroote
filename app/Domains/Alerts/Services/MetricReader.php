<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Domains\Alerts\DTOs\MetricReading;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Analytics\Models\TokenUsageRecord;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Projects\Models\Project;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * يحسب قيمة أي مؤشر من بيانات المشروع الفعلية.
 *
 * لا شيء هنا يقرأ حالة محفوظة: كل قراءة استعلامٌ على الجداول لحظة السؤال، فما
 * تعرضه شاشة التنبيهات هو ما سيقيسه المُقيِّم بعد قليل — لا رقمان لحقيقة واحدة.
 */
class MetricReader
{
    private const LOW_CONFIDENCE = 60;

    /** المشروع يُمرَّر ولا يُحمَّل من القاعدة: صفٌّ واحد لكل قاعدة يعني استعلامًا زائدًا لكل صف. */
    public function forRule(AlertRule $rule, Project $project): MetricReading
    {
        return $this->read(
            $project,
            $rule->metric,
            $rule->window_minutes,
            $rule->section_ids ?? [],
            $rule->provider_ids ?? [],
        );
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     */
    public function read(
        Project $project,
        AlertMetric $metric,
        int $windowMinutes,
        array $sectionIds = [],
        array $providerIds = [],
    ): MetricReading {
        $since = $metric->isWindowed()
            ? Carbon::now()->subMinutes(max($windowMinutes, 1))
            : null;

        return match ($metric) {
            AlertMetric::EscalationRate => $this->outcomeShare(
                $project, $since, $sectionIds, $providerIds, [ConversationOutcome::Human],
            ),
            AlertMetric::AbandonRate => $this->outcomeShare(
                $project, $since, $sectionIds, $providerIds, [ConversationOutcome::Abandoned],
            ),
            AlertMetric::UnresolvedRate => $this->outcomeShare(
                $project, $since, $sectionIds, $providerIds,
                [ConversationOutcome::Human, ConversationOutcome::Ticket, ConversationOutcome::Abandoned],
            ),
            AlertMetric::LowConfidenceRate => $this->lowConfidenceShare($project, $since, $sectionIds, $providerIds),
            AlertMetric::AvgResponseMs => $this->averageResponse($project, $since, $sectionIds, $providerIds),
            AlertMetric::ConversationVolume => $this->volume($project, $since, $sectionIds, $providerIds),
            AlertMetric::AvgRating => $this->averageRating($project, $since, $sectionIds, $providerIds),
            AlertMetric::CostTotal => $this->cost($project, $since, $sectionIds, $providerIds),
            AlertMetric::TokensTotal => $this->tokens($project, $since, $sectionIds, $providerIds),
            AlertMetric::ProviderErrorRate => $this->providerPeak($providerIds, 'error_rate'),
            AlertMetric::ProviderBalance => $this->providerFloor($providerIds),
            AlertMetric::OpenKnowledgeNotes => $this->openNotes($project, $sectionIds),
        };
    }

    /**
     * أسماء الأقسام المطابقة للمعرّفات.
     *
     * المحادثات تحمل اسم القسم نصًّا لا معرّفًا — عقدُ التكامل مع المشاريع
     * الخارجية يرسل اسمًا. الترجمة تتم هنا مرة واحدة.
     *
     * @param  list<int>  $sectionIds
     * @return list<string>
     */
    private function sectionNames(Project $project, array $sectionIds): array
    {
        if ($sectionIds === []) {
            return [];
        }

        $names = [];

        foreach (ProjectSection::query()
            ->forProject($project)
            ->whereIn('id', $sectionIds)
            ->pluck('name') as $name) {
            $names[] = (string) $name;
        }

        return $names;
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     * @return Builder<Conversation>
     */
    private function conversations(
        Project $project,
        ?Carbon $since,
        array $sectionIds,
        array $providerIds,
    ): Builder {
        $query = Conversation::query()->forProject($project);

        if ($since !== null) {
            $query->where('started_at', '>=', $since);
        }

        $names = $this->sectionNames($project, $sectionIds);

        if ($names !== []) {
            $query->whereIn('section', $names);
        }

        if ($providerIds !== []) {
            $query->whereIn('provider_id', $providerIds);
        }

        return $query;
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     * @param  list<ConversationOutcome>  $outcomes
     */
    private function outcomeShare(
        Project $project,
        ?Carbon $since,
        array $sectionIds,
        array $providerIds,
        array $outcomes,
    ): MetricReading {
        // الجارية مستثناة من المقام: محادثةٌ لم تنتهِ بعدُ لا تُحتسب نجاحًا ولا فشلًا.
        $base = $this->conversations($project, $since, $sectionIds, $providerIds)
            ->where('outcome', '!=', ConversationOutcome::Open->value);

        $total = (clone $base)->count();

        if ($total === 0) {
            return MetricReading::unavailable('لا محادثات منتهية في الفترة');
        }

        $matching = (clone $base)
            ->whereIn('outcome', array_map(static fn (ConversationOutcome $o): string => $o->value, $outcomes))
            ->count();

        return new MetricReading(
            round($matching / $total * 100, 2),
            $total,
            "{$matching} من {$total} محادثة منتهية",
            ['matching' => $matching, 'total' => $total],
        );
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     */
    private function lowConfidenceShare(
        Project $project,
        ?Carbon $since,
        array $sectionIds,
        array $providerIds,
    ): MetricReading {
        $base = $this->conversations($project, $since, $sectionIds, $providerIds)
            ->whereNotNull('confidence');

        $total = (clone $base)->count();

        if ($total === 0) {
            return MetricReading::unavailable('لا محادثات بثقة مسجَّلة في الفترة');
        }

        $low = (clone $base)->where('confidence', '<', self::LOW_CONFIDENCE)->count();

        return new MetricReading(
            round($low / $total * 100, 2),
            $total,
            "{$low} من {$total} محادثة",
            ['low' => $low, 'total' => $total, 'threshold' => self::LOW_CONFIDENCE],
        );
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     */
    private function averageResponse(
        Project $project,
        ?Carbon $since,
        array $sectionIds,
        array $providerIds,
    ): MetricReading {
        $base = $this->conversations($project, $since, $sectionIds, $providerIds)
            ->whereNotNull('avg_response_ms');

        $total = (clone $base)->count();

        if ($total === 0) {
            return MetricReading::unavailable('لا أزمنة رد مسجَّلة في الفترة');
        }

        return new MetricReading(
            round((float) (clone $base)->avg('avg_response_ms'), 2),
            $total,
            "متوسط {$total} محادثة",
        );
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     */
    private function volume(
        Project $project,
        ?Carbon $since,
        array $sectionIds,
        array $providerIds,
    ): MetricReading {
        // الصفر هنا قياسٌ صحيح لا غياب قياس: «لا محادثات» هو بالضبط ما تراقبه
        // قاعدة الهبوط.
        $count = $this->conversations($project, $since, $sectionIds, $providerIds)->count();

        return new MetricReading((float) $count, $count, 'محادثات الفترة');
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     */
    private function averageRating(
        Project $project,
        ?Carbon $since,
        array $sectionIds,
        array $providerIds,
    ): MetricReading {
        $base = $this->conversations($project, $since, $sectionIds, $providerIds)
            ->whereNotNull('rating');

        $total = (clone $base)->count();

        if ($total === 0) {
            return MetricReading::unavailable('لا تقييمات في الفترة');
        }

        return new MetricReading(
            round((float) (clone $base)->avg('rating'), 2),
            $total,
            "متوسط {$total} تقييم",
        );
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     */
    private function cost(
        Project $project,
        ?Carbon $since,
        array $sectionIds,
        array $providerIds,
    ): MetricReading {
        $query = CostUsageRecord::query()->forProject($project);

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        $names = $this->sectionNames($project, $sectionIds);

        if ($names !== []) {
            $query->whereIn('section', $names);
        }

        if ($providerIds !== []) {
            $query->whereIn('provider_id', $providerIds);
        }

        $count = (clone $query)->count();

        return new MetricReading(
            round((float) (clone $query)->sum('amount'), 2),
            $count,
            "{$count} قيد تكلفة",
        );
    }

    /**
     * @param  list<int>  $sectionIds
     * @param  list<int>  $providerIds
     */
    private function tokens(
        Project $project,
        ?Carbon $since,
        array $sectionIds,
        array $providerIds,
    ): MetricReading {
        $query = TokenUsageRecord::query()->forProject($project);

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        $names = $this->sectionNames($project, $sectionIds);

        if ($names !== []) {
            $query->whereIn('section', $names);
        }

        if ($providerIds !== []) {
            $query->whereIn('provider_id', $providerIds);
        }

        $count = (clone $query)->count();
        $total = (clone $query)->selectRaw(
            'COALESCE(SUM(input_tokens + output_tokens + knowledge_tokens + attachment_tokens + tool_tokens), 0) AS total',
        )->value('total');

        return new MetricReading((float) $total, $count, "{$count} قيد استهلاك");
    }

    /** @param list<int> $providerIds */
    private function providerPeak(array $providerIds, string $column): MetricReading
    {
        $query = AiProvider::query()->where('is_enabled', true);

        if ($providerIds !== []) {
            $query->whereIn('id', $providerIds);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            return MetricReading::unavailable('لا مزودين مفعَّلين');
        }

        return new MetricReading(
            round((float) (clone $query)->max($column), 2),
            $count,
            "أعلى قيمة بين {$count} مزودًا",
        );
    }

    /** @param list<int> $providerIds */
    private function providerFloor(array $providerIds): MetricReading
    {
        $query = AiProvider::query()->where('is_enabled', true);

        if ($providerIds !== []) {
            $query->whereIn('id', $providerIds);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            return MetricReading::unavailable('لا مزودين مفعَّلين');
        }

        return new MetricReading(
            round((float) (clone $query)->min('balance'), 2),
            $count,
            "أدنى رصيد بين {$count} مزودًا",
        );
    }

    /** @param list<int> $sectionIds */
    private function openNotes(Project $project, array $sectionIds): MetricReading
    {
        $query = KnowledgeFeedback::query()
            ->forProject($project)
            ->whereNull('resolved_at');

        if ($sectionIds !== []) {
            $query->whereIn('section_id', $sectionIds);
        }

        $count = $query->count();

        return new MetricReading((float) $count, $count, 'ملاحظة مفتوحة');
    }
}
