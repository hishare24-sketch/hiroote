<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * نتيجة التحقق الميداني — ما وجده موظف الدعم حين مثّل دور المستخدم.
 *
 * «لم يتكرر» نتيجةٌ كاملة لا فشلٌ في التحقق: رصدٌ لا يُعاد إنتاجه لا يُبنى
 * عليه تعديل، وإغلاقه باستبعاد أنظفُ من تعديل وصفٍ لعلّةٍ لم تُثبَت.
 */
enum VerificationOutcome: string implements PresentableEnum
{
    case Reproduced = 'reproduced';
    case NotReproduced = 'not_reproduced';
    case DifferentCause = 'different_cause';

    public function label(): string
    {
        return match ($this) {
            self::Reproduced => 'تكرّر معي',
            self::NotReproduced => 'لم يتكرر',
            self::DifferentCause => 'السبب مختلف',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Reproduced => 'danger',
            self::NotReproduced => 'success',
            self::DifferentCause => 'warning',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Reproduced => 'واجهتُ ما واجهه العميل على الشاشة نفسها.',
            self::NotReproduced => 'جرّبتُ الخطوات ولم أجد ما رُصد.',
            self::DifferentCause => 'المشكلة قائمة لكن سببها غير المرصود.',
        };
    }

    /** هل تُسوّغ هذه النتيجة تعديل المحتوى التعريفي. */
    public function justifiesEdit(): bool
    {
        return $this !== self::NotReproduced;
    }
}
