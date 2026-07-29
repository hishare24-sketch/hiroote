<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Knowledge\Models\KnowledgeFeedback;

/**
 * إغلاق ملاحظة أو سؤال بلا إجابة — وثيقة 06 §15.
 */
final readonly class ResolveKnowledgeFeedback
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(KnowledgeFeedback $feedback, bool $resolved): void
    {
        if (($feedback->resolved_at !== null) === $resolved) {
            return;
        }

        $feedback->forceFill([
            'resolved_at' => $resolved ? now() : null,
            'resolved_by' => $resolved ? auth()->id() : null,
        ])->save();

        $this->audit->handle(new AuditEntry(
            action: $resolved ? 'knowledge.feedback_resolve' : 'knowledge.feedback_reopen',
            auditable: $feedback,
            section: 'knowledge',
            newValues: [$feedback->kind->label() => $resolved ? 'مغلقة' : 'مفتوحة'],
            reason: mb_substr($feedback->body, 0, 120),
        ));
    }
}
