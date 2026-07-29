<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use RuntimeException;

/**
 * إغلاق رصد: إمّا عولج بتعديل، وإمّا استُبعد.
 *
 * الإغلاق بوصفه «عولج» يشترط تحققًا ميدانيًا سابقًا حين يكون مصدر الرصد المساعد
 * أو المستخدم. بلا هذا الشرط تصير الحلقة تصديقًا لما قاله النموذج: يرصد، فيُغلق
 * الرصد بتعديل، فيقرأ النموذج تعديله في الجولة التالية. الشرط هو ما يبقي إنسانًا
 * قد **جرّب** بين الرصد والكتابة.
 *
 * والاستبعاد لا يشترط شيئًا: رفض رصدٍ لم يثبت ليس فعلًا خطرًا.
 */
final readonly class CloseKnowledgeFeedback
{
    public const FIXED = 'fixed';

    public const DISMISSED = 'dismissed';

    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(KnowledgeFeedback $feedback, string $resolution, ?string $note = null): void
    {
        if ($resolution === self::FIXED && ! $feedback->loadMissing('verifications')->isActionable()) {
            throw new RuntimeException('لا يمكن إغلاق الرصد بوصفه معالَجًا قبل تحقق ميداني يثبته.');
        }

        $feedback->forceFill([
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
            'resolution' => $resolution,
        ])->save();

        $this->audit->handle(new AuditEntry(
            action: $resolution === self::FIXED
                ? 'knowledge.feedback_resolve'
                : 'knowledge.feedback_dismiss',
            auditable: $feedback,
            section: 'knowledge',
            newValues: [
                'الإغلاق' => $resolution === self::FIXED ? 'عولج بتعديل' : 'استُبعد',
                'السبب' => $note ?? '—',
            ],
            reason: mb_substr($feedback->body, 0, 120),
        ));
    }

    public function reopen(KnowledgeFeedback $feedback): void
    {
        if ($feedback->resolved_at === null) {
            return;
        }

        $feedback->forceFill([
            'resolved_at' => null,
            'resolved_by' => null,
            'resolution' => null,
        ])->save();

        $this->audit->handle(new AuditEntry(
            action: 'knowledge.feedback_reopen',
            auditable: $feedback,
            section: 'knowledge',
            reason: mb_substr($feedback->body, 0, 120),
        ));
    }
}
