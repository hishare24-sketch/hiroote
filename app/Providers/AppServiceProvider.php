<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Projects\Services\CurrentProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
    }
}
