<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Services;

use App\Domains\Conversations\Models\ConversationEscalation;
use App\Support\Enums\EnumPayload;

/**
 * الشكل الوحيد لحالة التصعيد المرسلة للواجهة — العقد `EscalationRow`.
 *
 * مكان واحد يبنيه: شاشة التحويل وتفاصيل المحادثة تعرضان الحقول نفسها، فلو
 * بُني في موضعين لانحرف أحدهما عند أول تعديل.
 */
final class EscalationPresenter
{
    /**
     * @return array{
     *     id: int, reference: string, conversation_reference: string|null, conversation_id: int|null,
     *     target: array{value: string, label: string, tone: string},
     *     severity: array{value: string, label: string, tone: string},
     *     reason: string, section: string, subject: string,
     *     wait_seconds: int|null, handling_seconds: int|null,
     *     resolved_at: string|null, created_at: string
     * }
     */
    public static function row(ConversationEscalation $escalation, ?string $conversationReference = null): array
    {
        return [
            'id' => $escalation->id,
            'reference' => $escalation->reference,
            'conversation_reference' => $conversationReference ?? $escalation->conversation?->reference,
            'conversation_id' => $escalation->conversation_id,
            'target' => EnumPayload::from($escalation->target),
            'severity' => EnumPayload::from($escalation->severity),
            'reason' => $escalation->reason,
            'section' => $escalation->section,
            'subject' => $escalation->subject,
            'wait_seconds' => $escalation->wait_seconds,
            'handling_seconds' => $escalation->handling_seconds,
            'resolved_at' => $escalation->resolved_at?->toIso8601String(),
            'created_at' => $escalation->created_at->toIso8601String(),
        ];
    }

    /**
     * @param  iterable<ConversationEscalation>  $escalations
     * @return list<array{
     *     id: int, reference: string, conversation_reference: string|null, conversation_id: int|null,
     *     target: array{value: string, label: string, tone: string},
     *     severity: array{value: string, label: string, tone: string},
     *     reason: string, section: string, subject: string,
     *     wait_seconds: int|null, handling_seconds: int|null,
     *     resolved_at: string|null, created_at: string
     * }>
     */
    public static function rows(iterable $escalations): array
    {
        $rows = [];

        foreach ($escalations as $escalation) {
            $rows[] = self::row($escalation);
        }

        return $rows;
    }
}
