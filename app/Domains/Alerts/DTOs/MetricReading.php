<?php

declare(strict_types=1);

namespace App\Domains\Alerts\DTOs;

/**
 * قراءة واحدة لمؤشر.
 *
 * `value === null` تعني «لا يمكن القياس» لا «صفر». نسبة تصعيد على صفر محادثة
 * ليست ٠٪ بل مجهولة، ولو عوملت صفرًا لأطلقت كل قاعدة «أقل من» إنذارًا كاذبًا
 * كلما هدأت الحركة — وهو أسوأ وقت لإغراق المشغّل بإنذارات لا سبب لها.
 */
final readonly class MetricReading
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public ?float $value,
        public int $sampleSize,
        public string $sampleLabel,
        public array $context = [],
    ) {}

    public static function unavailable(string $reason): self
    {
        return new self(null, 0, $reason);
    }

    public function isMeasurable(): bool
    {
        return $this->value !== null;
    }
}
