<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

/**
 * وحدة قياس المؤشر — تحدد كيف تُعرض القيمة وحدّ التنبيه معًا.
 *
 * الوحدة تسافر مع القيمة إلى الواجهة حتى لا تخمّن الواجهة أن ٤٠٠٠ ثانيةٌ أو
 * ريالٌ أو نسبة.
 */
enum MetricUnit: string
{
    case Percent = 'percent';
    case Count = 'count';
    case Money = 'money';
    case Milliseconds = 'milliseconds';
    case Rating = 'rating';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'نسبة مئوية',
            self::Count => 'عدد',
            self::Money => 'مبلغ',
            self::Milliseconds => 'مللي ثانية',
            self::Rating => 'تقييم من ٥',
        };
    }

    /** أقصى قيمة معقولة للحدّ — تمنع «نسبة تتجاوز ٩٠٠٪». */
    public function ceiling(): ?float
    {
        return match ($this) {
            self::Percent => 100.0,
            self::Rating => 5.0,
            default => null,
        };
    }

    public function decimals(): int
    {
        return match ($this) {
            self::Money => 2,
            self::Rating => 1,
            self::Percent => 1,
            default => 0,
        };
    }
}
