<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Enums;

use App\Support\Enums\PresentableEnum;

/** من أين جاءت المعرفة — وثيقة 06 §15 (النصوص والملفات والصور والروابط). */
enum SourceKind: string implements PresentableEnum
{
    case Link = 'link';
    case File = 'file';
    case Note = 'note';

    public function label(): string
    {
        return match ($this) {
            self::Link => 'رابط',
            self::File => 'ملف',
            self::Note => 'ملاحظة',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Link => 'info',
            self::File => 'accent',
            self::Note => 'neutral',
        };
    }
}
