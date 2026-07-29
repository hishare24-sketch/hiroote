<?php

declare(strict_types=1);

namespace App\Domains\Providers\Enums;

/**
 * حالة اتصال المزود كما تظهر في شاشة المزودين (وثيقة التصميم §8-9).
 */
enum ProviderStatus: string
{
    case Operational = 'operational';
    case Degraded = 'degraded';
    case Down = 'down';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'يعمل',
            self::Degraded => 'متذبذب',
            self::Down => 'متعطل',
            self::Unknown => 'غير معروف',
        };
    }
}
