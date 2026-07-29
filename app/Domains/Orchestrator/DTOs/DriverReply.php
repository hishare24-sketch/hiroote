<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\DTOs;

/**
 * ما يعيده مزودٌ بعينه — نصًّا ورموزًا وزمنًا، أو سببَ إخفاقه.
 *
 * الرموز تأتي **من المزود لا تُقدَّر**: تقديرُ عددها يجعل التكلفة تقديرًا
 * يُقرأ فاتورة. وحين لا يرسلها المزود تبقى null ولا تُملأ بصفر.
 */
final readonly class DriverReply
{
    private function __construct(
        public bool $ok,
        public string $text,
        public ?int $inputTokens,
        public ?int $outputTokens,
        public int $latencyMs,
        public ?string $error,
        public ?string $stopReason = null,
    ) {}

    public static function success(
        string $text,
        ?int $inputTokens,
        ?int $outputTokens,
        int $latencyMs,
        ?string $stopReason = null,
    ): self {
        return new self(true, $text, $inputTokens, $outputTokens, $latencyMs, null, $stopReason);
    }

    public static function failure(string $error, int $latencyMs = 0): self
    {
        return new self(false, '', null, null, $latencyMs, $error);
    }

    public function totalTokens(): int
    {
        return ($this->inputTokens ?? 0) + ($this->outputTokens ?? 0);
    }

    /** هل يحمل الردّ محاسبةً كاملة؟ نصفها لا يصلح لفاتورة. */
    public function isMetered(): bool
    {
        return $this->inputTokens !== null && $this->outputTokens !== null;
    }
}
