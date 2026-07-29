<?php

declare(strict_types=1);

use App\Domains\Providers\Jobs\RunProviderHealthChecks;
use Illuminate\Support\Facades\Schedule;

// الفحص الذاتي الدوري — الافتراضي كل ساعة، قابل للضبط عبر
// AI_HEALTH_CHECK_INTERVAL_MINUTES (وثيقة التصميم §9).
$interval = config()->integer('hiroote.health_check.interval_minutes', 60);

Schedule::job(new RunProviderHealthChecks)
    ->cron($interval >= 60 ? '0 * * * *' : "*/{$interval} * * * *");
