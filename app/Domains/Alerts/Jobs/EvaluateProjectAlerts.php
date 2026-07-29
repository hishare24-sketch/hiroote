<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Jobs;

use App\Domains\Alerts\Actions\EvaluateAlertRules;
use App\Domains\Projects\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * تقييم قواعد التنبيه لكل مشروع — دوريًّا لا بزرّ.
 *
 * قاعدةٌ تُقيَّم حين يفتح أحدهم الشاشة تنبّه بعد وقوع ما تنبّه له بساعات، وقد
 * لا تنبّه أصلًا إن لم يفتحها أحد. والتنبيه الذي يصل متأخّرًا يُقرأ تقريرًا لا
 * إنذارًا.
 *
 * ومشروعٌ يُخفق تقييمه لا يوقف البقية: عطلٌ في بيانات مشروع لا يُسكت تنبيهات
 * غيره.
 */
class EvaluateProjectAlerts implements ShouldQueue
{
    use Queueable;

    public function handle(EvaluateAlertRules $evaluate): void
    {
        foreach (Project::query()->where('is_active', true)->cursor() as $project) {
            try {
                $evaluate->handle($project);
            } catch (Throwable $exception) {
                Log::warning('تعذّر تقييم تنبيهات المشروع', [
                    'project' => $project->slug,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
