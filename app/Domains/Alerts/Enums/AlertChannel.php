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

    /**
     * هل تصل هذه القناة **في هذه البيئة**؟
     *
     * كانت مثبَّتة في الكود، فتفعيل البريد يتطلب تعديل كود لا إعدادًا: مالكٌ
     * يضبط SMTP صحيحًا يبقى تنبيهُه «معلّقًا» ولا يعرف السبب. صارت تُقرأ من
     * الإعداد، فتتبع اللوحةُ الواقعَ بلا نشرة جديدة.
     */
    public function isWired(): bool
    {
        return match ($this) {
            self::InApp => true,
            // `log` و`array` تبتلعان الرسالة: الأولى تكتبها في ملفّ والثانية
            // في الذاكرة، وكلتاهما «أُرسل» في الكود و«لم يصل» عند المستلم.
            self::Email => ! in_array(
                (string) config('mail.default'),
                ['log', 'array', 'null', ''],
                strict: true,
            ),
            self::Webhook => false,
        };
    }

    public function pendingReason(): ?string
    {
        if ($this->isWired()) {
            return null;
        }

        return match ($this) {
            self::InApp => null,
            self::Email => 'ينتظر ضبط مُرسِل بريد حقيقي — المُرسِل الحالي يبتلع الرسالة.',
            self::Webhook => 'لم يُبنَ بعد: يحتاج عنوان استقبال وسرَّ توقيع.',
        };
    }
}
