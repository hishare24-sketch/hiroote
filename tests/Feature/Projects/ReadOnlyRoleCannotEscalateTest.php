<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * تصعيد الصلاحية عبر العضوية.
 *
 * `project.manage` يُفرض في المتحكم لا في الـ middleware — المسارات كلها خلف
 * `project.view`. الحماية قائمة، لكنها كانت بلا اختبار: من ينقل الحارس يومًا
 * من المتحكم إلى الـ middleware، أو يضيف مسارًا جديدًا للعضوية، لن يجد ما
 * يوقفه. إضافة عضو فعلٌ يمنح الوصول، وتركه بلا اختبار أخطر من تركه بلا توثيق.
 */
class ReadOnlyRoleCannotEscalateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_read_only_role_cannot_grant_itself_membership(): void
    {
        $project = ProjectFactory::default();
        $auditor = User::factory()->role(Role::SecurityAuditor)->create();
        $victim = User::factory()->role(Role::SupportAgent)->create();

        $this->actingAs($auditor)->post("/projects/{$project->id}/members", [
            'user_id' => $victim->id,
            'role' => Role::SystemAdmin->value,
        ])->assertForbidden();

        $this->actingAs($auditor)->put("/projects/{$project->id}", [
            'name' => 'مختطف', 'slug' => 'hijacked', 'is_active' => true, 'sort_order' => 1,
        ])->assertForbidden();

        $this->actingAs($auditor)->post('/projects', [
            'name' => 'مشروع جديد', 'slug' => 'new-one', 'is_active' => true, 'sort_order' => 5,
        ])->assertForbidden();
    }
}
