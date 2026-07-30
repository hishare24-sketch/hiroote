<?php

declare(strict_types=1);

use App\Console\Commands\BackupDatabase;
use App\Console\Commands\PrunePulses;
use App\Domains\Alerts\Jobs\EvaluateProjectAlerts;
use App\Domains\Providers\Jobs\RunProviderHealthChecks;
use Illuminate\Support\Facades\Schedule;

// الفحص الذاتي الدوري — الافتراضي كل ساعة، قابل للضبط عبر
// AI_HEALTH_CHECK_INTERVAL_MINUTES (وثيقة التصميم §9).
$interval = config()->integer('hiroote.health_check.interval_minutes', 60);

Schedule::job(new RunProviderHealthChecks)
    ->cron($interval >= 60 ? '0 * * * *' : "*/{$interval} * * * *");

// تقييم قواعد التنبيه — دوريًّا لا بزرّ (وثيقة 08 §4.1).
// `withoutOverlapping` لأن التقييم يقرأ كل مشروع: تشغيلان متداخلان يضاعفان
// الاستعلامات وقد يفتحان الحدث نفسه مرتين.
$alerts = config()->integer('hiroote.alerts.evaluation_interval_minutes', 15);

Schedule::job(new EvaluateProjectAlerts)
    ->cron($alerts >= 60 ? '0 * * * *' : "*/{$alerts} * * * *")
    ->withoutOverlapping();

/*
 * نسخة يومية من قاعدة البيانات — ٣:٣٠ فجرًا، أهدأ ساعة.
 *
 * تحمي من خطأٍ في التطبيق (هجرةٌ أتلفت، حذفٌ بالخطأ)، **لا من ضياع الخادم**
 * فهي عليه. و`withoutOverlapping` لأن نسخةً تبدأ قبل أن تنتهي سابقتها تتنازعان
 * على الملف نفسه.
 */
Schedule::command(BackupDatabase::class)
    ->dailyAt('03:30')
    ->withoutOverlapping();

/*
 * كنس النبض المنتهية مدّته — ٤:١٠ فجرًا، بعد النسخة لا قبلها.
 *
 * الترتيب مقصود: نسخةُ اليوم تُؤخذ وفيها ما سيُحذف بعد أربعين دقيقة، فيبقى
 * للمحذوف أثرٌ يومًا واحدًا على الأقل. والعكس يحذف ثم ينسخ فلا يبقى شيء.
 */
Schedule::command(PrunePulses::class)
    ->dailyAt('04:10')
    ->withoutOverlapping();
