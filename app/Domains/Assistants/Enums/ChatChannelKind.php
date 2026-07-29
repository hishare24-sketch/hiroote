<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Enums;

/** أنواع قنوات الشات التي يجيزها المالك للمشروع. */
enum ChatChannelKind: string
{
    case Assistant = 'assistant';
    case Direct = 'direct';
    case Group = 'group';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Assistant => 'شات المساعد',
            self::Direct => 'محادثة ثنائية',
            self::Group => 'مجموعات',
            self::Support => 'الدعم الفني',
        };
    }

    public function about(): string
    {
        return match ($this) {
            self::Assistant => 'المستخدم يحاور المساعد الذكي وحده.',
            self::Direct => 'عضوان يتحاوران مباشرةً — بلا مساعد بينهما.',
            self::Group => 'قناة لعدة أعضاء داخل الدائرة المسموحة.',
            self::Support => 'المستخدم يفتح قناة مع فريق المنصّة.',
        };
    }

    /** هل يقرأ فيها المساعد كلام بشرٍ لبشر؟ */
    public function carriesHumanToHuman(): bool
    {
        return $this !== self::Assistant;
    }
}
