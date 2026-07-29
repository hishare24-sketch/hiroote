<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

/** تجميع المؤشرات في منشئ القاعدة حتى لا تُعرض قائمة من اثني عشر خيارًا مسطّحًا. */
enum MetricFamily: string
{
    case Conversations = 'conversations';
    case Cost = 'cost';
    case Providers = 'providers';
    case Knowledge = 'knowledge';

    public function label(): string
    {
        return match ($this) {
            self::Conversations => 'المحادثات والجودة',
            self::Cost => 'الاستهلاك والتكلفة',
            self::Providers => 'المزودون',
            self::Knowledge => 'قاعدة المعرفة',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Conversations => 'info',
            self::Cost => 'warning',
            self::Providers => 'accent',
            self::Knowledge => 'success',
        };
    }
}
