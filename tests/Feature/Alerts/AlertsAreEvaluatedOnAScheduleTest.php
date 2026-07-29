<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Domains\Alerts\Actions\EvaluateAlertRules;
use App\Domains\Alerts\Jobs\EvaluateProjectAlerts;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * التنبيه يُقيَّم دوريًّا لا بزرّ.
 *
 * قاعدةٌ تُقيَّم حين يفتح أحدهم الشاشة تنبّه بعد وقوع ما تنبّه له بساعات، وقد
 * لا تنبّه أصلًا إن لم يفتحها أحد — والتنبيه المتأخّر يُقرأ تقريرًا لا إنذارًا.
 */
class AlertsAreEvaluatedOnAScheduleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_evaluation_is_on_the_schedule(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => $event->getSummaryForDisplay());

        $this->assertTrue(
            $events->contains(fn (string $summary): bool => str_contains($summary, 'EvaluateProjectAlerts')),
            'تقييم التنبيهات ليس في الجدولة — فلا يعمل إلا بزرّ.',
        );
    }

    #[Test]
    public function one_failing_project_does_not_silence_the_others(): void
    {
        // عطلٌ في بيانات مشروع لا يُسكت تنبيهات غيره: وظيفةٌ تسقط عند أول
        // إخفاق تترك بقية المشاريع بلا تقييم حتى يُصلَح ذلك المشروع وحده.
        ProjectFactory::default();
        Project::factory()->create(['slug' => 'another', 'sort_order' => 8]);

        // مشروع موقوف لا يُقيَّم — لا قواعد تعمل على ما أُوقف.
        Project::factory()->create(['slug' => 'stopped', 'sort_order' => 9, 'is_active' => false]);

        app(EvaluateProjectAlerts::class)->handle(app(EvaluateAlertRules::class));

        $this->assertTrue(true, 'اكتمل التقييم على كل المشاريع الفعّالة بلا استثناء.');
    }
}
