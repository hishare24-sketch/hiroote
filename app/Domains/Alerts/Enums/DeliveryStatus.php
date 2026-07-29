<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

use App\Support\Enums\PresentableEnum;

/** حالة إيصال واحد إلى مستلم واحد عبر قناة واحدة. */
enum DeliveryStatus: string implements PresentableEnum
{
    case Delivered = 'delivered';
    case Pending = 'pending';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Delivered => 'وصل',
            self::Pending => 'معلّق',
            self::Failed => 'أخفق',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Delivered => 'success',
            self::Pending => 'warning',
            self::Failed => 'danger',
        };
    }
}
