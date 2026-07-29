<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Enums;

/** دوائر الشات — من يصل إلى من. */
enum ChatScope: string
{
    case Project = 'project';
    case Subscriber = 'subscriber';
    case Platform = 'platform';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Project => 'داخل المشروع',
            self::Subscriber => 'كل مشاريع المشترك',
            self::Platform => 'عبر المشتركين',
            self::Support => 'مع المنصّة',
        };
    }

    public function about(): string
    {
        return match ($this) {
            self::Project => 'أعضاء مشروع واحد.',
            self::Subscriber => 'فريق المشترك عبر مشاريعه كلها.',
            self::Platform => 'تواصل بين مشتركين مختلفين — أوسع دائرة وأخطرها على الخصوصية.',
            self::Support => 'المستخدم مع فريق المنصّة.',
        };
    }

    /** الدائرة التي تُخرج المحادثة من حدود المشترك. */
    public function crossesTenants(): bool
    {
        return $this === self::Platform;
    }
}
