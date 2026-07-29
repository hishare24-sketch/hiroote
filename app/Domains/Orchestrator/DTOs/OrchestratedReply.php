<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\DTOs;

use App\Domains\Conversations\Models\Conversation;

/**
 * نتيجة نداءٍ مرّ بالطبقة كاملة — نصًّا ومحاسبةً وأثرًا.
 *
 * `cost` قد تكون null: نموذجٌ بلا تسعير في اللوحة لا تُحسب كلفته صفرًا، فصفرٌ
 * في الفاتورة يُقرأ «مجاني» لا «غير مُسعَّر».
 */
final readonly class OrchestratedReply
{
    private function __construct(
        public bool $ok,
        public string $text,
        public ?string $error,
        public ?int $inputTokens,
        public ?int $outputTokens,
        public ?float $cost,
        public int $latencyMs,
        public ?string $provider,
        public ?string $model,
        public ?Conversation $conversation,
        public bool $failedOver = false,
    ) {}

    public static function ok(
        string $text,
        ?int $inputTokens,
        ?int $outputTokens,
        ?float $cost,
        int $latencyMs,
        string $provider,
        string $model,
        ?Conversation $conversation,
        bool $failedOver = false,
    ): self {
        return new self(
            true, $text, null, $inputTokens, $outputTokens, $cost,
            $latencyMs, $provider, $model, $conversation, $failedOver,
        );
    }

    public static function failed(string $error, ?string $provider = null, int $latencyMs = 0): self
    {
        return new self(false, '', $error, null, null, null, $latencyMs, $provider, null, null);
    }

    public function totalTokens(): int
    {
        return ($this->inputTokens ?? 0) + ($this->outputTokens ?? 0);
    }
}
