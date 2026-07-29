<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * درجة خطورة الحالة المفتوحة — وثيقة التصميم §10.
 */
enum EscalationSeverity: string implements PresentableEnum
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'حرج',
            self::High => 'مرتفع',
            self::Medium => 'متوسط',
            self::Low => 'منخفض',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::High => 'warning',
            self::Medium => 'info',
            self::Low => 'neutral',
        };
    }
}
