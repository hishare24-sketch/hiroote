<?php

declare(strict_types=1);

namespace App\Domains\Providers\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Providers\Enums\FailoverReason;
use App\Domains\Providers\Models\AiFailoverEvent;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Support\Facades\DB;

/**
 * تحويل المزود النشط — يدويًا أو تلقائيًا عند فشل الفحص (وثيقة التصميم §8-9).
 */
final readonly class PerformFailover
{
    public function __construct(private RecordAuditEntry $audit) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function handle(
        FailoverReason $reason,
        ?AiProvider $to = null,
        array $details = [],
        ?int $triggeredBy = null,
    ): ?AiFailoverEvent {
        return DB::transaction(function () use ($reason, $to, $details, $triggeredBy): ?AiFailoverEvent {
            $current = AiProvider::query()->where('is_active', true)->lockForUpdate()->first();

            $target = $to ?? AiProvider::nextCandidate(excluding: $current);

            // لا مرشح متاح، أو المستهدف هو النشط أصلًا — لا شيء يحدث.
            if ($target === null || ($current !== null && $target->is($current))) {
                return null;
            }

            if (! $target->is_enabled) {
                return null;
            }

            $current?->forceFill(['is_active' => false])->save();
            $target->forceFill(['is_active' => true])->save();

            $event = AiFailoverEvent::query()->create([
                'from_provider_id' => $current?->id,
                'to_provider_id' => $target->id,
                'reason' => $reason,
                'triggered_by' => $triggeredBy,
                'details' => $details === [] ? null : $details,
            ]);

            $this->audit->handle(new AuditEntry(
                action: 'providers.failover',
                auditable: $target,
                section: 'providers',
                oldValues: ['active_provider' => $current?->name],
                newValues: ['active_provider' => $target->name],
                reason: $reason->label(),
            ));

            return $event;
        });
    }
}
