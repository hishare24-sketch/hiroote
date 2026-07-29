<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * أول إقلاع على قاعدة إنتاج — أو لا شيء.
 *
 * قاعدةٌ مهاجَرة بلا مستخدم لوحةٌ لا يدخلها أحد. والبذرة التطويرية لا تصلح
 * بديلًا: تزرع ستة حسابات بكلمة مرور معروفة وحركةً تجريبية تُقرأ حقيقيةً في
 * اللوحة.
 */
class BootstrapPlatformTest extends TestCase
{
    use RefreshDatabase;

    private const OPTIONS = [
        '--name' => 'المالك',
        '--email' => 'owner@hiroote.example',
        '--password' => 'كلمة-مرور-طويلة-كفاية',
    ];

    #[Test]
    public function the_first_admin_is_a_member_of_every_project(): void
    {
        // «مدير منصة» عضويةٌ بدور مدير النظام في كل مشروع لا استثناءٌ من
        // البوابة (CLAUDE.md رقم ٣) — فمنحُها في واحدٍ يترك حاملها أعمى عن
        // البقية بينما تسمّيه الشاشة مدير منصة.
        Project::factory()->create(['slug' => 'second', 'sort_order' => 2]);

        $this->artisan('hiroote:bootstrap', self::OPTIONS)->assertSuccessful();

        $user = User::query()->firstOrFail();
        $this->assertSame(Role::SystemAdmin, $user->role);
        $this->assertTrue($user->is_platform_admin);
        $this->assertTrue(Hash::check('كلمة-مرور-طويلة-كفاية', $user->password));

        $projects = Project::query()->get();
        $this->assertGreaterThan(1, $projects->count());

        foreach ($projects as $project) {
            $this->assertSame(
                Role::SystemAdmin,
                $project->membershipRoleFor($user),
                "لا عضوية في «{$project->name}» — يدخل ولا يرى شيئًا.",
            );
        }
    }

    #[Test]
    public function it_does_not_add_a_second_project_beside_the_migrated_one(): void
    {
        // الهجرة تُنشئ مشروعًا افتراضيًّا؛ وإنشاء ثانٍ يترك مشروعًا فارغًا
        // يلتبس بالعامل في كل قائمة ومبدّل.
        $before = Project::query()->count();
        $this->assertGreaterThan(0, $before, 'الهجرة لم تُنشئ مشروعًا — افتراض هذا الأمر ساقط.');

        $this->artisan('hiroote:bootstrap', self::OPTIONS)->assertSuccessful();

        $this->assertSame($before, Project::query()->count());
    }

    #[Test]
    public function it_refuses_on_a_database_that_already_has_accounts(): void
    {
        // أمرٌ يُشغَّل مرتين سهوًا يجب ألّا يمنح أحدًا صلاحية جديدة صامتًا.
        User::factory()->role(Role::SupportAgent)->create();

        $this->artisan('hiroote:bootstrap', self::OPTIONS)->assertFailed();

        $this->assertSame(1, User::query()->count());
    }

    #[Test]
    public function a_short_password_is_refused_and_no_account_is_created(): void
    {
        // الحساب الأول يفتح كل مشروع وكل مفتاح في اللوحة.
        $this->artisan('hiroote:bootstrap', [...self::OPTIONS, '--password' => 'قصيرة'])
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    #[Test]
    public function nothing_survives_a_failure_midway(): void
    {
        // حالةٌ نصفُ مكتملة تُغلق الباب: الأمر يرفض العمل ثانيةً لوجود حساب،
        // فيبقى المالك خارج لوحته بلا مخرج.
        $this->artisan('hiroote:bootstrap', [...self::OPTIONS, '--email' => 'ليس بريدًا'])
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
