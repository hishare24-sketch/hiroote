<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * حالة الحدث الواحد.
 *
 * «مُقَرّ» ليس «محلولًا»: الإقرار يعني أن إنسانًا رآه فتوقفت الإشعارات، والحل
 * يعني أن المؤشر عاد تحت الحد. الخلط بينهما يُسكت التنبيه ويترك السبب قائمًا.
 */
enum AlertEventStatus: string implements PresentableEnum
{
    case Triggered = 'triggered';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Triggered => 'مفتوح',
            self::Acknowledged => 'مُقَرّ',
            self::Resolved => 'عاد للطبيعي',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Triggered => 'danger',
            self::Acknowledged => 'warning',
            self::Resolved => 'success',
        };
    }

    public function isOpen(): bool
    {
        return $this !== self::Resolved;
    }
}
