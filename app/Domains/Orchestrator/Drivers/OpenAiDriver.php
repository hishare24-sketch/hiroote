<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\Drivers;

use App\Domains\Orchestrator\Contracts\AiDriver;
use App\Domains\Orchestrator\DTOs\DriverReply;
use App\Domains\Providers\Models\AiModel;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * مهايئ OpenAI — واجهة إتمام المحادثة.
 *
 * ⚠️ **مكتوبٌ على العقد المنشور ولم يُجرَّب على مفتاح حقيقي بعد** — كما في
 * مهايئ Anthropic.
 */
class OpenAiDriver implements AiDriver
{
    private const TIMEOUT = 60;

    public function slug(): string
    {
        return 'openai';
    }

    /** @param list<array{role: string, content: string}> $messages */
    public function complete(
        AiProvider $provider,
        AiModel $model,
        string $apiKey,
        string $system,
        array $messages,
        int $maxTokens,
        float $temperature,
    ): DriverReply {
        $started = (int) (microtime(true) * 1000);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(self::TIMEOUT)
                ->post(rtrim($provider->base_url, '/').'/v1/chat/completions', [
                    'model' => $model->name,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ...$messages,
                    ],
                ]);
        } catch (ConnectionException $exception) {
            return DriverReply::failure('تعذّر الوصول إلى المزود: '.$exception->getMessage());
        } catch (Throwable $exception) {
            return DriverReply::failure('خطأ غير متوقع: '.$exception->getMessage());
        }

        $elapsed = (int) (microtime(true) * 1000) - $started;

        if ($response->failed()) {
            return DriverReply::failure("ردّ المزود بـ {$response->status()}.", $elapsed);
        }

        $body = $response->json();

        if (! is_array($body)) {
            return DriverReply::failure('ردّ المزود بجسم غير متوقع.', $elapsed);
        }

        $choices = $body['choices'] ?? null;
        $text = is_array($choices) && is_array($choices[0] ?? null) && is_array($choices[0]['message'] ?? null)
            ? $choices[0]['message']['content'] ?? null
            : null;

        if (! is_string($text) || $text === '') {
            return DriverReply::failure('ردّ المزود بلا نصّ صالح.', $elapsed);
        }

        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];

        return DriverReply::success(
            $text,
            $this->intOrNull($usage['prompt_tokens'] ?? null),
            $this->intOrNull($usage['completion_tokens'] ?? null),
            $elapsed,
            is_string($choices[0]['finish_reason'] ?? null) ? $choices[0]['finish_reason'] : null,
        );
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
