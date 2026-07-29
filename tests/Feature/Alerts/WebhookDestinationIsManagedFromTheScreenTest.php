<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Models\ProjectWebhook;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * قناةٌ تُضبط من الشاشة لا من قاعدة البيانات.
 *
 * وجهةٌ لا مسار لضبطها تبقى معطّلة مهما كان محرّكها مبنيًّا — والمشغّل لا يفتح
 * `psql` ليُفعّل إنذاره.
 */
class WebhookDestinationIsManagedFromTheScreenTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
    }

    #[Test]
    public function the_destination_is_saved_and_shown_back_without_its_secret(): void
    {
        $this->actingAs($this->admin())
            ->put('/bridge/webhook', [
                'url' => 'https://project.test/hooks/hiroote',
                'secret' => 'سرٌّ-طوله-ستة-عشر-محرفًا',
                'is_enabled' => true,
            ])
            ->assertRedirect();

        $hook = ProjectWebhook::query()->firstOrFail();
        $this->assertSame($this->project->id, $hook->project_id);
        $this->assertSame('سرٌّ-طوله-ستة-عشر-محرفًا', $hook->secret);

        // الشاشة تعرض الحالة لا السرّ: حمولةٌ تحمله تُسرّبه إلى كل من يفتح
        // مصدر الصفحة، وهو ما يوقّع باسمنا.
        $payload = $this->actingAs($this->admin())->get('/bridge')->viewData('page')['props']['webhook'];

        $this->assertSame('https://project.test/hooks/hiroote', $payload['url']);
        $this->assertSame('لم تُجرَّب بعد', $payload['status']);
        $this->assertArrayNotHasKey('secret', $payload);
    }

    #[Test]
    public function a_first_setup_without_a_secret_is_refused(): void
    {
        // وجهةٌ بلا سرّ تُرسل بلا توقيع: المستقبِل لا يستطيع تمييز إنذارنا
        // عن أي طلبٍ يعرف عنوانه.
        $this->actingAs($this->admin())
            ->put('/bridge/webhook', ['url' => 'https://project.test/hooks/hiroote'])
            ->assertSessionHasErrors('secret');

        $this->assertSame(0, ProjectWebhook::query()->count());
    }

    #[Test]
    public function an_empty_secret_on_edit_keeps_the_stored_one(): void
    {
        // من يصحّح عنوانًا لا ينوي إبطال توقيع كل دفعة بعده.
        ProjectWebhook::query()->create([
            'project_id' => $this->project->id,
            'url' => 'https://old.test/hooks',
            'secret' => 'السرّ-المحفوظ-الأصلي',
            'is_enabled' => true,
        ]);

        $this->actingAs($this->admin())
            ->put('/bridge/webhook', ['url' => 'https://new.test/hooks', 'is_enabled' => true])
            ->assertRedirect();

        $hook = ProjectWebhook::query()->firstOrFail();
        $this->assertSame('https://new.test/hooks', $hook->url);
        $this->assertSame('السرّ-المحفوظ-الأصلي', $hook->secret);
    }

    #[Test]
    public function a_new_destination_clears_the_previous_failure(): void
    {
        // «أخفقت» تصف وجهةً لم تعد مضبوطة: إبقاؤها بعد التصحيح يجعل الشاشة
        // تتّهم عنوانًا لم يُجرَّب بعد.
        ProjectWebhook::query()->create([
            'project_id' => $this->project->id,
            'url' => 'https://old.test/hooks',
            'secret' => 'السرّ-المحفوظ-الأصلي',
            'is_enabled' => true,
            'last_error' => 'ردّ 500',
            'last_error_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->put('/bridge/webhook', ['url' => 'https://new.test/hooks', 'is_enabled' => true]);

        $hook = ProjectWebhook::query()->firstOrFail();
        $this->assertNull($hook->last_error);
        $this->assertSame('لم تُجرَّب بعد', $hook->statusLabel());
    }

    #[Test]
    public function a_reader_cannot_change_the_destination(): void
    {
        $this->actingAs(User::factory()->role(Role::SupportAgent)->create())
            ->put('/bridge/webhook', [
                'url' => 'https://project.test/hooks/hiroote',
                'secret' => 'سرٌّ-طوله-ستة-عشر-محرفًا',
            ])
            ->assertForbidden();

        $this->assertSame(0, ProjectWebhook::query()->count());
    }

    private function admin(): User
    {
        return User::factory()->role(Role::SystemAdmin)->create();
    }
}
