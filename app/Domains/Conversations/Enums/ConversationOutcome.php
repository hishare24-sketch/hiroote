<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * الحالة النهائية للمحادثة — وثيقة التصميم §6 (عمود «الحالة» في سجل المحادثات).
 */
enum ConversationOutcome: string implements PresentableEnum
{
    case Resolved = 'resolved';
    case Ticket = 'ticket';
    case Human = 'human';
    case Abandoned = 'abandoned';
    case Open = 'open';

    public function label(): string
    {
        return match ($this) {
            self::Resolved => 'تم الحل',
            self::Ticket => 'تذكرة',
            self::Human => 'بشري',
            self::Abandoned => 'منقطع',
            self::Open => 'جارية',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Resolved => 'success',
            self::Ticket => 'warning',
            self::Human => 'info',
            self::Abandoned => 'danger',
            self::Open => 'neutral',
        };
    }
}
