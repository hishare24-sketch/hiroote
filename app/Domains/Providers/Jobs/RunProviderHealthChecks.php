<?php

declare(strict_types=1);

namespace App\Domains\Providers\Jobs;

use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Services\ProviderHealthService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * يفحص كل المزودين المفعلين — يعمل على طابور health-checks (وثيقة 02 §9).
 */
class RunProviderHealthChecks implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('health-checks');
    }

    public function handle(ProviderHealthService $health): void
    {
        AiProvider::query()
            ->enabled()
            ->orderBy('priority')
            ->get()
            ->each(fn (AiProvider $provider) => $health->check($provider));
    }
}
