<?php

declare(strict_types=1);

namespace App\Support\Http;

use App\Domains\Providers\Enums\ProviderSetting;
use App\Domains\Providers\Enums\ProviderStatus;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\ProviderSettingValue;

/**
 * مؤشر حالة النظام في ترويسة كل شاشة — وثيقة التصميم §4.
 */
final class SystemStatus
{
    /**
     * @return array{label: string, tone: string}
     */
    public static function current(): array
    {
        if (ProviderSettingValue::isEnabled(ProviderSetting::MaintenanceMode)) {
            return ['label' => 'وضع الصيانة', 'tone' => 'warning'];
        }

        if (! ProviderSettingValue::isEnabled(ProviderSetting::AssistantEnabled)) {
            return ['label' => 'المساعد متوقف', 'tone' => 'neutral'];
        }

        $active = AiProvider::active();

        return match (true) {
            $active === null => ['label' => 'لا يوجد مزود نشط', 'tone' => 'danger'],
            $active->status === ProviderStatus::Down => ['label' => 'المزود النشط متعطل', 'tone' => 'danger'],
            $active->status === ProviderStatus::Degraded => ['label' => 'أداء متذبذب', 'tone' => 'warning'],
            $active->activeCredential() === null => ['label' => 'بانتظار مفتاح المزود', 'tone' => 'warning'],
            $active->status === ProviderStatus::Unknown => ['label' => 'بانتظار أول فحص', 'tone' => 'info'],
            default => ['label' => 'النظام يعمل طبيعيًا', 'tone' => 'success'],
        };
    }
}
