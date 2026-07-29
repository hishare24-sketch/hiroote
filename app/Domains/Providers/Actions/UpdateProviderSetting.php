<?php

declare(strict_types=1);

namespace App\Domains\Providers\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Providers\Enums\ProviderSetting;
use App\Domains\Providers\Models\ProviderSettingValue;

/**
 * تبديل سويتش تحكم — كل تبديل تغيير حساس يُسجَّل (وثيقة 05 §7).
 */
final readonly class UpdateProviderSetting
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(ProviderSetting $setting, bool $enabled): void
    {
        $previous = ProviderSettingValue::isEnabled($setting);

        ProviderSettingValue::query()->updateOrCreate(
            ['key' => $setting->value],
            ['enabled' => $enabled],
        );

        if ($previous === $enabled) {
            return;
        }

        $this->audit->handle(new AuditEntry(
            action: 'settings.toggle',
            section: 'settings',
            oldValues: [$setting->label() => $previous],
            newValues: [$setting->label() => $enabled],
        ));
    }
}
