<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\DTOs\BridgeResult;

/**
 * يحوّل ردود موازين إلى عقد الشاشة، ويشتقّ ما لا يحسبه موازين لنفسه.
 *
 * كل رقم يحمل مصدره: **مقروء** جاء كما هو من موازين، و**محسوب** اشتُقّ هنا من
 * أرقامه. الخلط بينهما يجعل تقديرًا يُقرأ كقياس، وهو أخطر ما في لوحةٍ تعرض
 * بيانات نظام آخر.
 */
class MawazinPresenter
{
    /** ترتيب الباقات كما هي في موازين — لا أبجديًّا. */
    private const PLAN_ORDER = ['free', 'bronze', 'silver', 'gold', 'platinum', 'diamond'];

    private const PLAN_LABELS = [
        'free' => 'مجانية',
        'bronze' => 'برونزية',
        'silver' => 'فضية',
        'gold' => 'ذهبية',
        'platinum' => 'بلاتينية',
        'diamond' => 'ماسية',
    ];

    /**
     * @param  array<string, BridgeResult>  $snapshot
     * @return array<string, mixed>
     */
    public function present(array $snapshot): array
    {
        $settings = $snapshot['settings'] ?? null;
        $analytics = $snapshot['analytics'] ?? null;

        return [
            'transport' => array_map(
                fn (BridgeResult $result): array => $result->payload(),
                $snapshot,
            ),
            'assistant' => $this->assistant($settings),
            'plans' => $this->plans($settings),
            'analytics' => $this->analytics($analytics),
            'health' => ($snapshot['health'] ?? null)?->data,
            'quotas' => $this->quotas($snapshot['quotas'] ?? null),
            'derived' => $this->derived($settings, $analytics),
        ];
    }

    /**
     * إعداد المساعد كما هو في موازين — عرضًا لا تحكّمًا.
     *
     * @return array<string, mixed>|null
     */
    private function assistant(?BridgeResult $result): ?array
    {
        $data = $result?->data;

        if ($data === null) {
            return null;
        }

        return [
            'provider' => $this->text($data, 'provider'),
            'model' => $this->text($data, 'model'),
            'assistant_provider' => $this->text($data, 'assistantProvider'),
            'assistant_model' => $this->text($data, 'assistantModel'),
            'embedding_provider' => $this->text($data, 'embeddingProvider'),
            'embedding_model' => $this->text($data, 'embeddingModel'),
            'embeddings_enabled' => $this->flag($data, 'embeddingsEnabled'),
            'system_prompt' => $this->text($data, 'systemPrompt'),
            'assistant_level' => $this->number($data, 'assistantLevel'),
            'allow_user_level_override' => $this->flag($data, 'allowUserLevelOverride'),
            'level_tokens' => $this->map($data, 'levelTokens'),
            'tools_enabled' => $this->flag($data, 'assistantToolsEnabled'),
            'write_tools_enabled' => $this->flag($data, 'assistantWriteToolsEnabled'),
            'doc_max_reads' => $this->number($data, 'docMaxReads'),
            'async_queue_enabled' => $this->flag($data, 'asyncQueueEnabled'),
            'async_queue_min_pages' => $this->number($data, 'asyncQueueMinPages'),
            'reading_pipelines' => is_array($data['readingPipelines'] ?? null)
                ? count($data['readingPipelines'])
                : null,
        ];
    }

    /**
     * حصص الباقات — ستّ باقات بأربعة سقوف.
     *
     * @return list<array<string, mixed>>
     */
    private function plans(?BridgeResult $result): array
    {
        $quotas = $result?->data['planQuotas'] ?? null;

        if (! is_array($quotas)) {
            return [];
        }

        $rows = [];

        foreach (self::PLAN_ORDER as $plan) {
            $row = $quotas[$plan] ?? null;

            if (! is_array($row)) {
                continue;
            }

            $daily = $this->intOrNull($row['dailyTokens'] ?? null);

            $rows[] = [
                'plan' => $plan,
                'label' => self::PLAN_LABELS[$plan],
                'max_per_request' => $this->intOrNull($row['maxTokensPerRequest'] ?? null),
                'daily' => $daily,
                'weekly' => $this->intOrNull($row['weeklyTokens'] ?? null),
                'monthly' => $this->intOrNull($row['monthlyTokens'] ?? null),
                // صفرٌ في موازين يعني «بلا سقف» لا «ممنوع» — والفرق بينهما كامل.
                'unlimited' => $daily === 0,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analytics(?BridgeResult $result): ?array
    {
        $data = $result?->data;

        if ($data === null) {
            return null;
        }

        return [
            'since' => $this->text($data, 'since'),
            'total_cost_usd' => $this->floatOrNull($data['totalCostUsd'] ?? null),
            'by_kind' => $this->rows($data, 'byKind'),
            'daily' => $this->rows($data, 'daily'),
            'by_provider_model' => $this->rows($data, 'byProviderModel'),
            'top_users' => array_slice($this->rows($data, 'topUsers'), 0, 10),
            'cache' => is_array($data['cache'] ?? null) ? $data['cache'] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quotas(?BridgeResult $result): array
    {
        $data = $result?->data;

        return is_array($data) ? array_values(array_filter($data, is_array(...))) : [];
    }

    /**
     * ما لا يحسبه موازين لنفسه — مشتقّ من أرقامه، ومعلَّم بذلك.
     *
     * @return list<array<string, mixed>>
     */
    private function derived(?BridgeResult $settings, ?BridgeResult $analytics): array
    {
        $rows = [];
        $stats = $analytics?->data;
        $config = $settings?->data;

        if (is_array($stats)) {
            $daily = $this->rows($stats, 'daily');
            $tokens = array_sum(array_map(
                fn (array $row): int => $this->intOrNull($row['tokens'] ?? null) ?? 0,
                $daily,
            ));
            $days = count($daily);

            if ($days > 0) {
                $rows[] = [
                    'label' => 'متوسط الرموز اليومي',
                    'value' => (int) round($tokens / $days),
                    'unit' => 'count',
                    'note' => "مقسومة على {$days} يومًا فيها حركة",
                ];
            }

            $cost = $this->floatOrNull($stats['totalCostUsd'] ?? null);

            if ($cost !== null && $tokens > 0) {
                $rows[] = [
                    'label' => 'كلفة المليون رمز',
                    'value' => round($cost / $tokens * 1_000_000, 2),
                    'unit' => 'usd',
                    'note' => 'الكلفة الكلية ÷ الرموز الكلية',
                ];
            }

            $cache = $stats['cache'] ?? null;

            if (is_array($cache) && isset($cache['hitRate'])) {
                $rows[] = [
                    'label' => 'إصابة الكاش',
                    'value' => round((float) $cache['hitRate'] * 100, 1),
                    'unit' => 'percent',
                    'note' => 'كل نقطة ارتفاع فيها تخفض الكلفة مباشرة',
                ];
            }

            $top = $this->rows($stats, 'topUsers');

            if ($top !== [] && $tokens > 0) {
                $head = array_sum(array_map(
                    fn (array $row): int => $this->intOrNull($row['tokens'] ?? null) ?? 0,
                    array_slice($top, 0, 5),
                ));

                $rows[] = [
                    'label' => 'حصة أنشط خمسة',
                    'value' => round($head / $tokens * 100, 1),
                    'unit' => 'percent',
                    'note' => 'ارتفاعها يعني أن السقوف تُضبط لقلّة لا لعامّة',
                ];
            }
        }

        if (is_array($config)) {
            $quotas = $config['planQuotas'] ?? null;

            if (is_array($quotas)) {
                $unlimited = 0;

                foreach ($quotas as $row) {
                    if (is_array($row) && ($this->intOrNull($row['dailyTokens'] ?? null) === 0)) {
                        $unlimited++;
                    }
                }

                $rows[] = [
                    'label' => 'باقات بلا سقف يومي',
                    'value' => $unlimited,
                    'unit' => 'count',
                    'note' => 'الصفر في موازين يعني بلا حد لا منعًا',
                ];
            }
        }

        return $rows;
    }

    /** @param array<array-key, mixed> $data */
    private function text(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** @param array<array-key, mixed> $data */
    private function flag(array $data, string $key): ?bool
    {
        $value = $data[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /** @param array<array-key, mixed> $data */
    private function number(array $data, string $key): ?int
    {
        return $this->intOrNull($data[$key] ?? null);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, int>
     */
    private function map(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $k => $v) {
            $number = $this->intOrNull($v);

            if ($number !== null) {
                $out[(string) $k] = $number;
            }
        }

        return $out;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function rows(array $data, string $key): array
    {
        $value = $data[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        $rows = [];

        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
