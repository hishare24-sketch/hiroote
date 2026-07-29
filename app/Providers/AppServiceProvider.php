<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Middleware\AuthenticateProjectApiKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped لا singleton: المشروع النشط يخص طلبًا واحدًا، وعامل الطابور
        // الذي يعيش عبر عدة مهام يجب ألا يرث مشروع المهمة السابقة.
        $this->app->scoped(CurrentProject::class);
    }

    public function boot(): void
    {
        // Strict mode outside production: lazy loading, missing attributes and
        // silently discarded mass-assignment become failures during
        // development instead of production surprises (وثيقة 03 §1).
        Model::shouldBeStrict(! $this->app->isProduction());

        if (! $this->app->isProduction()) {
            DB::prohibitDestructiveCommands(false);
        } else {
            DB::prohibitDestructiveCommands();
        }

        // حدُّ جسر المشاريع على المفتاح لا على العنوان: مشروعٌ خلف بوابة واحدة
        // يبدو كعميل واحد، فيخنق حدُّ العنوان مستخدميه جميعًا بسبب أنشطهم.
        RateLimiter::for('api-bridge', function (Request $request): Limit {
            $key = $request->attributes->get(AuthenticateProjectApiKey::KEY);

            return $key instanceof ProjectApiKey
                ? Limit::perMinute(120)->by('api-key:'.$key->id)
                : Limit::perMinute(20)->by((string) $request->ip());
        });
    }
}
