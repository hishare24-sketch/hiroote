<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * تحويل enum إلى العقد `EnumRef` في `resources/js/types/conversations.ts`.
 */
final class EnumPayload
{
    /**
     * @return array{value: string, label: string, tone: string}
     */
    public static function from(PresentableEnum $enum): array
    {
        return [
            'value' => (string) $enum->value,
            'label' => $enum->label(),
            'tone' => $enum->tone(),
        ];
    }

    /**
     * @return array{value: string, label: string, tone: string}|null
     */
    public static function fromNullable(?PresentableEnum $enum): ?array
    {
        return $enum === null ? null : self::from($enum);
    }
}
