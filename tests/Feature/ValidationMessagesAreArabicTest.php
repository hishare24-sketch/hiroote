<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * لوحةٌ عربية لا تردّ بالإنجليزية.
 *
 * كان `lang/ar/` غير موجود، فترجع لارافل إلى رسائلها الأصلية: يقرأ المشغّل
 * «The name field is required» تحت حقلٍ عنوانه «الاسم». والأسوأ منها اسمُ
 * العمود الخام — «حقل base_url مطلوب» تحت حقلٍ عنوانه «عنوان الواجهة» يجعل
 * القارئ يبحث عن حقلٍ لا وجود له في الشاشة.
 */
class ValidationMessagesAreArabicTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_default_locale_is_arabic(): void
    {
        $this->assertSame('ar', config('app.locale'));
    }

    #[Test]
    public function a_failed_rule_speaks_arabic(): void
    {
        $errors = Validator::make(
            ['email' => 'ليس بريدًا', 'name' => ''],
            ['email' => 'required|email', 'name' => 'required|string|min:3'],
        )->errors()->all();

        foreach ($errors as $message) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(field|must|required|invalid|The)\b/',
                $message,
                "رسالة إنجليزية تسرّبت: {$message}",
            );
        }
    }

    #[Test]
    public function a_field_is_named_as_the_screen_names_it(): void
    {
        // اسم العمود الخام يجعل القارئ يبحث عن حقلٍ لا وجود له في الشاشة.
        ProjectFactory::default();

        $this->actingAs(User::factory()->role(Role::SystemAdmin)->create())
            ->post('/bridge', ['auth_mode' => 'service_account'])
            ->assertSessionHasErrors('base_url');

        $errors = session('errors');
        $this->assertNotNull($errors);

        $message = (string) $errors->first('base_url');
        $this->assertStringContainsString('عنوان الواجهة', $message);
        $this->assertStringNotContainsString('base_url', $message);
    }
}
