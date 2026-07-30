<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * المخرج الوحيد من الإغلاق.
 *
 * شاشة المستخدمين تحتاج دخولًا، ولا بريدَ استعادة حتى يُضبط مُرسِل، ولا حسابَ
 * ثانٍ في أول إقلاع — فمالكٌ نسي كلمة مروره يبقى خارج لوحته ولا شيء يفتحها له.
 */
class ResetOperatorPasswordTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_prints_the_new_password_alone_so_it_can_be_redirected(): void
    {
        // من ينسخ سرًّا من شاشة يتركه في سجلّ الطرفية وفي كل صورة تُؤخذ بعده.
        // فالسرّ وحده على المخرج القياسي كي يُحوَّل إلى ملف بلا أن يمرّ بالعين.
        $user = User::factory()->role(Role::SystemAdmin)->create(['email' => 'owner@hiroote.example']);

        // `Artisan::call` لا `$this->artisan`: الثاني يؤجّل التنفيذ ولا يملأ
        // مخزن المخرج، فيُقرأ الفراغ نجاحًا.
        $this->assertSame(0, Artisan::call('hiroote:reset-password', ['email' => 'owner@hiroote.example']));

        $printed = trim(explode("\n", Artisan::output())[0]);

        $this->assertGreaterThanOrEqual(12, mb_strlen($printed));
        $this->assertTrue(
            Hash::check($printed, (string) $user->fresh()?->password),
            'المطبوع ليس كلمة المرور المحفوظة — فمن يأخذه لا يدخل به.',
        );
    }

    #[Test]
    public function the_reset_is_audited_without_the_secret(): void
    {
        User::factory()->role(Role::SystemAdmin)->create(['email' => 'owner@hiroote.example']);

        $this->artisan('hiroote:reset-password', [
            'email' => 'owner@hiroote.example',
            '--password' => 'كلمة-مرور-طويلة-كفاية',
        ])->assertSuccessful();

        $entry = AuditLog::query()->where('action', 'users.password_reset')->firstOrFail();

        // الحدث يُسجَّل ولا تُسجَّل قيمته: قيدٌ يحمل السرّ يجعل سجلًّا
        // append-only مخزنًا دائمًا لكلمات المرور.
        $this->assertStringNotContainsString(
            'كلمة-مرور-طويلة-كفاية',
            json_encode($entry->new_values, JSON_UNESCAPED_UNICODE) ?: '',
        );
    }

    #[Test]
    public function a_short_password_is_refused(): void
    {
        User::factory()->role(Role::SystemAdmin)->create(['email' => 'owner@hiroote.example']);

        $this->artisan('hiroote:reset-password', [
            'email' => 'owner@hiroote.example',
            '--password' => 'قصيرة',
        ])->assertFailed();
    }

    #[Test]
    public function an_unknown_email_lists_the_ones_that_exist(): void
    {
        // «لا حساب بهذا البريد» وحدها تترك من أخطأ حرفًا يجرّب صيغًا عشوائية.
        User::factory()->role(Role::SystemAdmin)->create(['email' => 'owner@hiroote.example']);

        $this->artisan('hiroote:reset-password', ['email' => 'wrong@hiroote.example'])
            ->expectsOutputToContain('owner@hiroote.example')
            ->assertFailed();
    }

    #[Test]
    public function a_disabled_account_is_only_activated_when_asked(): void
    {
        // حسابٌ معطّل يقبل كلمة المرور ثم يُرفض برسالةٍ أخرى — فيظنّ المشغّل
        // أن إعادة الضبط أخفقت وهي نجحت.
        $user = User::factory()->role(Role::SystemAdmin)->create([
            'email' => 'owner@hiroote.example',
            'is_active' => false,
        ]);

        $this->artisan('hiroote:reset-password', ['email' => 'owner@hiroote.example'])
            ->assertSuccessful();
        $this->assertFalse((bool) $user->fresh()?->is_active);

        $this->artisan('hiroote:reset-password', [
            'email' => 'owner@hiroote.example',
            '--activate' => true,
        ])->assertSuccessful();
        $this->assertTrue((bool) $user->fresh()?->is_active);
    }
}
