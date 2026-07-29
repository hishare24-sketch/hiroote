<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Projects\Models\Project;
use App\Support\Http\RequestId;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يتحقق من مفتاح المشروع ويثبّت المشروع الذي ينتمي إليه الطلب.
 *
 * المشروع يأتي من المفتاح لا من الطلب: لو أرسله العميل لصار بإمكانه سؤال هاي
 * روت عن مشروع ليس له بمجرّد تبديل رقم. المفتاح **هو** المشروع.
 *
 * ورسالة الرفض واحدة لكل الأسباب — مفتاح غير موجود، مُبطَل، منتهٍ — عمدًا:
 * تمييزها يخبر من يجرّب المفاتيح أيُّها كان صحيحًا يومًا.
 */
class AuthenticateProjectApiKey
{
    public const PROJECT = 'hiroote.api.project';

    public const KEY = 'hiroote.api.key';

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->token($request);

        if ($token === null) {
            return $this->deny('api_key_missing', 'مفتاح الوصول مطلوب.');
        }

        $key = ProjectApiKey::query()
            ->where('hash', hash('sha256', $token))
            ->with('project')
            ->first();

        if ($key === null || ! $key->isUsable()) {
            return $this->deny('api_key_invalid', 'مفتاح الوصول غير صالح.');
        }

        $project = $key->project;

        if (! $project instanceof Project || ! $project->is_active) {
            return $this->deny('project_inactive', 'المشروع غير مفعّل.');
        }

        // كتابةٌ واحدة لكل طلب مقبول: عمود يقول متى استُخدم المفتاح آخر مرة هو
        // ما يجعل إبطال المفاتيح المهجورة قرارًا مبنيًّا على واقع.
        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set(self::PROJECT, $project);
        $request->attributes->set(self::KEY, $key);

        return $next($request);
    }

    private function token(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if (is_string($header) && str_starts_with($header, 'Bearer ')) {
            return mb_substr($header, 7);
        }

        return null;
    }

    private function deny(string $code, string $message): Response
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'request_id' => RequestId::current(),
            ],
        ], 401);
    }
}
