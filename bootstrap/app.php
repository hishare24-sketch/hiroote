<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\AuthenticateProjectApiKey;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveCurrentProject;
use App\Support\Http\ApiErrorResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs first so a failure anywhere later still carries a request id.
        $middleware->prepend(AssignRequestId::class);

        $middleware->web(append: [
            // قبل Inertia: الصلاحيات المشاركة مع الواجهة تعتمد على المشروع النشط.
            ResolveCurrentProject::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);

        /*
         * مصادقة المفتاح تسبق الخنق.
         *
         * لارافل يرتّب وسائط المسار بأولويّته لا بترتيب كتابتها، و`ThrottleRequests`
         * في قائمته سلفًا فيسبق أي وسيط مخصّص. فكان محدِّد `api-bridge` يقرأ
         * المفتاح ولا يجده — إذ لم يُصادَق بعد — فيسقط إلى **٢٠ طلبًا لكل عنوان
         * شبكة** بدل ١٢٠ لكل مفتاح. أي أن مشروعًا خلف بوابة واحدة يخنقه أنشطُ
         * مستخدميه، وهو بالضبط ما وُضع الحدُّ على المفتاح لتفاديه.
         *
         * كشفه اختبارٌ يسأل عن ٢٣ شاشة في نداء واحد فسقط عند الحادي والعشرين.
         */
        $middleware->prependToPriorityList(ThrottleRequests::class, AuthenticateProjectApiKey::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiErrorResponse::fromThrowable($e, config('app.debug') === true);
            }

            return null;
        });
    })->create();
