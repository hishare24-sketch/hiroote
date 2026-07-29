<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Enums;

use App\Support\Enums\PresentableEnum;

enum MessageRole: string implements PresentableEnum
{
    case User = 'user';
    case Assistant = 'assistant';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::User => 'المستخدم',
            self::Assistant => 'المساعد',
            self::System => 'النظام',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::User => 'neutral',
            self::Assistant => 'accent',
            self::System => 'info',
        };
    }
}
