<?php

declare(strict_types=1);

namespace App\Domains\Providers\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\AiProviderCredential;
use Illuminate\Support\Facades\DB;

/**
 * حفظ مفتاح مزود مشفرًا. المفتاح الجديد يبطل سابقيه (rotation — وثيقة 02 §12)،
 * ولا يدخل نصه في سجل التدقيق أبدًا — فقط تلميح آخر 4 أحرف.
 */
final readonly class StoreProviderCredential
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(
        AiProvider $provider,
        string $label,
        string $apiKey,
        ?int $createdBy = null,
    ): AiProviderCredential {
        return DB::transaction(function () use ($provider, $label, $apiKey, $createdBy): AiProviderCredential {
            $provider->credentials()->where('is_active', true)->update(['is_active' => false]);

            $credential = $provider->credentials()->create([
                'label' => $label,
                'api_key' => $apiKey,
                'key_hint' => mb_substr($apiKey, -4),
                'is_active' => true,
                'created_by' => $createdBy,
            ]);

            $this->audit->handle(new AuditEntry(
                action: 'providers.credential_stored',
                auditable: $credential,
                section: 'providers',
                newValues: [
                    'provider' => $provider->name,
                    'label' => $label,
                    'key_hint' => $credential->key_hint,
                ],
            ));

            return $credential;
        });
    }
}
