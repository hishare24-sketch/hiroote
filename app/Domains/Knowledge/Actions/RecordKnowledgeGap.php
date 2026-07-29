<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Actions;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\FeedbackSource;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;

/**
 * تسجيل ثغرة معرفية — **قاعدة تكرارٍ واحدة لكل من يرفعها**.
 *
 * يرفعها طرفان: المشروع الخارجي عبر جسر الوارد، والـ Orchestrator حين يجيب
 * المساعد بلا مرجع. وقاعدتان للتكرار في مكانين تجعلان السؤال نفسه صفًّا واحدًا
 * إن جاء من هنا وسبعةَ صفوف إن جاء من هناك — فيُقرأ «سأله سبعة» عن سؤالٍ سأله
 * واحد سبعَ مرات، أو العكس.
 *
 * وما يُسجَّل هنا **رصدٌ لا حكم**: يدخل الطابور البشري ولا يعدّل معرفةً ولا
 * يغلق شيئًا.
 */
final readonly class RecordKnowledgeGap
{
    /**
     * @return array{feedback: KnowledgeFeedback, created: bool}
     */
    public function handle(
        Project $project,
        string $body,
        ?KnowledgeScreen $screen = null,
        ?ProjectSection $section = null,
        FeedbackKind $kind = FeedbackKind::Unanswered,
        FeedbackSource $source = FeedbackSource::Assistant,
        ?int $conversationId = null,
    ): array {
        $body = mb_substr(trim($body), 0, 1000);

        $existing = KnowledgeFeedback::query()
            ->forProject($project)
            ->where('screen_id', $screen?->id)
            ->where('body', $body)
            ->open()
            ->first();

        if ($existing !== null) {
            // العدّاد صغير (tinyint) فيُحدّ عند سقفه: رقمٌ يلتف إلى الصفر يقلب
            // «تكرر كثيرًا» إلى «لم يتكرر».
            $existing->forceFill(['occurrences' => min($existing->occurrences + 1, 255)])->save();

            return ['feedback' => $existing, 'created' => false];
        }

        // قسم الشاشة أولى من القسم المرسل: الشاشة أدقّ موضعًا، والاثنان قد
        // يختلفان إن أُرسل اسم قسمٍ لا تنتمي إليه الشاشة.
        $sectionId = $screen instanceof KnowledgeScreen ? $screen->section_id : $section?->id;

        $feedback = KnowledgeFeedback::query()->create([
            'project_id' => $project->id,
            'section_id' => $sectionId,
            'screen_id' => $screen?->id,
            'conversation_id' => $conversationId,
            'kind' => $kind,
            'source' => $source,
            'body' => $body,
            'occurrences' => 1,
        ]);

        return ['feedback' => $feedback, 'created' => true];
    }
}
