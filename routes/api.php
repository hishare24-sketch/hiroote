<?php

declare(strict_types=1);

use App\Domains\Integrations\Http\Api\ConversationIntakeController;
use App\Domains\Integrations\Http\Api\FeedbackIntakeController;
use App\Domains\Integrations\Http\Api\ScreenContextController;
use App\Http\Middleware\AuthenticateProjectApiKey;
use Illuminate\Support\Facades\Route;

/*
 * جسر المشاريع — وثيقة 02 §5.
 *
 * لا جلسة ولا كوكيز: كل طلب يحمل مفتاح مشروعه، والمشروع يُشتقّ من المفتاح لا
 * من جسم الطلب. الحدّ على المفتاح لا على عنوان الشبكة: مشروعٌ خلف بوابة واحدة
 * يبدو كعميل واحد، فيخنق حدُّ العنوان مستخدميه جميعًا بسبب أنشطهم.
 */
Route::prefix('api/v1')
    ->middleware([AuthenticateProjectApiKey::class, 'throttle:api-bridge'])
    ->group(function (): void {
        Route::get('/context', ScreenContextController::class)->name('api.context');
        Route::post('/conversations', ConversationIntakeController::class)->name('api.conversations');
        Route::post('/feedback', FeedbackIntakeController::class)->name('api.feedback');
    });
