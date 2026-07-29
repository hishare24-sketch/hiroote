<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Actions;

use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Alerts\Models\ProjectWebhook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * يدفع التنبيه إلى وجهة المشروع، موقَّعًا.
 *
 * **التوقيع والطابع الزمني معًا**: التوقيع وحده يُثبت أن الجسم من هاي روت ولا
 * يمنع إعادة بثّ دفعةٍ قديمة بحذافيرها. والطابع داخل النصّ الموقَّع لا خارجه —
 * طابعٌ خارج التوقيع يُبدَّل بلا أن يبطل التوقيع.
 */
final readonly class SendAlertWebhook
{
    private const TIMEOUT = 8;

    /** @return array{ok: bool, error: string|null} */
    public function handle(ProjectWebhook $webhook, AlertEvent $event, AlertRule $rule): array
    {
        $payload = [
            'event' => 'alert.opened',
            'id' => $event->getAttribute('ulid'),
            'severity' => $event->severity->value,
            'rule' => ['id' => $rule->id, 'name' => $rule->name],
            'metric' => $event->metric->value,
            'observed' => $event->observed_value,
            'threshold' => $event->threshold,
            'window_minutes' => $event->window_minutes,
            'triggered_at' => $event->triggered_at->toIso8601String(),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Hiroote-Timestamp' => $timestamp,
                'X-Hiroote-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $webhook->secret),
            ])
                ->timeout(self::TIMEOUT)
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (ConnectionException $exception) {
            return $this->record($webhook, 'تعذّر الوصول إلى وجهة المشروع: '.$exception->getMessage());
        } catch (Throwable $exception) {
            return $this->record($webhook, 'خطأ غير متوقع: '.$exception->getMessage());
        }

        if ($response->failed()) {
            return $this->record($webhook, "ردّت الوجهة بـ {$response->status()}.");
        }

        $webhook->forceFill([
            'last_delivered_at' => now(),
            'last_error' => null,
            'last_error_at' => null,
        ])->save();

        return ['ok' => true, 'error' => null];
    }

    /** @return array{ok: false, error: string} */
    private function record(ProjectWebhook $webhook, string $error): array
    {
        // الإخفاق يُحفظ على الوجهة نفسها: شاشةٌ تقول «تعمل» ووجهةٌ ساقطة منذ
        // أمس تُقنع المشغّل بأن إنذاره يصل.
        $webhook->forceFill([
            'last_error' => mb_substr($error, 0, 500),
            'last_error_at' => now(),
        ])->save();

        return ['ok' => false, 'error' => $error];
    }
}
