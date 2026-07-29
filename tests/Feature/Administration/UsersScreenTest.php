<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsersScreenTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
        $this->admin = User::factory()->role(Role::SystemAdmin)->create();
    }

    #[Test]
    public function the_matrix_is_read_from_the_role_map_not_written_beside_it(): void
    {
        // مصفوفةٌ تُنسخ يدويًّا تتقادم بأول صلاحية جديدة، فتَعِد القارئ بما لا
        // تفعله البوابة. هذا الاختبار يقارن الصفّ المعروض بالمصدر نفسه.
        $page = $this->actingAs($this->admin)->get('/users')->assertOk();

        /** @var array{props: array{matrix: list<array{permission: string, roles: array<string, bool>}>}} $props */
        $props = $page->viewData('page');
        $matrix = $props['props']['matrix'];

        $this->assertCount(count(Permission::cases()), $matrix);

        foreach ($matrix as $row) {
            $permission = Permission::from($row['permission']);

            foreach (Role::cases() as $role) {
                $this->assertSame(
                    $role->grants($permission),
                    $row['roles'][$role->value],
                    "المصفوفة تخالف Role::permissions() في {$row['permission']} / {$role->value}.",
                );
            }
        }
    }

    #[Test]
    public function a_created_account_holds_no_permission_until_it_joins_a_project(): void
    {
        // الدور بلا عضوية لا صلاحية له (ADR-0003 §3) — والشاشة تقولها، وهذا
        // يثبتها: منحُ دورٍ ثم انتظارُ أثرٍ لا يأتي يُقرأ عطلًا في البوابة.
        $this->actingAs($this->admin)
            ->post('/users', [
                'name' => 'مشغّل جديد',
                'email' => 'new@hiroote.test',
                'role' => Role::AiManager->value,
                'password' => 'kalima-sirriya',
            ])
            ->assertRedirect();

        $created = User::query()->where('email', 'new@hiroote.test')->firstOrFail();

        $this->assertTrue($created->is_active);
        $this->assertSame(Role::AiManager, $created->role);
        $this->assertFalse($created->hasPermission(Permission::ProvidersManage, $this->project));

        $this->actingAs($this->admin)
            ->post("/users/{$created->id}/memberships", [
                'project_id' => $this->project->id,
                'role' => Role::AiManager->value,
            ])
            ->assertRedirect();

        $this->assertTrue($created->fresh()?->hasPermission(Permission::ProvidersManage, $this->project));
    }

    #[Test]
    public function a_password_never_reaches_the_audit_trail(): void
    {
        $this->actingAs($this->admin)
            ->post('/users', [
                'name' => 'مشغّل',
                'email' => 'secret@hiroote.test',
                'role' => Role::SupportAgent->value,
                'password' => 'super-secret-value',
            ])
            ->assertRedirect();

        $this->assertSame(0, DB::table('audit_logs')
            ->whereRaw('new_values::text LIKE ?', ['%super-secret-value%'])
            ->count());

        // والحدث نفسه مسجَّل — الغياب هنا يعني ثغرة في الأثر لا صيانةً للسرّ.
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'users.create')->count());
    }

    #[Test]
    public function editing_without_a_password_keeps_the_stored_one(): void
    {
        // من يصحّح اسمًا لا ينوي إخراج صاحبه من حسابه.
        $target = User::factory()->role(Role::SupportAgent)->create(['name' => 'قديم']);
        $before = $target->password;

        $this->actingAs($this->admin)
            ->put("/users/{$target->id}", [
                'name' => 'جديد',
                'email' => $target->email,
                'role' => Role::SupportAgent->value,
            ])
            ->assertRedirect();

        $fresh = $target->fresh();

        $this->assertSame('جديد', $fresh?->name);
        $this->assertSame($before, $fresh?->password);
    }

    #[Test]
    public function deactivating_strips_every_permission_and_keeps_the_history(): void
    {
        $target = User::factory()->role(Role::AiManager)->create();

        $this->assertTrue($target->hasPermission(Permission::ProvidersManage, $this->project));

        $this->actingAs($this->admin)
            ->post("/users/{$target->id}/active", ['is_active' => false])
            ->assertRedirect();

        $fresh = $target->fresh();

        $this->assertFalse($fresh?->hasPermission(Permission::ProvidersManage, $this->project));
        // التاريخ باقٍ: سحبُ الوصول لا يمحو الأثر الذي يشرح ما فعله صاحبه.
        $this->assertNotNull($fresh);
        $this->assertSame(1, User::query()->where('id', $target->id)->count());
    }

    #[Test]
    public function you_cannot_lock_yourself_out(): void
    {
        // من يعطّل نفسه لا يملك بعدها صلاحية إعادة التفعيل.
        $this->actingAs($this->admin)
            ->post("/users/{$this->admin->id}/active", ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($this->admin->fresh()?->is_active);
    }

    #[Test]
    public function the_last_platform_admin_cannot_be_deactivated(): void
    {
        // منصةٌ بلا مدير فعّال لا يفتحها إلا تعديلٌ في قاعدة البيانات.
        $owner = User::factory()->platformAdmin()->role(Role::SystemAdmin)->create();
        $second = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($second)
            ->post("/users/{$owner->id}/active", ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($owner->fresh()?->is_active);
    }

    #[Test]
    public function a_read_only_auditor_sees_the_matrix_and_changes_nothing(): void
    {
        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)
            ->get('/users')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', false));

        $this->actingAs($auditor)
            ->post('/users', [
                'name' => 'محاولة',
                'email' => 'nope@hiroote.test',
                'role' => Role::SystemAdmin->value,
                'password' => 'kalima-sirriya',
            ])
            ->assertForbidden();

        $this->assertSame(0, User::query()->where('email', 'nope@hiroote.test')->count());
    }
}
