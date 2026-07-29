<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeScreenManagementTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private ProjectSection $section;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->project = ProjectFactory::default();
        $this->section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المحفظة',
            'slug' => 'wallet',
        ]);
        $this->manager = User::factory()->role(Role::KnowledgeManager)->create();
    }

    #[Test]
    public function a_screen_is_created_with_its_image_and_described_facets(): void
    {
        $this->actingAs($this->manager)
            ->post("/knowledge/sections/{$this->section->id}/screens", [
                'name' => 'طلب سحب',
                'key' => 'wallet.withdraw',
                'path' => '/wallet/withdraw',
                'description' => 'يطلب المستخدم منها تحويل رصيده إلى حسابه البنكي.',
                'elements' => ['المبلغ', 'الحساب البنكي'],
                'actions' => ['إرسال الطلب'],
                'states' => ['قيد المراجعة'],
                'image' => UploadedFile::fake()->image('withdraw.png', 800, 600),
            ])
            ->assertRedirect();

        $screen = KnowledgeScreen::query()->forProject($this->project)->firstOrFail();

        $this->assertSame('wallet.withdraw', $screen->key);
        $this->assertSame(['المبلغ', 'الحساب البنكي'], $screen->elements);
        $this->assertNotNull($screen->image_path);
        Storage::disk('public')->assertExists($screen->image_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.screen_create']);
    }

    #[Test]
    public function replacing_the_image_deletes_the_file_it_replaced(): void
    {
        // ملفات يتيمة تتراكم بصمت حتى يمتلئ القرص دون أن يشير شيء إلى السبب.
        $screen = $this->screen();
        $old = $screen->image_path;

        $this->actingAs($this->manager)
            ->post("/knowledge/screens/{$screen->id}", [
                'name' => $screen->name,
                'description' => 'وصف محدَّث',
                'image' => UploadedFile::fake()->image('new.png'),
            ])
            ->assertRedirect();

        $screen->refresh();

        $this->assertNotSame($old, $screen->image_path);
        Storage::disk('public')->assertMissing((string) $old);
        Storage::disk('public')->assertExists((string) $screen->image_path);
    }

    #[Test]
    public function deleting_a_screen_removes_its_file_too(): void
    {
        $screen = $this->screen();
        $path = (string) $screen->image_path;

        $this->actingAs($this->manager)
            ->delete("/knowledge/screens/{$screen->id}")
            ->assertRedirect();

        $this->assertSame(0, KnowledgeScreen::query()->count());
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.screen_delete']);
    }

    #[Test]
    public function the_key_must_be_unique_inside_the_project(): void
    {
        // مفتاحان متطابقان يجعلان سياق المساعد عشوائيًا بين شاشتين.
        $this->screen('wallet.withdraw');

        $this->actingAs($this->manager)
            ->post("/knowledge/sections/{$this->section->id}/screens", [
                'name' => 'شاشة أخرى',
                'key' => 'wallet.withdraw',
                'description' => 'وصف',
            ])
            ->assertSessionHasErrors('key');
    }

    #[Test]
    public function the_same_key_may_exist_in_another_project(): void
    {
        $this->screen('wallet.withdraw');

        $other = Project::factory()->create(['slug' => 'other-project', 'sort_order' => 7]);
        $otherSection = ProjectSection::query()->create([
            'project_id' => $other->id,
            'name' => 'المحفظة',
            'slug' => 'wallet',
        ]);

        KnowledgeScreen::query()->create([
            'project_id' => $other->id,
            'section_id' => $otherSection->id,
            'name' => 'طلب سحب',
            'key' => 'wallet.withdraw',
        ]);

        $this->assertSame(2, KnowledgeScreen::query()->where('key', 'wallet.withdraw')->count());
    }

    #[Test]
    public function a_malformed_key_is_refused_in_arabic(): void
    {
        // المفتاح يسافر في عنوان طلب من مشروع خارجي؛ حرفٌ عربي فيه يجعل
        // المطابقة تعتمد على ترميز الرابط.
        $this->actingAs($this->manager)
            ->post("/knowledge/sections/{$this->section->id}/screens", [
                'name' => 'شاشة',
                'key' => 'شاشة السحب',
                'description' => 'وصف',
            ])
            ->assertSessionHasErrors([
                'key' => 'المفتاح حروف لاتينية صغيرة وأرقام تفصلها نقطة، مثل wallet.withdraw.',
            ]);
    }

    #[Test]
    public function a_screen_from_another_project_is_not_found(): void
    {
        $other = Project::factory()->create(['slug' => 'far', 'sort_order' => 6]);
        $farSection = ProjectSection::query()->create([
            'project_id' => $other->id,
            'name' => 'قسم بعيد',
            'slug' => 'far-section',
        ]);
        $farScreen = KnowledgeScreen::query()->create([
            'project_id' => $other->id,
            'section_id' => $farSection->id,
            'name' => 'شاشة بعيدة',
        ]);

        $this->actingAs($this->manager)
            ->delete("/knowledge/screens/{$farScreen->id}")
            ->assertNotFound();

        $this->actingAs($this->manager)
            ->post("/knowledge/sections/{$farSection->id}/screens", [
                'name' => 'اختطاف',
                'description' => 'وصف',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function a_read_only_role_cannot_touch_screens(): void
    {
        $screen = $this->screen();
        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)->delete("/knowledge/screens/{$screen->id}")->assertForbidden();
        $this->actingAs($auditor)
            ->post("/knowledge/sections/{$this->section->id}/screens", [
                'name' => 'شاشة',
                'description' => 'وصف',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function the_section_screen_exposes_the_image_url_and_key(): void
    {
        $this->screen('wallet.withdraw');

        $this->actingAs($this->manager)
            ->get("/knowledge/sections/{$this->section->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Knowledge/Show')
                ->where('screens.0.key', 'wallet.withdraw')
                ->whereNot('screens.0.image_url', null));
    }

    #[Test]
    public function a_screen_without_a_description_does_not_count_as_coverage(): void
    {
        // صورةٌ بلا وصف تجعل القسم يبدو موثَّقًا وهو ليس كذلك.
        KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'name' => 'شاشة بصورة فقط',
            'image_path' => 'screens/1/x.png',
        ]);

        $this->actingAs($this->manager)
            ->get('/knowledge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sections.0.screens', 0)
                ->where('sections.0.met.screens', false));
    }

    private function screen(?string $key = null): KnowledgeScreen
    {
        $this->actingAs($this->manager)->post("/knowledge/sections/{$this->section->id}/screens", [
            'name' => 'طلب سحب',
            'key' => $key,
            'description' => 'وصف الشاشة.',
            'image' => UploadedFile::fake()->image('shot.png'),
        ]);

        return KnowledgeScreen::query()->forProject($this->project)->latest('id')->firstOrFail();
    }
}
