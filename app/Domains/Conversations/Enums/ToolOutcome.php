<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * نتيجة استدعاء أداة — وثيقة التصميم §6 (الأدوات والبيانات المستدعاة).
 */
enum ToolOutcome: string implements PresentableEnum
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'ناجح',
            self::Failed => 'فشل',
            self::Skipped => 'لم يستخدم',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Succeeded => 'success',
            self::Failed => 'danger',
            self::Skipped => 'neutral',
        };
    }
}
