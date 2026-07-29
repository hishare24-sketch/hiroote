<?php

declare(strict_types=1);

namespace Tests\Feature\Assistants;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Enums\AssistantFunction;
use App\Domains\Assistants\Models\AssistantFunctionSetting;
use App\Domains\Assistants\Models\AssistantLevelSetting;
use App\Domains\Assistants\Models\AssistantProfile;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssistantSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
    }

    #[Test]
    public function opening_the_screen_provisions_the_four_levels(): void
    {
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)
            ->get('/assistants')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Assistants/Index')
                ->has('levels', 4)
                ->has('functions', 14)
                ->where('levels.0.key.value', 'direct')
                ->where('levels.3.key.value', 'expert'));

        $this->assertSame(4, AssistantLevelSetting::query()->forProject($this->project)->count());
    }

    #[Test]
    public function levels_belong_to_their_project_alone(): void
    {
        $other = Project::factory()->create(['slug' => 'other', 'sort_order' => 2]);

        $manager = User::factory()->role(Role::AiManager)->platformAdmin()->create();
        $this->actingAs($manager)->get('/assistants')->assertOk();

        $this->actingAs($manager)->post("/projects/{$other->id}/switch");
        $this->actingAs($manager)->get('/assistants')->assertOk();

        // أربعة لكل مشروع لا أربعة مشتركة.
        $this->assertSame(4, AssistantLevelSetting::query()->forProject($this->project)->count());
        $this->assertSame(4, AssistantLevelSetting::query()->forProject($other)->count());

        $mine = AssistantLevelSetting::query()->forProject($this->project)->first();
        $this->assertNotNull($mine);

        $this->actingAs($manager)
            ->put("/assistants/levels/{$mine->id}", $this->levelPayload(['label' => 'مباشر جدًا']))
            ->assertNotFound();
    }

    #[Test]
    public function editing_a_level_is_saved_and_audited(): void
    {
        $manager = User::factory()->role(Role::AiManager)->create();
        $this->actingAs($manager)->get('/assistants');

        $level = AssistantLevelSetting::query()->forProject($this->project)->firstOrFail();

        $this->actingAs($manager)
            ->put("/assistants/levels/{$level->id}", $this->levelPayload([
                'label' => 'مباشر جدًا',
                'token_limit' => 900,
            ]))
            ->assertRedirect();

        $level->refresh();
        $this->assertSame('مباشر جدًا', $level->label);
        $this->assertSame(900, $level->token_limit);

        $this->assertDatabaseHas('audit_logs', ['action' => 'assistants.level_update']);
    }

    #[Test]
    public function a_read_only_role_cannot_edit(): void
    {
        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        // يرى الشاشة…
        $this->actingAs($auditor)->get('/assistants')->assertOk();

        $level = AssistantLevelSetting::query()->forProject($this->project)->firstOrFail();

        // …ولا يحرّرها.
        $this->actingAs($auditor)
            ->put("/assistants/levels/{$level->id}", $this->levelPayload())
            ->assertForbidden();

        $this->actingAs($auditor)
            ->post('/assistants/functions', ['key' => 'summarize', 'enabled' => false])
            ->assertForbidden();
    }

    #[Test]
    public function toggling_a_function_switches_off_what_depends_on_it(): void
    {
        $manager = User::factory()->role(Role::AiManager)->create();
        $this->actingAs($manager)->get('/assistants');

        // تحليل المرفقات يعتمد على قراءة الملفات.
        $this->actingAs($manager)
            ->post('/assistants/functions', [
                'key' => AssistantFunction::ReadFiles->value,
                'enabled' => true,
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->post('/assistants/functions', [
                'key' => AssistantFunction::AnalyzeAttachments->value,
                'enabled' => true,
            ]);

        $this->actingAs($manager)
            ->post('/assistants/functions', [
                'key' => AssistantFunction::ReadFiles->value,
                'enabled' => false,
            ]);

        $map = AssistantFunctionSetting::mapFor($this->project);

        $this->assertFalse($map[AssistantFunction::ReadFiles->value]);
        $this->assertFalse($map[AssistantFunction::AnalyzeAttachments->value]);
    }

    /**
     * لا وظيفةَ مُعلَنة بلا مسار.
     *
     * كانت `ChatZoom` تُرفض بـ٤٢٢ لأنها وعدٌ بلا تنفيذ؛ وقد بُنيت حوكمتها في
     * هذه اللوحة فصارت تُفعَّل. والحارس في المتحكّم يبقى للوظيفة التالية التي
     * تُعلَن قبل أن تُبنى — **تفعيلٌ قبل التنفيذ يَعِد بسلوك لا وجود له**.
     */
    #[Test]
    public function no_declared_function_is_offered_before_it_has_a_path(): void
    {
        foreach (AssistantFunction::cases() as $function) {
            $this->assertFalse(
                $function->awaitsImplementation(),
                "«{$function->label()}» معروضة في الشاشة وبلا مسار.",
            );
        }
    }

    #[Test]
    public function the_chat_capability_can_be_switched_on_now_that_its_governance_exists(): void
    {
        $manager = User::factory()->role(Role::AiManager)->create();
        $this->actingAs($manager)->get('/assistants');

        $this->actingAs($manager)
            ->post('/assistants/functions', [
                'key' => AssistantFunction::ChatZoom->value,
                'enabled' => true,
            ])
            ->assertRedirect();

        $this->assertTrue(AssistantFunctionSetting::mapFor($this->project)[AssistantFunction::ChatZoom->value]);
    }

    #[Test]
    public function the_user_control_profile_is_saved_and_audited(): void
    {
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)
            ->put('/assistants/profile', [
                'default_level' => 'expert',
                'allow_level_change' => false,
                'level_scope' => 'conversation',
                'availability' => 'role',
                'availability_key' => 'مشترك مميز',
            ])
            ->assertRedirect();

        $profile = AssistantProfile::forProject($this->project);

        $this->assertSame('expert', $profile->default_level->value);
        $this->assertFalse($profile->allow_level_change);
        $this->assertSame('مشترك مميز', $profile->availability_key);

        $this->assertTrue(
            AuditLog::query()->where('action', 'assistants.profile_update')->exists(),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function levelPayload(array $overrides = []): array
    {
        return [
            'label' => 'مباشر وموجز',
            'description' => 'جواب قصير.',
            'response_length' => 'جملة',
            'token_limit' => 600,
            'intelligence' => 2,
            'initiative' => 1,
            'creativity' => 10,
            'detail' => 1,
            'formality' => 3,
            'reads_attachments' => false,
            'calls_data' => true,
            'executes_actions' => false,
            'confidence_threshold' => 80,
            'model_id' => null,
            'expected_cost' => 0.09,
            'is_available' => true,
            ...$overrides,
        ];
    }
}
