<?php

declare(strict_types=1);

namespace App\Support\Enums;

use BackedEnum;

/**
 * كل enum يظهر في الواجهة يحمل تسميته ونغمته معه.
 *
 * الواجهة لا تترجم ولا تختار لونًا: مصدر الحقيقة واحد هنا، فلا تنشأ حالة
 * يسمّيها الخادم شيئًا وتلوّنها الواجهة شيئًا آخر (وثيقة 03 §5).
 */
interface PresentableEnum extends BackedEnum
{
    public function label(): string;

    /** إحدى الحالات الخمس أو `accent`. */
    public function tone(): string;
}
