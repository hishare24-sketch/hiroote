<?php

declare(strict_types=1);

namespace App\Domains\Providers\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Providers\Enums\FailoverReason;
use App\Domains\Providers\Models\AiProvider;

/**
 * تفعيل أو تعطيل مزود. تعطيل المزود النشط يحوّل تلقائيًا للمرشح التالي.
 */
final readonly class ToggleProvider
{
    public function __construct(
        private RecordAuditEntry $audit,
        private PerformFailover $failover,
    ) {}

    public function handle(AiProvider $provider, bool $enabled, ?int $actorId = null): AiProvider
    {
        $wasActive = $provider->is_active;

        $provider->forceFill(['is_enabled' => $enabled])->save();

        $this->audit->handle(new AuditEntry(
            action: $enabled ? 'providers.enable' : 'providers.disable',
            auditable: $provider,
            section: 'providers',
            oldValues: ['is_enabled' => ! $enabled],
            newValues: ['is_enabled' => $enabled],
        ));

        if (! $enabled && $wasActive) {
            // يظل النشط كما هو حتى ينفذ التحويل، ليسجل الحدث من أين إلى أين.
            $this->failover->handle(
                reason: FailoverReason::ProviderDisabled,
                details: ['disabled_provider' => $provider->name],
                triggeredBy: $actorId,
            );

            // لا مرشح بديل؟ لا يبقى مزود معطل في موقع النشط.
            if ($provider->refresh()->is_active) {
                $provider->forceFill(['is_active' => false])->save();
            }
        }

        return $provider->refresh();
    }
}
