<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * حالة عنصر المعرفة — وثيقة 06 §15.
 *
 * المنشور وحده يصل المساعد: المسودة والمراجعة عملٌ داخلي، ووصولهما إلى مستخدم
 * المشروع يعني إجابة بمعلومة لم يعتمدها أحد.
 */
enum KnowledgeStatus: string implements PresentableEnum
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Review => 'قيد المراجعة',
            self::Published => 'منشور',
            self::Archived => 'مؤرشف',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Review => 'warning',
            self::Published => 'success',
            self::Archived => 'info',
        };
    }

    /** هل يصل هذا العنصر إلى المساعد. */
    public function isLive(): bool
    {
        return $this === self::Published;
    }
}
