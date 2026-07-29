<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Integrations\Models\ProjectBridge;
use App\Domains\Integrations\Services\MawazinBridge;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MawazinBridgeTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://api.mawazin.test/api';

    private Project $project;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->project = ProjectFactory::default();
        $this->manager = User::factory()->role(Role::AiManager)->create();
    }

    #[Test]
    public function it_logs_in_once_and_reuses_the_token_for_every_endpoint(): void
    {
        // تسجيل دخول لكل نداء يعني أربعة تسجيلات لفتح شاشة واحدة — وموازين
        // يخنق تسجيل الدخول المتكرر.
        $this->fakeMawazin();
        $bridge = $this->bridge();

        app(MawazinBridge::class)->snapshot($bridge);

        $logins = 0;

        Http::assertSentCount(5);
        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), '/auth/login')) {
                $logins++;
            }
        }

        $this->assertSame(1, $logins);
    }

    #[Test]
    public function an_expired_token_is_refreshed_once_then_the_call_succeeds(): void
    {
        $bridge = $this->bridge();
        Cache::put("bridge:token:{$bridge->id}", 'stale-token', 600);

        $calls = 0;

        Http::fake([
            self::BASE.'/auth/login' => Http::response(['accessToken' => 'fresh-token']),
            self::BASE.'/ai/settings' => function () use (&$calls) {
                $calls++;

                return $calls === 1
                    ? Http::response(['message' => 'Unauthorized'], 401)
                    : Http::response(['provider' => 'claude', 'model' => 'sonnet']);
            },
        ]);

        $result = app(MawazinBridge::class)->get($bridge, '/ai/settings');

        $this->assertTrue($result->ok);
        $this->assertSame('claude', $result->data['provider'] ?? null);
    }

    #[Test]
    public function a_persistent_rejection_stops_instead_of_hammering_the_login(): void
    {
        // إعادة المحاولة بلا حدّ على بيانات خاطئة تقفل الحساب في موازين.
        Http::fake([
            self::BASE.'/auth/login' => Http::response(['accessToken' => 'token']),
            self::BASE.'/ai/settings' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $result = app(MawazinBridge::class)->get($this->bridge(), '/ai/settings');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('ai:read', (string) $result->error);
    }

    #[Test]
    public function one_failing_endpoint_does_not_take_the_others_down(): void
    {
        Http::fake([
            self::BASE.'/auth/login' => Http::response(['accessToken' => 'token']),
            self::BASE.'/ai/settings' => Http::response(['provider' => 'claude']),
            self::BASE.'/ai/usage-analytics*' => Http::response(['message' => 'boom'], 500),
            self::BASE.'/ai/health' => Http::response(['ok' => true]),
            self::BASE.'/ai/user-quotas' => Http::response([]),
        ]);

        $snapshot = app(MawazinBridge::class)->snapshot($this->bridge());

        $this->assertTrue($snapshot['settings']->ok);
        $this->assertFalse($snapshot['analytics']->ok);
        $this->assertTrue($snapshot['health']->ok);
    }

    #[Test]
    public function the_screen_shows_mawazin_settings_and_hirootes_derived_figures(): void
    {
        $this->fakeMawazin();
        $this->bridge();

        $this->actingAs($this->manager)
            ->get('/bridge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Bridge/Index')
                ->where('snapshot.assistant.provider', 'claude')
                ->where('snapshot.assistant.write_tools_enabled', false)
                ->where('snapshot.plans.0.plan', 'free')
                // الصفر في موازين يعني بلا سقف — والشاشة تقولها لا تعرض صفرًا.
                ->where('snapshot.plans.5.unlimited', true)
                ->where('bridge.status', 'متصل')
                ->has('snapshot.derived'));
    }

    #[Test]
    public function a_failed_fetch_is_recorded_and_the_status_stops_saying_connected(): void
    {
        // جسرٌ يقول «متصل» وآخر نجاحه أمس يجعل المشغّل يثق برقم قديم.
        Http::fake([
            self::BASE.'/auth/login' => Http::response(['accessToken' => 'token']),
            self::BASE.'/*' => Http::response(['message' => 'down'], 503),
        ]);

        $bridge = $this->bridge();
        $bridge->forceFill(['last_synced_at' => now()->subDay()])->save();

        $this->actingAs($this->manager)
            ->get('/bridge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('bridge.status', 'أخفق'));

        $this->assertNotNull($bridge->fresh()?->last_error);
    }

    #[Test]
    public function credentials_are_encrypted_at_rest_and_never_returned_to_the_browser(): void
    {
        $this->fakeMawazin();

        $this->actingAs(User::factory()->role(Role::SystemAdmin)->create())
            ->post('/bridge', [
                'base_url' => self::BASE,
                'auth_mode' => 'service_account',
                'email' => 'service@mawazin.test',
                'password' => 'super-secret-value',
                'is_enabled' => true,
            ])
            ->assertRedirect();

        $raw = (string) DB::table('project_bridges')->value('credentials');

        $this->assertStringNotContainsString('super-secret-value', $raw);
        $this->assertSame('super-secret-value', ProjectBridge::query()->firstOrFail()->secret('password'));

        // ولا في سجل التدقيق.
        $this->assertSame(0, DB::table('audit_logs')
            ->whereRaw('new_values::text LIKE ?', ['%super-secret-value%'])
            ->count());
    }

    #[Test]
    public function saving_without_a_secret_keeps_the_stored_one(): void
    {
        // من يفتح الشاشة ليغيّر العنوان وحده لا ينوي مسح كلمة المرور.
        $this->fakeMawazin();
        $this->bridge();

        $this->actingAs(User::factory()->role(Role::SystemAdmin)->create())
            ->post('/bridge', [
                'base_url' => 'https://new.mawazin.test/api',
                'auth_mode' => 'service_account',
                'is_enabled' => true,
            ])
            ->assertRedirect();

        $bridge = ProjectBridge::query()->firstOrFail();

        $this->assertSame('https://new.mawazin.test/api', $bridge->base_url);
        $this->assertSame('secret', $bridge->secret('password'));
    }

    #[Test]
    public function a_read_only_role_cannot_change_the_connection(): void
    {
        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)
            ->post('/bridge', [
                'base_url' => self::BASE,
                'auth_mode' => 'bearer',
                'token' => 'x',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function each_project_reads_its_own_bridge_only(): void
    {
        $this->fakeMawazin();
        $this->bridge();

        $other = Project::factory()->create(['slug' => 'other-bridge', 'sort_order' => 9]);
        $other->members()->attach($this->manager->id, ['role' => Role::AiManager->value]);

        $this->actingAs($this->manager)
            ->post("/projects/{$other->id}/switch")
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->get('/bridge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('bridge', null));
    }

    /**
     * إخفاق تسجيل الدخول يقول أيَّ الأسباب وقع.
     *
     * رُصد على أول ربط حيّ: أربع بطاقات تقول «تعذّر تسجيل دخول حساب الخدمة»
     * وهي نفسها للخادم المطفأ ولكلمة المرور الخاطئة ولحقل الرمز المُعاد
     * تسميته — فلا يبقى للمشغّل إلا التجريب العشوائي. الرسالة الواحدة لثلاثة
     * أسباب ليست معلومة، وهذا ما يمنع عودتها.
     */
    /**
     * حالةٌ لكل اختبار لا حلقةٌ داخل واحد: `Http::fake` يضيف ولا يستبدل، فأول
     * مطابقٍ يفوز، وحلقةٌ تجيب فيها الحالةُ الأولى عن الأربع تمرّ كذبًا.
     *
     * @param  array<string, mixed>  $body
     */
    #[Test]
    #[DataProvider('loginFailures')]
    public function each_login_failure_names_its_own_cause(
        string $expected,
        int $status,
        array $body = [],
    ): void {
        Http::fake([self::BASE.'/auth/login' => Http::response($body, $status)]);

        $result = app(MawazinBridge::class)->get($this->bridge(), '/ai/settings');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString($expected, (string) $result->error);
    }

    /**
     * @return array<string, array{string, int, 2?: array<string, mixed>}>
     */
    public static function loginFailures(): array
    {
        return [
            'بيانات مرفوضة' => ['رفض المشروع بيانات حساب الخدمة', 401],
            'مسار غير موجود' => ['لا مسار تسجيل دخول', 404],
            // رسالة المشروع تُقدَّم: هي وحدها تعرف أيُّ الحارسين قفل وكم بقي.
            'قفل الحساب' => ['حاول بعد 14 دقيقة', 429, ['message' => 'محاولات دخول كثيرة — حاول بعد 14 دقيقة']],
            'تجاوز بلا رسالة' => ['تجاوز المشروع حدَّ محاولات الدخول', 429],
            'عطل في المشروع' => ['ردّ المشروع بـ 500', 500],
            'رد بلا رمز' => ['ولم يحمل الرد رمزًا', 200, ['user' => ['id' => 1]]],
        ];
    }

    #[Test]
    public function the_unified_envelope_is_unwrapped_before_anything_reads_it(): void
    {
        // رُصد حيًّا: `TransformInterceptor` عامّ في موازين يغلّف كل رد JSON،
        // فالرمز داخل `data` وكذلك حمولات النقاط الأربع. بُني المهايئ على
        // أنواع ما تُعيده المتحكّمات لا على ما يخرج من HTTP.
        Http::fake([
            self::BASE.'/auth/login' => Http::response(['success' => true, 'data' => ['token' => 't']]),
            self::BASE.'/ai/settings' => Http::response(['success' => true, 'data' => ['provider' => 'claude']]),
        ]);

        $result = app(MawazinBridge::class)->get($this->bridge(), '/ai/settings');

        $this->assertTrue($result->ok);
        $this->assertSame('claude', $result->data['provider'] ?? null);
        $this->assertArrayNotHasKey('success', $result->data);
    }

    #[Test]
    public function a_payload_that_merely_carries_a_data_key_is_left_whole(): void
    {
        // الفكّ يشترط `success` معه: حمولةٌ حقلُها اسمه `data` مصادفةً تُسلَّم
        // كما هي، وإلا ابتلع الفكُّ بقيةَ حقولها بلا أن يشكو أحد.
        Http::fake([
            self::BASE.'/auth/login' => Http::response(['token' => 't']),
            self::BASE.'/ai/settings' => Http::response(['data' => ['x' => 1], 'since' => '2026-06-29']),
        ]);

        $result = app(MawazinBridge::class)->get($this->bridge(), '/ai/settings');

        $this->assertTrue($result->ok);
        $this->assertSame('2026-06-29', $result->data['since'] ?? null);
    }

    #[Test]
    public function a_rejected_login_is_attempted_once_for_the_whole_snapshot(): void
    {
        // رُصد حيًّا: أربع نقاط تعني أربع محاولات دخول حين يُرفض الدخول، وموازين
        // يخنق عند ٣٠ في الدقيقة — فبضع فتحاتٍ للشاشة تستنفد الحدّ، ثم يقرأ
        // المشغّل ٤٢٩ فيظنّ العطل في بياناته لا في عدد محاولاتنا.
        Http::fake([self::BASE.'/auth/login' => Http::response([], 401)]);

        app(MawazinBridge::class)->snapshot($this->bridge());

        Http::assertSentCount(1);
    }

    #[Test]
    public function an_unreachable_project_names_the_address_it_tried(): void
    {
        // العنوان في نصّ الخطأ: نسيان البادئة `/api` أو خطأ المنفذ أشيع من
        // عطل الخادم، ولا يُرى إلا إذا قيل ما نُودي فعلًا.
        Http::fake(fn () => throw new ConnectionException('connection refused'));

        $result = app(MawazinBridge::class)->get($this->bridge(), '/ai/settings');

        $this->assertFalse($result->ok);
        $this->assertStringContainsString(self::BASE.'/auth/login', (string) $result->error);
    }

    private function bridge(): ProjectBridge
    {
        return ProjectBridge::query()->create([
            'project_id' => $this->project->id,
            'driver' => 'mawazin',
            'base_url' => self::BASE,
            'auth_mode' => ProjectBridge::MODE_SERVICE_ACCOUNT,
            'credentials' => ['email' => 'service@mawazin.test', 'password' => 'secret'],
            'is_enabled' => true,
        ]);
    }

    /**
     * موازين الحقيقي كما رُصد حيًّا: كل رد JSON مغلَّف بـ {success, data} عبر
     * `TransformInterceptor` عامّ. والمُقلَّد الذي لا يغلّف يجعل الاختبار يمرّ
     * على عقدٍ لا وجود له.
     */
    private function fakeMawazin(): void
    {
        Http::fake([
            self::BASE.'/auth/login' => $this->wrapped(['token' => 'token']),
            self::BASE.'/ai/settings' => $this->wrapped([
                'provider' => 'claude',
                'model' => 'claude-sonnet',
                'assistantProvider' => '',
                'assistantLevel' => 2,
                'allowUserLevelOverride' => true,
                'assistantToolsEnabled' => true,
                'assistantWriteToolsEnabled' => false,
                'embeddingsEnabled' => false,
                'docMaxReads' => 12,
                'levelTokens' => [1 => 800, 2 => 1600, 3 => 3200],
                'planQuotas' => [
                    'free' => ['maxTokensPerRequest' => 1024, 'dailyTokens' => 20000, 'weeklyTokens' => 80000, 'monthlyTokens' => 200000],
                    'bronze' => ['maxTokensPerRequest' => 2048, 'dailyTokens' => 60000, 'weeklyTokens' => 300000, 'monthlyTokens' => 900000],
                    'silver' => ['maxTokensPerRequest' => 2048, 'dailyTokens' => 120000, 'weeklyTokens' => 600000, 'monthlyTokens' => 1800000],
                    'gold' => ['maxTokensPerRequest' => 4096, 'dailyTokens' => 300000, 'weeklyTokens' => 1500000, 'monthlyTokens' => 4500000],
                    'platinum' => ['maxTokensPerRequest' => 4096, 'dailyTokens' => 700000, 'weeklyTokens' => 3500000, 'monthlyTokens' => 10000000],
                    'diamond' => ['maxTokensPerRequest' => 4096, 'dailyTokens' => 0, 'weeklyTokens' => 0, 'monthlyTokens' => 0],
                ],
            ]),
            self::BASE.'/ai/usage-analytics*' => $this->wrapped([
                'since' => '2026-06-29',
                'totalCostUsd' => 42.5,
                'byKind' => [['kind' => 'assistant', 'count' => 120, 'tokens' => 900000, 'points' => 30]],
                'daily' => [
                    ['date' => '2026-07-28', 'tokens' => 400000, 'scans' => 10, 'points' => 12],
                    ['date' => '2026-07-29', 'tokens' => 500000, 'scans' => 14, 'points' => 18],
                ],
                'byProviderModel' => [],
                'topUsers' => [['userId' => 'u1', 'tokens' => 500000, 'events' => 60, 'points' => 20]],
                'cache' => ['readTokens' => 10, 'creationTokens' => 5, 'hitRate' => 0.42],
            ]),
            self::BASE.'/ai/health' => $this->wrapped(['status' => 'ok']),
            self::BASE.'/ai/user-quotas' => $this->wrapped([]),
        ]);
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function wrapped(array $payload): PromiseInterface
    {
        return Http::response(['success' => true, 'data' => $payload]);
    }
}
