<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * مشروع موازين في الإنتاج — بمعرّفه لا باسمه.
 *
 * إنشاؤه من الشاشة يعطيه معرّفًا عربيًّا («موازين»)، وبذور الأقسام والشاشات
 * تبحث عن `mawazin` — فيُنشأ فارغًا، ويردّ جسر الوارد ٤٠٤ على كل مفتاح شاشة،
 * بلا أن يقول أحدٌ إن السبب معرّفٌ لا يطابق.
 */
class SetupMawazinProjectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_the_project_with_the_slug_the_seeders_expect(): void
    {
        $this->artisan('hiroote:setup-mawazin')->assertSuccessful();

        $project = Project::query()->where('slug', 'mawazin')->firstOrFail();

        $this->assertSame('موازين', $project->name);
        $this->assertTrue($project->is_active);
    }

    #[Test]
    public function the_sections_and_screens_arrive_with_it(): void
    {
        // مشروعٌ بلا شاشات يجعل جسر الوارد يردّ ٤٠٤ على كل نداء — وهو خطأٌ
        // صحيح يصف مفتاحًا غير مسجَّل، فلا يدلّ على أن التهيئة نصفُ تامّة.
        $this->artisan('hiroote:setup-mawazin')->assertSuccessful();

        $project = Project::query()->where('slug', 'mawazin')->firstOrFail();

        $this->assertSame(5, ProjectSection::query()->where('project_id', $project->id)->count());
        $this->assertSame(23, KnowledgeScreen::query()->where('project_id', $project->id)->count());

        // ومفتاحُ الشاشة هو اسم مسار موازين حرفيًّا — لا اشتقاقًا من الاسم.
        $this->assertTrue(
            KnowledgeScreen::query()
                ->where('project_id', $project->id)
                ->where('key', 'finance-page')
                ->exists(),
        );
    }

    #[Test]
    public function platform_admins_become_members_of_it(): void
    {
        // «مدير منصة» عضويةٌ في كل مشروع لا استثناءٌ من البوابة (CLAUDE.md رقم ٣)
        // — ومشروعٌ جديد بلا إلحاقهم يجعله يظهر في القائمة ولا يُرى داخله شيء.
        $admin = User::factory()->role(Role::SystemAdmin)->create(['is_platform_admin' => true]);
        $other = User::factory()->role(Role::SupportAgent)->create(['is_platform_admin' => false]);

        $this->artisan('hiroote:setup-mawazin')->assertSuccessful();

        $project = Project::query()->where('slug', 'mawazin')->firstOrFail();

        $this->assertSame(Role::SystemAdmin, $project->membershipRoleFor($admin));
        $this->assertNull($project->membershipRoleFor($other));
    }

    #[Test]
    public function running_it_twice_does_not_duplicate_anything(): void
    {
        $this->artisan('hiroote:setup-mawazin')->assertSuccessful();
        $this->artisan('hiroote:setup-mawazin')->assertSuccessful();

        $this->assertSame(1, Project::query()->where('slug', 'mawazin')->count());

        $project = Project::query()->where('slug', 'mawazin')->firstOrFail();
        $this->assertSame(23, KnowledgeScreen::query()->where('project_id', $project->id)->count());
    }
}
