<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domains\Analytics\Models\ProjectPulse;
use App\Domains\Analytics\Services\PulseReport;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use App\Support\Http\Period;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * النبض اليومي — والقاعدة التي تحرسها هذه الاختبارات واحدة:
 * **غير المقاس ليس صفرًا، والغائب ليس هبوطًا.**
 *
 * خطأٌ في هذه النهاية لا يظهر: محادثةٌ ضاعت تُلاحَظ، ورقمٌ يومي خاطئ يدخل الرسم
 * البياني ويُبنى عليه قرارٌ بعد شهور.
 */
class PulseIntakeTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();

        $section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المالية',
            'slug' => 'finance',
        ]);

        foreach (['finance-page', 'invoices-page'] as $key) {
            KnowledgeScreen::query()->create([
                'project_id' => $this->project->id,
                'section_id' => $section->id,
                'key' => $key,
                'name' => $key,
            ]);
        }

        $minted = ProjectApiKey::mint();

        ProjectApiKey::query()->create([
            'project_id' => $this->project->id,
            'name' => 'مفتاح اختبار',
            'prefix' => $minted['prefix'],
            'hash' => $minted['hash'],
        ]);

        $this->token = $minted['token'];
    }

    #[Test]
    public function a_daily_batch_is_stored_with_its_screens(): void
    {
        $this->send([
            'active_users' => 120,
            'sessions' => 300,
            'section_actions' => ['المالية' => 40],
            'packages' => [['name' => 'الأساسية', 'subscribers' => 7]],
            'health' => ['uptime_percent' => 99.4],
            'screens' => [
                ['key' => 'finance-page', 'views' => 900, 'clicks' => 45],
            ],
        ])->assertCreated()->assertJsonPath('revision', 1);

        $pulse = ProjectPulse::query()->firstOrFail();

        $this->assertSame(120, $pulse->active_users);
        // لم يُرسَل، فلا يُخزَّن صفرًا: `default(0)` كان سيمحو الفرق إلى الأبد.
        $this->assertNull($pulse->logins);
        $this->assertSame(1, $pulse->screens()->count());
    }

    #[Test]
    public function an_unmeasured_metric_is_absent_from_the_average_not_counted_as_zero(): void
    {
        // ثلاثة أيام: النشِطون في اثنين فقط.
        $this->send(['active_users' => 100], '-3 days');
        $this->send(['active_users' => 200], '-2 days');
        $this->send(['sessions' => 50], '-1 day');

        $metrics = $this->report()->metrics();

        // ١٥٠ لا ١٠٠: اليوم الذي لم يُقَس فيه لا يجرّ المتوسّط إلى الصفر.
        $this->assertSame(150.0, $metrics['active_users']['average']);
        $this->assertSame(2, $metrics['active_users']['measured_days']);

        // ومقياسٌ لم يُرسَل قط ليس صفرًا — هو مجهول.
        $this->assertNull($metrics['storage_megabytes']['average']);
        $this->assertSame(0, $metrics['storage_megabytes']['measured_days']);
    }

    #[Test]
    public function a_missing_day_is_a_gap_not_a_zero_point(): void
    {
        $this->send(['active_users' => 100], '-5 days');
        $this->send(['active_users' => 120], '-1 day');

        $report = $this->report();

        // نقطتان لا ستّ: الفجوة لا تُرسم صفرًا ولا تُستكمل بالجوار — الأول
        // يكذب، والثاني يخترع.
        $this->assertCount(2, $report->series('active_users'));
        $this->assertGreaterThan(0, $report->coverage()['missing']);
    }

    #[Test]
    public function a_partial_day_is_marked_so_its_dip_is_not_read_as_a_drop(): void
    {
        $this->send(['active_users' => 30, 'final' => false]);

        $this->assertSame(1, $this->report()->coverage()['partial']);
    }

    #[Test]
    public function resending_a_day_replaces_it_and_keeps_what_it_replaced(): void
    {
        $this->send(['active_users' => 30, 'final' => false])->assertCreated();
        $this->send(['active_users' => 210])->assertOk()->assertJsonPath('revision', 2);

        $pulse = ProjectPulse::query()->firstOrFail();

        $this->assertSame(210, $pulse->active_users);
        $this->assertTrue($pulse->is_final);
        $this->assertSame(1, ProjectPulse::query()->count());

        // والقيمة المُزاحة محفوظة: بلا هذا يتغيّر رسمٌ لشهرٍ مضى ولا يعرف أحدٌ
        // أنه كان غير ذلك.
        $superseded = $pulse->revisions()->firstOrFail();

        $this->assertSame(1, $superseded->revision);
        $this->assertSame(30, $superseded->superseded_values['active_users']);
    }

    #[Test]
    public function an_unknown_screen_key_is_reported_and_does_not_drop_the_batch(): void
    {
        $this->send([
            'active_users' => 10,
            'screens' => [
                ['key' => 'finance-page', 'views' => 5],
                ['key' => 'ghost-page', 'views' => 900],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('screens_accepted', 1)
            ->assertJsonPath('screens_ignored.0', 'ghost-page');

        $this->assertSame(1, ProjectPulse::query()->firstOrFail()->screens()->count());
    }

    #[Test]
    public function identity_fields_are_refused_with_a_reason(): void
    {
        $this->send(['active_users' => 10, 'user_label' => 'محمد'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'identity_not_accepted');

        $this->assertSame(0, ProjectPulse::query()->count());
    }

    #[Test]
    public function a_day_that_has_not_arrived_yet_is_refused(): void
    {
        $this->send(['active_users' => 10], '+2 days')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'date_in_future');
    }

    #[Test]
    public function a_click_rate_without_views_is_undefined_not_zero(): void
    {
        $this->send([
            'screens' => [['key' => 'finance-page', 'clicks' => 3]],
        ])->assertCreated();

        $screens = $this->report()->screens();

        $this->assertSame(3, $screens[0]['clicks']);
        $this->assertNull($screens[0]['views']);
        $this->assertNull($screens[0]['click_rate']);
    }

    /** @param array<string, mixed> $payload */
    private function send(array $payload, string $when = 'today'): TestResponse
    {
        $date = $when === 'today'
            ? Carbon::now('Asia/Riyadh')
            : Carbon::parse($when, 'Asia/Riyadh');

        return $this->withToken($this->token)->postJson('/api/v1/pulse', [
            'date' => $date->toDateString(),
            'timezone' => 'Asia/Riyadh',
            ...$payload,
        ]);
    }

    private function report(): PulseReport
    {
        return new PulseReport(
            Period::fromRequest(Request::create('/pulse', 'GET', ['period' => 'month'])),
            $this->project,
        );
    }
}
