<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\FeedbackSource;
use App\Domains\Knowledge\Models\FeedbackVerification;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeedbackVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private ProjectSection $section;

    private KnowledgeScreen $screen;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
        $this->section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المحفظة',
            'slug' => 'wallet',
        ]);
        $this->screen = KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'name' => 'طلب سحب',
            'key' => 'wallet.withdraw',
            'description' => 'إنشاء طلب سحب.',
        ]);
        $this->manager = User::factory()->role(Role::KnowledgeManager)->create();
    }

    #[Test]
    public function an_assistant_observation_cannot_be_closed_as_fixed_before_it_is_proven(): void
    {
        // هذا هو القيد الذي يمنع الحلقة من التصديق على نفسها: يرصد النموذج،
        // فيُغلق الرصد بتعديل، فيقرأ النموذج تعديله في الجولة التالية.
        $note = $this->note(FeedbackSource::Assistant);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/close", ['resolution' => 'fixed'])
            ->assertSessionHasErrors('resolution');

        $this->assertNull($note->fresh()?->resolved_at);
    }

    #[Test]
    public function a_field_verification_that_reproduces_it_unlocks_the_edit(): void
    {
        $note = $this->note(FeedbackSource::Assistant);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/verify", [
                'outcome' => 'reproduced',
                'steps' => 'دخلت بحساب تجريبي وفتحت شاشة طلب سحب وأدخلت مبلغًا أقل من الحد.',
                'finding' => 'الشاشة لا تذكر الحد الأدنى قبل الإرسال.',
                'screen_id' => $this->screen->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, $note->verifications()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.feedback_verify']);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/close", ['resolution' => 'fixed'])
            ->assertSessionHasNoErrors();

        $this->assertSame('fixed', $note->fresh()?->resolution);
    }

    #[Test]
    public function a_verification_that_fails_to_reproduce_does_not_unlock_the_edit(): void
    {
        // «لم يتكرر» نتيجة كاملة: لا يُبنى على رصد لم يُعَد إنتاجه تعديلٌ للمحتوى.
        $note = $this->note(FeedbackSource::Assistant);

        $this->actingAs($this->manager)->post("/knowledge/feedback/{$note->id}/verify", [
            'outcome' => 'not_reproduced',
            'steps' => 'جرّبت الخطوات ثلاث مرات بحسابين مختلفين ولم أواجه ما رُصد.',
        ]);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/close", ['resolution' => 'fixed'])
            ->assertSessionHasErrors('resolution');

        // لكن الاستبعاد متاح: رفض رصدٍ لم يثبت ليس فعلًا خطرًا.
        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/close", ['resolution' => 'dismissed'])
            ->assertSessionHasNoErrors();

        $this->assertSame('dismissed', $note->fresh()?->resolution);
    }

    #[Test]
    public function a_support_note_is_a_witness_account_and_needs_no_reproof(): void
    {
        $note = $this->note(FeedbackSource::Support);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/close", ['resolution' => 'fixed'])
            ->assertSessionHasNoErrors();

        $this->assertSame('fixed', $note->fresh()?->resolution);
    }

    #[Test]
    public function steps_are_required_because_a_signature_is_not_proof(): void
    {
        $note = $this->note(FeedbackSource::Assistant);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/verify", [
                'outcome' => 'reproduced',
                'steps' => 'تحقّقت',
            ])
            ->assertSessionHasErrors('steps');

        $this->assertSame(0, FeedbackVerification::query()->count());
    }

    #[Test]
    public function verifying_claims_the_note_when_nobody_owns_it(): void
    {
        $note = $this->note(FeedbackSource::Assistant);

        $this->actingAs($this->manager)->post("/knowledge/feedback/{$note->id}/verify", [
            'outcome' => 'different_cause',
            'steps' => 'أعدت المحاولة على شاشة السجل لا شاشة السحب فظهر الالتباس هناك.',
        ]);

        $this->assertSame($this->manager->id, $note->fresh()?->assigned_to);
    }

    #[Test]
    public function claiming_and_releasing_a_note_is_recorded(): void
    {
        $note = $this->note(FeedbackSource::User);

        $this->actingAs($this->manager)->post("/knowledge/feedback/{$note->id}/assign");
        $this->assertSame($this->manager->id, $note->fresh()?->assigned_to);

        $this->actingAs($this->manager)->post("/knowledge/feedback/{$note->id}/assign");
        $this->assertNull($note->fresh()?->assigned_to);

        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.feedback_claim']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.feedback_release']);
    }

    #[Test]
    public function a_verification_may_name_a_different_screen_than_the_one_reported(): void
    {
        // اختلاف الشاشة بين المرصود والمجرَّب نتيجةٌ بذاتها، لا خطأ في الإدخال.
        $other = KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'name' => 'سجل العمليات',
            'key' => 'wallet.transactions',
            'description' => 'سجل الحركات.',
        ]);

        $note = $this->note(FeedbackSource::Assistant);

        $this->actingAs($this->manager)->post("/knowledge/feedback/{$note->id}/verify", [
            'outcome' => 'different_cause',
            'steps' => 'الالتباس ظهر في سجل العمليات لا في شاشة السحب.',
            'screen_id' => $other->id,
        ]);

        $this->assertSame($other->id, FeedbackVerification::query()->firstOrFail()->screen_id);
    }

    #[Test]
    public function a_screen_from_another_project_cannot_be_named_in_a_verification(): void
    {
        $far = Project::factory()->create(['slug' => 'far-one', 'sort_order' => 5]);
        $farSection = ProjectSection::query()->create([
            'project_id' => $far->id,
            'name' => 'قسم بعيد',
            'slug' => 'far',
        ]);
        $farScreen = KnowledgeScreen::query()->create([
            'project_id' => $far->id,
            'section_id' => $farSection->id,
            'name' => 'شاشة بعيدة',
        ]);

        $note = $this->note(FeedbackSource::Assistant);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/verify", [
                'outcome' => 'reproduced',
                'steps' => 'محاولة الإشارة إلى شاشة من مشروع آخر.',
                'screen_id' => $farScreen->id,
            ])
            ->assertNotFound();
    }

    #[Test]
    public function a_read_only_role_cannot_verify_or_close(): void
    {
        $note = $this->note(FeedbackSource::Assistant);
        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)
            ->post("/knowledge/feedback/{$note->id}/verify", [
                'outcome' => 'reproduced',
                'steps' => 'خطوات كافية الطول للتحقق من الرفض.',
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->post("/knowledge/feedback/{$note->id}/close", ['resolution' => 'dismissed'])
            ->assertForbidden();
    }

    #[Test]
    public function a_closed_note_can_be_reopened(): void
    {
        $note = $this->note(FeedbackSource::Support);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/close", ['resolution' => 'fixed']);

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}/close", ['resolution' => 'reopen']);

        $note->refresh();

        $this->assertNull($note->resolved_at);
        $this->assertNull($note->resolution);
    }

    private function note(FeedbackSource $source): KnowledgeFeedback
    {
        return KnowledgeFeedback::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'screen_id' => $this->screen->id,
            'kind' => FeedbackKind::Unanswered,
            'source' => $source,
            'body' => 'كم الحد الأدنى للسحب؟',
            'occurrences' => 7,
        ]);
    }
}
