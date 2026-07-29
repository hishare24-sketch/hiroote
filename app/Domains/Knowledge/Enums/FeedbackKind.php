<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * ما يصل من خارج المحرِّر — وثيقة 06 §15.
 *
 * الثلاثة تُعالَج بالطريقة نفسها (تُقرأ ثم تُغلق)، ويفرّقها مصدرها: المستخدم
 * قيّم، أو سأل ولم يجد، أو اقترح المساعد نفسه ما ينقصه.
 */
enum FeedbackKind: string implements PresentableEnum
{
    case Feedback = 'feedback';
    case Unanswered = 'unanswered';
    case Suggestion = 'suggestion';

    public function label(): string
    {
        return match ($this) {
            self::Feedback => 'تغذية راجعة',
            self::Unanswered => 'سؤال بلا إجابة',
            self::Suggestion => 'اقتراح المساعد',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Feedback => 'info',
            self::Unanswered => 'danger',
            self::Suggestion => 'accent',
        };
    }
}
