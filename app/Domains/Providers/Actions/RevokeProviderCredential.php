<?php

declare(strict_types=1);

namespace App\Domains\Providers\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Providers\Models\AiProviderCredential;

/**
 * إبطال مفتاح — soft delete يبقي أثره التاريخي دون صلاحية استخدام.
 */
final readonly class RevokeProviderCredential
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(AiProviderCredential $credential): void
    {
        $credential->forceFill(['is_active' => false])->save();
        $credential->delete();

        $this->audit->handle(new AuditEntry(
            action: 'providers.credential_revoked',
            auditable: $credential,
            section: 'providers',
            oldValues: [
                'label' => $credential->label,
                'key_hint' => $credential->key_hint,
            ],
        ));
    }
}
