<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\Services;

use App\Domains\Assistants\Models\AssistantLevelSetting;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Orchestrator\DTOs\AssistantRequest;

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
            $parts[] = $this->knowledgeBlock($section);
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

    private function knowledgeBlock(ProjectSection $section): string
    {
        $items = KnowledgeItem::query()
            ->where('project_id', $section->project_id)
            ->where('section_id', $section->id)
            ->where('status', KnowledgeStatus::Published->value)
            ->orderByDesc('updated_at')
            ->limit(self::MAX_ITEMS)
            ->get();

        if ($items->isEmpty()) {
            return "## المرجع\nلا معرفة منشورة لهذا القسم. قل إنك لا تعرف بدل أن تخمّن.";
        }

        $lines = ['## المرجع (منشور فقط)'];

        foreach ($items as $item) {
            $lines[] = "### {$item->title}\n{$item->body}";
        }

        return implode("\n\n", $lines);
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
