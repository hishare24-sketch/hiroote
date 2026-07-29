<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\DTOs\BridgeResult;
use App\Domains\Integrations\Models\ProjectBridge;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * قارئ إعدادات موازين وإحصاءاته — قراءة فقط.
 *
 * نقاط الذكاء في موازين محميّة بـ JwtAuthGuard + AdminGuard بنطاقَي `ai:read`
 * و`health:read`، أي أنها تنتظر رمز أدمن لا مفتاح آلة. فيسجّل هذا المهايئ دخول
 * **حساب خدمة** مقصور على القراءة، ويحتفظ برمزه في الكاش، ويجدّده مرة واحدة
 * عند أول ٤٠١ ثم يستسلم — إعادة المحاولة بلا حدّ على بيانات خاطئة تقفل الحساب
 * في موازين بدل أن تُصلح شيئًا.
 *
 * ولا دالة كتابة هنا عمدًا: القراءة فقط قرارُ المرحلة، وغياب الدالة يجعله
 * قرارًا في البنية لا وعدًا في التوثيق.
 */
class MawazinBridge
{
    private const TIMEOUT = 8;

    /** الرمز يعيش أقصر من صلاحيته الفعلية حتى لا يُستعمل وهو ينتهي في الطريق. */
    private const TOKEN_TTL = 1500;

    /**
     * كل ما تعرضه الشاشة، وكل نداء مستقل عن جاره.
     *
     * @return array<string, BridgeResult>
     */
    public function snapshot(ProjectBridge $bridge): array
    {
        return [
            'settings' => $this->get($bridge, '/ai/settings'),
            'analytics' => $this->get($bridge, '/ai/usage-analytics', ['days' => 30]),
            'health' => $this->get($bridge, '/ai/health'),
            'quotas' => $this->get($bridge, '/ai/user-quotas'),
        ];
    }

    /** @param array<string, mixed> $query */
    public function get(ProjectBridge $bridge, string $path, array $query = []): BridgeResult
    {
        if (! $bridge->isConfigured()) {
            return BridgeResult::failure('الجسر غير مكتمل الإعداد.');
        }

        $token = $this->token($bridge);

        if ($token === null) {
            return BridgeResult::failure('تعذّر تسجيل دخول حساب الخدمة.');
        }

        $result = $this->call($bridge, $path, $query, $token);

        // ٤٠١ بعد رمز مقبول يعني انتهاءه لا خطأ البيانات: يُمسح ويُجدَّد مرة.
        if ($result->error === 'unauthorized') {
            Cache::forget($this->cacheKey($bridge));
            $fresh = $this->token($bridge);

            if ($fresh === null) {
                return BridgeResult::failure('انتهى رمز حساب الخدمة وتعذّر تجديده.');
            }

            $result = $this->call($bridge, $path, $query, $fresh);
        }

        return $result->error === 'unauthorized'
            ? BridgeResult::failure('رُفض الوصول: تأكد من صلاحية ai:read لحساب الخدمة.')
            : $result;
    }

    /** @param array<string, mixed> $query */
    private function call(ProjectBridge $bridge, string $path, array $query, string $token): BridgeResult
    {
        $started = (int) (microtime(true) * 1000);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(self::TIMEOUT)
                ->get($this->url($bridge, $path), $query);
        } catch (ConnectionException $exception) {
            return BridgeResult::failure('تعذّر الوصول إلى الخادم: '.$exception->getMessage());
        } catch (Throwable $exception) {
            return BridgeResult::failure('خطأ غير متوقع: '.$exception->getMessage());
        }

        $elapsed = (int) (microtime(true) * 1000) - $started;

        if ($response->status() === 401 || $response->status() === 403) {
            return BridgeResult::failure('unauthorized', $elapsed);
        }

        if ($response->failed()) {
            return BridgeResult::failure("رد الخادم بـ {$response->status()}.", $elapsed);
        }

        $data = $response->json();

        return is_array($data)
            ? BridgeResult::success($data, $elapsed)
            : BridgeResult::failure('رد الخادم بجسم غير متوقع.', $elapsed);
    }

    private function token(ProjectBridge $bridge): ?string
    {
        if ($bridge->auth_mode === ProjectBridge::MODE_BEARER) {
            return $bridge->secret('token');
        }

        $cached = Cache::get($this->cacheKey($bridge));

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->login($bridge);

        if ($token !== null) {
            Cache::put($this->cacheKey($bridge), $token, self::TOKEN_TTL);
        }

        return $token;
    }

    private function login(ProjectBridge $bridge): ?string
    {
        try {
            $response = Http::acceptJson()
                ->timeout(self::TIMEOUT)
                ->post($this->url($bridge, '/auth/login'), [
                    'email' => $bridge->secret('email'),
                    'password' => $bridge->secret('password'),
                ]);
        } catch (Throwable) {
            return null;
        }

        if ($response->failed()) {
            return null;
        }

        $body = $response->json();

        if (! is_array($body)) {
            return null;
        }

        // موازين قد يسمّيه `accessToken` أو `access_token` أو `token`؛ نقبلها
        // كلها بدل أن نكسر عند إعادة تسمية لا تغيّر المعنى.
        foreach (['accessToken', 'access_token', 'token'] as $field) {
            $value = $body[$field] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function url(ProjectBridge $bridge, string $path): string
    {
        return rtrim($bridge->base_url, '/').'/'.ltrim($path, '/');
    }

    private function cacheKey(ProjectBridge $bridge): string
    {
        return "bridge:token:{$bridge->id}";
    }
}
