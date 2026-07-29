<?php

declare(strict_types=1);

namespace App\Support\Text;

use Illuminate\Support\Str;

/**
 * معرّف نصّي مقروء للمشاريع والأقسام.
 *
 * `Str::slug` الافتراضي ينقل العربية حرفيًا إلى لاتينية، فيصير «مشروع ثالث»
 * إلى `mshroaa-thalth` — سلسلة لا يقرؤها عربي ولا إنجليزي. المعرّف هنا ليس
 * جزءًا من رابط عام بل مفتاح داخلي يظهر للمشغّل، فالإبقاء على العربية أوضح.
 */
final class Slug
{
    public static function make(string $value, string $fallback = 'item'): string
    {
        // اللغة null توقف النقل الحرفي وتُبقي الحروف كما هي.
        $slug = Str::slug($value, '-', null);

        return $slug === '' ? $fallback : $slug;
    }
}
