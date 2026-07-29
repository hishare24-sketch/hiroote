<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * مستويات المساعد الأربعة — وثيقة التصميم §12.
 */
enum AssistantLevel: string implements PresentableEnum
{
    case Direct = 'direct';
    case Balanced = 'balanced';
    case Proactive = 'proactive';
    case Expert = 'expert';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'مباشر',
            self::Balanced => 'متوازن',
            self::Proactive => 'استباقي',
            self::Expert => 'خبير',
        };
    }

    /** المستوى ليس نجاحًا ولا خطرًا — يبقى محايدًا، ويحمل الخبير لون الهوية. */
    public function tone(): string
    {
        return match ($this) {
            self::Expert => 'accent',
            self::Proactive => 'info',
            default => 'neutral',
        };
    }
}
