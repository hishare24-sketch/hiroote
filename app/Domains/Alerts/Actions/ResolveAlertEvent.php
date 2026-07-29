<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Alerts\Enums\AlertEventStatus;
use App\Domains\Alerts\Models\AlertEvent;

/**
 * الإقرار بحدث أو إغلاقه يدويًا.
 *
 * الإغلاق اليدوي لا يمنع فتح حدث جديد إن بقي المؤشر متجاوزًا: من أغلق الحدث
 * لم يُصلح سببه، والتهدئة وحدها هي ما يمنع تكرار الإشعار.
 */
final readonly class ResolveAlertEvent
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(AlertEvent $event, AlertEventStatus $status): void
    {
        if ($event->status === $status || $status === AlertEventStatus::Triggered) {
            return;
        }

        $event->forceFill([
            'status' => $status,
            'acknowledged_at' => $event->acknowledged_at ?? now(),
            'acknowledged_by' => $event->acknowledged_by ?? auth()->id(),
            'resolved_at' => $status === AlertEventStatus::Resolved ? now() : null,
        ])->save();

        $this->audit->handle(new AuditEntry(
            action: $status === AlertEventStatus::Resolved ? 'alerts.close' : 'alerts.acknowledge',
            auditable: $event,
            section: 'alerts',
            newValues: [
                'المؤشر' => $event->metric->label(),
                'القيمة' => (string) $event->observed_value,
                'الحالة' => $status->label(),
            ],
        ));
    }
}
