<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * قناة إيصال التنبيه — وثيقة 06 §11 («البريد وقنوات الإشعار»).
 *
 * `isWired()` تفصل ما يصل اليوم عمّا ينتظر ربطًا. القناة غير المربوطة تُختار
 * وتُحفظ، لكن سجل الإرسال يقول «معلّق» لا «أُرسل» — تنبيهٌ يظن المشغّل أنه
 * وصل ولم يصل أخطر من ألّا يكون هناك تنبيه.
 */
enum AlertChannel: string implements PresentableEnum
{
    case InApp = 'in_app';
    case Email = 'email';
    case Webhook = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'داخل اللوحة',
            self::Email => 'البريد',
            self::Webhook => 'Webhook',
        };
    }

    public function tone(): string
    {
        return $this->isWired() ? 'success' : 'neutral';
    }

    public function isWired(): bool
    {
        return $this === self::InApp;
    }

    public function pendingReason(): ?string
    {
        return match ($this) {
            self::InApp => null,
            self::Email => 'ينتظر ضبط مُرسِل البريد.',
            self::Webhook => 'ينتظر عنوان استقبال في إعدادات المشروع.',
        };
    }
}
