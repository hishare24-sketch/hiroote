<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * الإجراء التلقائي عند التفعيل — وثيقة 06 §11.
 *
 * ما عدا «الإشعار فقط» ينتظر طبقة الـ Orchestrator: تعطيل قسم أو تحويل مزود
 * فعلٌ على الإنتاج، وتنفيذه من هنا يتجاوز الحدود التي وضعتها وثيقة 02 §4.
 * الخيار يُحفظ في القاعدة ويُعرض في الحدث بوصفه نيّة معلنة لا تنفيذًا تمّ.
 */
enum AlertAction: string implements PresentableEnum
{
    case NotifyOnly = 'notify_only';
    case EscalateToHuman = 'escalate_to_human';
    case PauseSection = 'pause_section';
    case FailoverProvider = 'failover_provider';
    case RaiseAssistantLevel = 'raise_assistant_level';

    public function label(): string
    {
        return match ($this) {
            self::NotifyOnly => 'إشعار فقط',
            self::EscalateToHuman => 'تحويل المحادثات الجديدة إلى بشري',
            self::PauseSection => 'إيقاف المساعد في الأقسام المشمولة',
            self::FailoverProvider => 'التحويل إلى المزود التالي',
            self::RaiseAssistantLevel => 'رفع مستوى المساعد',
        };
    }

    public function tone(): string
    {
        return $this === self::NotifyOnly ? 'neutral' : 'warning';
    }

    /** هل ينتظر هذا الإجراء طبقة التنفيذ. */
    public function awaitsImplementation(): bool
    {
        return $this !== self::NotifyOnly;
    }
}
