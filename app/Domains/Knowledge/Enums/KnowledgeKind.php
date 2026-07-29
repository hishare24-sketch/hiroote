<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Enums;

use App\Support\Enums\PresentableEnum;

/** نوع عنصر المعرفة — يحدد كيف يستعمله المساعد لا شكله فقط. */
enum KnowledgeKind: string implements PresentableEnum
{
    case Article = 'article';
    case Faq = 'faq';
    case Procedure = 'procedure';
    case Policy = 'policy';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'شرح',
            self::Faq => 'سؤال وجواب',
            self::Procedure => 'إجراء',
            self::Policy => 'سياسة',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Article => 'شرح عام لمفهوم أو شاشة.',
            self::Faq => 'سؤال متكرر بجوابه المعتمد.',
            self::Procedure => 'خطوات مرتبة يتبعها المستخدم.',
            self::Policy => 'قاعدة ملزمة لا يجتهد المساعد حولها.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Policy => 'warning',
            self::Procedure => 'info',
            default => 'neutral',
        };
    }
}
