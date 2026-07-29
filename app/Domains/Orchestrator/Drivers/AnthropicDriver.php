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
 * مهايئ Anthropic — واجهة الرسائل.
 *
 * ⚠️ **مكتوبٌ على العقد المنشور ولم يُجرَّب على مفتاح حقيقي بعد.** أول تشغيل
 * حيّ قد يكشف فرقًا في تسمية حقلٍ أو شكل خطأ، كما كشفه أول ربط بموازين. لا
 * تُزل هذه الملاحظة قبل نجاح نداءٍ فعلي.
 */
class AnthropicDriver implements AiDriver
{
    private const VERSION = '2023-06-01';

    private const TIMEOUT = 60;

    public function slug(): string
    {
        return 'anthropic';
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
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::VERSION,
            ])
                ->acceptJson()
                ->timeout(self::TIMEOUT)
                ->post(rtrim($provider->base_url, '/').'/v1/messages', [
                    'model' => $model->name,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                    'system' => $system,
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $exception) {
            return DriverReply::failure('تعذّر الوصول إلى المزود: '.$exception->getMessage());
        } catch (Throwable $exception) {
            return DriverReply::failure('خطأ غير متوقع: '.$exception->getMessage());
        }

        $elapsed = (int) (microtime(true) * 1000) - $started;

        if ($response->failed()) {
            $body = $response->json();
            $message = is_array($body) && is_array($body['error'] ?? null)
                ? (string) ($body['error']['message'] ?? '')
                : '';

            return DriverReply::failure(
                trim("ردّ المزود بـ {$response->status()}. {$message}"),
                $elapsed,
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            return DriverReply::failure('ردّ المزود بجسم غير متوقع.', $elapsed);
        }

        $text = $this->text($body);

        if ($text === null) {
            return DriverReply::failure('ردّ المزود بلا نصّ صالح.', $elapsed);
        }

        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];

        return DriverReply::success(
            $text,
            $this->intOrNull($usage['input_tokens'] ?? null),
            $this->intOrNull($usage['output_tokens'] ?? null),
            $elapsed,
            is_string($body['stop_reason'] ?? null) ? $body['stop_reason'] : null,
        );
    }

    /** @param array<array-key, mixed> $body */
    private function text(array $body): ?string
    {
        $blocks = $body['content'] ?? null;

        if (! is_array($blocks)) {
            return null;
        }

        $parts = [];

        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        return $parts === [] ? null : implode("\n", $parts);
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
