<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Knowledge\Enums\VerificationOutcome;
use App\Domains\Knowledge\Models\FeedbackVerification;
use App\Domains\Knowledge\Models\KnowledgeFeedback;

/**
 * تسجيل محضر تحقق ميداني على رصد.
 *
 * الخطوات مطلوبة لا اختيارية: «تحقّقتُ» بلا ذكر ما فُعل ليست إثباتًا بل توقيعًا.
 * ومن يقرأ السجل بعد شهر يحتاج أن يعرف ماذا جُرِّب بالضبط ليحكم على النتيجة.
 */
final readonly class RecordFeedbackVerification
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(
        KnowledgeFeedback $feedback,
        VerificationOutcome $outcome,
        string $steps,
        ?string $finding = null,
        ?int $screenId = null,
    ): FeedbackVerification {
        $verification = FeedbackVerification::query()->create([
            'project_id' => $feedback->project_id,
            'knowledge_feedback_id' => $feedback->id,
            'screen_id' => $screenId ?? $feedback->screen_id,
            'verified_by' => auth()->id(),
            'outcome' => $outcome,
            'steps' => $steps,
            'finding' => $finding,
        ]);

        // من تحقّق يصير مسؤولًا عن الرصد ما لم يكن مُسندًا لغيره.
        if ($feedback->assigned_to === null) {
            $feedback->forceFill(['assigned_to' => auth()->id()])->save();
        }

        $this->audit->handle(new AuditEntry(
            action: 'knowledge.feedback_verify',
            auditable: $feedback,
            section: 'knowledge',
            newValues: [
                'النتيجة' => $outcome->label(),
                'الخطوات' => mb_substr($steps, 0, 200),
            ],
            reason: mb_substr($feedback->body, 0, 120),
        ));

        return $verification;
    }
}
