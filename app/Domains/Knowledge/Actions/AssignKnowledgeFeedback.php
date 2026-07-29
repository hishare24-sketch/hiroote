<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Knowledge\Models\KnowledgeFeedback;

/**
 * إسناد الرصد إلى نفسه أو رفع اليد عنه.
 *
 * الإسناد لنفس المستخدم فقط عمدًا: توزيع العمل على الآخرين قرار إداري لا يصلح
 * أن يُتخذ بنقرة من شاشة معرفة، ورصدٌ مُسنَد إلى من لم يقبله يبقى بلا صاحب
 * وهو يبدو مملوكًا.
 */
final readonly class AssignKnowledgeFeedback
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(KnowledgeFeedback $feedback): void
    {
        $actor = auth()->id();
        $taking = $feedback->assigned_to !== $actor;

        $feedback->forceFill(['assigned_to' => $taking ? $actor : null])->save();

        $this->audit->handle(new AuditEntry(
            action: $taking ? 'knowledge.feedback_claim' : 'knowledge.feedback_release',
            auditable: $feedback,
            section: 'knowledge',
            reason: mb_substr($feedback->body, 0, 120),
        ));
    }
}
