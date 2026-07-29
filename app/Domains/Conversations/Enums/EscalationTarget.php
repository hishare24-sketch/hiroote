<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * مسارات التحويل الثلاثة المفصولة بصريًا — وثيقة التصميم §10.
 */
enum EscalationTarget: string implements PresentableEnum
{
    case SpecialistAssistant = 'specialist_assistant';
    case HumanAgent = 'human_agent';
    case Ticket = 'ticket';

    public function label(): string
    {
        return match ($this) {
            self::SpecialistAssistant => 'مساعد متخصص',
            self::HumanAgent => 'موظف بشري',
            self::Ticket => 'تذكرة',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SpecialistAssistant => 'accent',
            self::HumanAgent => 'info',
            self::Ticket => 'warning',
        };
    }
}
