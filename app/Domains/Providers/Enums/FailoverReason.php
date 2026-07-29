<?php

declare(strict_types=1);

namespace App\Domains\Providers\Enums;

enum FailoverReason: string
{
    case Manual = 'manual';
    case HealthCheckFailure = 'health_check_failure';
    case ProviderDisabled = 'provider_disabled';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'تحويل يدوي',
            self::HealthCheckFailure => 'فشل الفحص الذاتي',
            self::ProviderDisabled => 'تعطيل المزود',
        };
    }
}
