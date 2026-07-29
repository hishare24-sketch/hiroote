<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * من رفع الملاحظة.
 *
 * المصدر يُعرض دائمًا لأن وزن الرصد يختلف باختلافه: ما يرفعه المساعد إشارةٌ
 * إحصائية تحتاج تحققًا، وما يكتبه موظف دعم شهادةُ من رأى.
 */
enum FeedbackSource: string implements PresentableEnum
{
    case Assistant = 'assistant';
    case User = 'user';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Assistant => 'رصد المساعد',
            self::User => 'ملاحظة مستخدم',
            self::Support => 'ملاحظة دعم',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Assistant => 'accent',
            self::User => 'info',
            self::Support => 'neutral',
        };
    }

    /** هل يحتاج هذا المصدر تحققًا ميدانيًا قبل التعديل. */
    public function needsVerification(): bool
    {
        return $this !== self::Support;
    }
}
