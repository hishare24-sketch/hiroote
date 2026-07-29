<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\Services;

use App\Domains\Assistants\Models\AssistantLevelSetting;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Orchestrator\DTOs\AssistantRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * يبني تعليمات المساعد من معرفة المشروع — خادميًّا لا من العميل.
 *
 * **المنشور وحده يعبر**: المسودة عملٌ لم يعتمده أحد، وإجابةٌ بها تبلغ مستخدمًا
 * حقيقيًّا باسم المنصّة.
 *
 * وحين لا توجد معرفة للقسم يُقال ذلك في التعليمات صراحةً بدل الصمت: مساعدٌ لا
 * يعرف أنه بلا مرجع يخترع، ومن يُخبَر أنه بلا مرجع يعتذر.
 */
class ContextAssembler
{
    private const MAX_ITEMS = 25;

    public function system(AssistantRequest $request): string
    {
        $project = $request->project;
        $parts = ["أنت مساعد «{$project->name}». أجب بالعربية، وبما في المرجع أدناه وحده."];

        $screen = $request->screenKey === null ? null : KnowledgeScreen::query()
            ->forProject($project)
            ->where('key', $request->screenKey)
            ->with('section')
            ->first();

        $section = $screen === null
            ? ($request->sectionName === null ? null : ProjectSection::query()
                ->forProject($project)
                ->where('name', $request->sectionName)
                ->first())
            : $screen->section;

        if ($screen !== null) {
            $parts[] = $this->screenBlock($screen);
        }

        if ($section instanceof ProjectSection) {
            $parts[] = "## القسم\n{$section->name}: ".($section->description ?? 'بلا وصف.');
            $parts[] = $this->knowledgeBlock($section, $request->lastUserMessage());
        } else {
            $parts[] = '## المرجع'."\n".'لا معرفة معتمَدة لهذا الموضع. قل إنك لا تعرف بدل أن تخمّن.';
        }

        $parts[] = $this->levelBlock($request);

        return implode("\n\n", array_filter($parts));
    }

    /** حدّ الرموز كما ضبطه المالك لهذا المستوى في هذا المشروع. */
    public function maxTokens(AssistantRequest $request): int
    {
        $limit = AssistantLevelSetting::query()
            ->forProject($request->project)
            ->where('key', $request->level->value)
            ->value('token_limit');

        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : 1024;
    }

    public function temperature(AssistantRequest $request): float
    {
        $creativity = AssistantLevelSetting::query()
            ->forProject($request->project)
            ->where('key', $request->level->value)
            ->value('creativity');

        // الإبداع في اللوحة ٠..١٠٠، وحرارة المزود ٠..١ — التحويل صريحٌ هنا كي
        // لا يُرسل مئةٌ إلى واجهةٍ تقبل واحدًا فترفض الطلب كله.
        return is_numeric($creativity) ? round(min(100, max(0, (int) $creativity)) / 100, 2) : 0.3;
    }

    private function screenBlock(KnowledgeScreen $screen): string
    {
        $lines = ["## الشاشة\n{$screen->name} (`{$screen->key}`)"];

        if ($screen->description !== null) {
            $lines[] = $screen->description;
        }

        foreach ([
            'عناصرها' => $screen->elements,
            'إجراءاتها' => $screen->actions,
            'حالاتها' => $screen->states,
        ] as $label => $values) {
            if (is_array($values) && $values !== []) {
                $lines[] = "{$label}: ".implode(' · ', $values);
            }
        }

        return implode("\n", $lines);
    }

    private function knowledgeBlock(ProjectSection $section, string $question): string
    {
        $published = KnowledgeItem::query()
            ->where('project_id', $section->project_id)
            ->where('section_id', $section->id)
            ->where('status', KnowledgeStatus::Published->value);

        $total = (clone $published)->count();

        if ($total === 0) {
            return "## المرجع\nلا معرفة منشورة لهذا القسم. قل إنك لا تعرف بدل أن تخمّن.";
        }

        $items = $this->select($published, $question);
        $lines = ['## المرجع (منشور فقط)'];

        foreach ($items as $item) {
            $lines[] = "### {$item->title}\n{$item->body}";
        }

        // الاقتطاع يُعلَن للمساعد نفسه: مرجعٌ ناقص يظنّه المساعد كاملًا يجعله
        // ينفي وجود ما هو منشور فعلًا، فيُقرأ نفيُه حكمًا لا حدَّ علمٍ.
        if ($total > $items->count()) {
            $lines[] = "> المرجع مقتطع: أمامك {$items->count()} من {$total} عنصرًا، اختيرت بأقربها إلى السؤال. "
                .'إن لم تجد الجواب فقل إنك لا تراه في مرجعك، ولا تنفِ وجوده.';
        }

        return implode("\n\n", $lines);
    }

    /**
     * أقرب العناصر إلى السؤال أولًا، ثم الأحدث لملء ما بقي.
     *
     * الأحدثُ وحده اختيارٌ بلا علاقة بالسؤال: قسمٌ فيه أربعون عنصرًا يُسقط
     * خمسة عشر منها صامتًا، فيقول المساعد «لا أعرف» عن معرفةٍ منشورة.
     *
     * @param  Builder<KnowledgeItem>  $published
     * @return Collection<int, KnowledgeItem>
     */
    private function select(Builder $published, string $question): Collection
    {
        $terms = $this->terms($question);

        $matched = $terms === []
            ? collect()
            : (clone $published)
                ->where(function ($query) use ($terms): void {
                    foreach ($terms as $term) {
                        $query->orWhere('title', 'ilike', "%{$term}%")
                            ->orWhere('summary', 'ilike', "%{$term}%")
                            ->orWhere('body', 'ilike', "%{$term}%");
                    }
                })
                ->orderByDesc('updated_at')
                ->limit(self::MAX_ITEMS)
                ->get();

        $remaining = self::MAX_ITEMS - $matched->count();

        if ($remaining <= 0) {
            return $matched;
        }

        $filler = (clone $published)
            ->whereNotIn('id', $matched->pluck('id')->all())
            ->orderByDesc('updated_at')
            ->limit($remaining)
            ->get();

        return $matched->concat($filler);
    }

    /**
     * كلمات السؤال الصالحة للبحث.
     *
     * الحروف القصيرة تطابق كل شيء فلا تميّز، والكثرة تحوّل الاختيار إلى
     * «الكل» فتعود المشكلة. ثمانيةٌ بطول ثلاثة فأكثر حدٌّ عمليّ.
     *
     * @return list<string>
     */
    private function terms(string $question): array
    {
        preg_match_all('/[\p{Arabic}\p{Latin}\d]{3,}/u', $question, $matches);

        return array_slice(array_values(array_unique($matches[0])), 0, 8);
    }

    private function levelBlock(AssistantRequest $request): string
    {
        $setting = AssistantLevelSetting::query()
            ->forProject($request->project)
            ->where('key', $request->level->value)
            ->first();

        if ($setting === null) {
            return '';
        }

        return "## الأسلوب\n{$setting->label}: {$setting->description}\nطول الرد: {$setting->response_length}.";
    }
}
