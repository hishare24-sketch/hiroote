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
     * نتيجة تسجيل الدخول لهذا الطلب — نجاحًا كانت أو إخفاقًا.
     *
     * الإخفاق يُحفظ كما يُحفظ النجاح: بدونه تعيد كل نقطة المحاولةَ من جديد،
     * فتصير فتحةُ شاشة واحدة أربعَ محاولات دخول. وموازين يخنق تسجيل الدخول
     * عند ثلاثين محاولة في الدقيقة، فبضع محاولاتٍ من المشغّل تكفي لاستنفاده
     * ثم يقرأ ٤٢٩ فيظنّ العطل في بياناته.
     *
     * @var array<int, array{token: string|null, error: string}>
     */
    private array $logins = [];

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

        $auth = $this->token($bridge);

        if ($auth['token'] === null) {
            return BridgeResult::failure($auth['error']);
        }

        $result = $this->call($bridge, $path, $query, $auth['token']);

        // ٤٠١ بعد رمز مقبول يعني انتهاءه لا خطأ البيانات: يُمسح ويُجدَّد مرة.
        if ($result->error === 'unauthorized') {
            $this->forgetToken($bridge);
            $fresh = $this->token($bridge);

            if ($fresh['token'] === null) {
                return BridgeResult::failure('انتهى رمز حساب الخدمة وتعذّر تجديده: '.$fresh['error']);
            }

            $result = $this->call($bridge, $path, $query, $fresh['token']);
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

    /**
     * الرمز، أو سبب تعذّره.
     *
     * السبب يُحمل معه لا يُبتلع: «تعذّر تسجيل الدخول» وحدها تصف الخادمَ
     * المطفأ وكلمةَ المرور الخاطئة وحقلَ الرمز المُعاد تسميته بنفس العبارة،
     * فيبدأ المشغّل بتجربة الثلاثة عشوائيًّا.
     *
     * @return array{token: string|null, error: string}
     */
    private function token(ProjectBridge $bridge): array
    {
        if ($bridge->auth_mode === ProjectBridge::MODE_BEARER) {
            $token = $bridge->secret('token');

            return [
                'token' => $token,
                'error' => $token === null ? 'لا رمز محفوظ لهذا الجسر.' : '',
            ];
        }

        $cached = Cache::get($this->cacheKey($bridge));

        if (is_string($cached) && $cached !== '') {
            return ['token' => $cached, 'error' => ''];
        }

        if (array_key_exists($bridge->id, $this->logins)) {
            return $this->logins[$bridge->id];
        }

        $outcome = $this->login($bridge);
        $this->logins[$bridge->id] = $outcome;

        if ($outcome['token'] !== null) {
            Cache::put($this->cacheKey($bridge), $outcome['token'], self::TOKEN_TTL);
        }

        return $outcome;
    }

    /** يُنسي الرمز المحفوظ ونتيجةَ الدخول معًا، فيُسمح بمحاولة واحدة جديدة. */
    private function forgetToken(ProjectBridge $bridge): void
    {
        Cache::forget($this->cacheKey($bridge));
        unset($this->logins[$bridge->id]);
    }

    /**
     * @return array{token: string|null, error: string}
     */
    private function login(ProjectBridge $bridge): array
    {
        $url = $this->url($bridge, '/auth/login');

        try {
            $response = Http::acceptJson()
                ->timeout(self::TIMEOUT)
                ->post($url, [
                    'email' => $bridge->secret('email'),
                    'password' => $bridge->secret('password'),
                ]);
        } catch (ConnectionException) {
            // العنوان يُذكر في النص: خطأ المنفذ أو نسيان `/api` أشيع من عطل
            // الخادم، ولا يُرى إلا إذا قيل ما نُودي فعلًا.
            return $this->failedLogin("تعذّر الوصول إلى {$url} — تأكد أن المشروع يعمل وأن العنوان صحيح.");
        } catch (Throwable $exception) {
            return $this->failedLogin('خطأ غير متوقع عند تسجيل الدخول: '.$exception->getMessage());
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return $this->failedLogin('رفض المشروع بيانات حساب الخدمة — راجع البريد وكلمة المرور.');
        }

        if ($response->status() === 429) {
            return $this->failedLogin('تجاوز المشروع حدَّ محاولات الدخول — انتظر دقيقة ثم أعد الجلب.');
        }

        if ($response->status() === 404) {
            return $this->failedLogin("لا مسار تسجيل دخول على {$url} — غالبًا ينقص العنوانَ بادئته (مثل /api).");
        }

        if ($response->failed()) {
            return $this->failedLogin("ردّ المشروع بـ {$response->status()} على تسجيل الدخول.");
        }

        $body = $response->json();

        if (! is_array($body)) {
            return $this->failedLogin('ردّ تسجيل الدخول بجسم غير متوقع.');
        }

        // موازين قد يسمّيه `accessToken` أو `access_token` أو `token`؛ نقبلها
        // كلها بدل أن نكسر عند إعادة تسمية لا تغيّر المعنى.
        foreach (['accessToken', 'access_token', 'token'] as $field) {
            $value = $body[$field] ?? null;

            if (is_string($value) && $value !== '') {
                return ['token' => $value, 'error' => ''];
            }
        }

        // نجح الدخول وما وجدنا الرمز: عيبٌ في التسمية لا في البيانات، ويُقال
        // كذلك حتى لا يُطارَد في كلمة المرور بلا طائل.
        return $this->failedLogin(
            'قُبل الدخول ولم يحمل الرد رمزًا بأي من الأسماء المعروفة ('
            .implode(' · ', array_keys($body)).').',
        );
    }

    /** @return array{token: null, error: string} */
    private function failedLogin(string $reason): array
    {
        return ['token' => null, 'error' => $reason];
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
