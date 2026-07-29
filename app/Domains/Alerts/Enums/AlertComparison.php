<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * شرط المقارنة بين قيمة المؤشر والحدّ — وثيقة 06 §11.
 *
 * الشرط يُعرض كلمةً لا رمزًا: خوارزمية الاتجاه ثنائي الاتجاه تعكس `<` و`>` في
 * سياق عربي، فيقرأ المشغّل «أكبر من» حيث كتبنا «أصغر من» — عكسٌ صامت في أخطر
 * موضع تحتمله شاشة تنبيهات.
 */
enum AlertComparison: string implements PresentableEnum
{
    case GreaterThan = 'gt';
    case GreaterOrEqual = 'gte';
    case LessThan = 'lt';
    case LessOrEqual = 'lte';

    public function label(): string
    {
        return match ($this) {
            self::GreaterThan => 'يتجاوز',
            self::GreaterOrEqual => 'يبلغ أو يتجاوز',
            self::LessThan => 'يقلّ عن',
            self::LessOrEqual => 'يبلغ أو يقلّ عن',
        };
    }

    public function tone(): string
    {
        return 'neutral';
    }

    public function holds(float $observed, float $threshold): bool
    {
        return match ($this) {
            self::GreaterThan => $observed > $threshold,
            self::GreaterOrEqual => $observed >= $threshold,
            self::LessThan => $observed < $threshold,
            self::LessOrEqual => $observed <= $threshold,
        };
    }
}
