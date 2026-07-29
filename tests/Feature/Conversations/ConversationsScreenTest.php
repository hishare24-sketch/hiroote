<?php

declare(strict_types=1);

namespace Tests\Feature\Conversations;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Enums\EscalationSeverity;
use App\Domains\Conversations\Enums\EscalationTarget;
use App\Domains\Conversations\Enums\MessageRole;
use App\Domains\Conversations\Enums\ToolOutcome;
use App\Domains\Conversations\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConversationsScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function screen_requires_authentication(): void
    {
        $this->get('/conversations')->assertRedirect('/login');

        $support = User::factory()->role(Role::SupportAgent)->create();
        $this->actingAs($support)->get('/conversations')->assertOk();
    }

    #[Test]
    public function metrics_are_computed_from_the_filtered_period(): void
    {
        Conversation::factory()->count(3)->create([
            'outcome' => ConversationOutcome::Resolved,
            'resolved_first_answer' => true,
            'understood_intent' => true,
            'rephrased' => false,
            'started_at' => now()->subHours(2),
        ]);

        // خارج المدى — يجب ألا تدخل في الحساب.
        Conversation::factory()->create([
            'outcome' => ConversationOutcome::Abandoned,
            'started_at' => now()->subDays(20),
        ]);

        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/conversations?period=today')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Conversations/Index')
                ->where('metrics.conversations', 3)
                ->where('metrics.unattended_resolution_rate', 100)
                ->where('metrics.first_answer_resolution_rate', 100)
                ->where('metrics.abandonment_rate', 0)
                ->has('conversations.data', 3));
    }

    #[Test]
    public function the_table_can_be_filtered_by_section_and_outcome(): void
    {
        Conversation::factory()->create([
            'section' => 'المحفظة',
            'outcome' => ConversationOutcome::Ticket,
            'started_at' => now()->subHour(),
        ]);
        Conversation::factory()->create([
            'section' => 'الحملات',
            'outcome' => ConversationOutcome::Resolved,
            'started_at' => now()->subHour(),
        ]);

        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/conversations?section=المحفظة')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('conversations.data', 1)
                ->where('conversations.data.0.section', 'المحفظة'));

        $this->actingAs($admin)
            ->get('/conversations?outcome=resolved')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('conversations.data', 1)
                ->where('conversations.data.0.outcome.value', 'resolved'));
    }

    #[Test]
    public function search_matches_reference_and_intent(): void
    {
        Conversation::factory()->create([
            'reference' => '#HS-91001',
            'detected_intent' => 'استفسار عن حالة السحب',
            'started_at' => now()->subHour(),
        ]);
        Conversation::factory()->create([
            'reference' => '#HS-91002',
            'detected_intent' => 'التحقق من قسيمة',
            'started_at' => now()->subHour(),
        ]);

        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/conversations?search=91001')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('conversations.data', 1)
                ->where('conversations.data.0.reference', '#HS-91001'));

        $this->actingAs($admin)
            ->get('/conversations?search=قسيمة')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('conversations.data', 1)
                ->where('conversations.data.0.reference', '#HS-91002'));
    }

    #[Test]
    public function every_enum_reaches_the_screen_with_its_label_and_tone(): void
    {
        $conversation = Conversation::factory()->create([
            'outcome' => ConversationOutcome::Ticket,
            'started_at' => now()->subHour(),
        ]);

        $conversation->escalations()->create([
            'reference' => '#T-4410',
            'target' => EscalationTarget::Ticket,
            'severity' => EscalationSeverity::High,
            'reason' => 'طلب إجراء مالي',
            'section' => $conversation->section,
            'subject' => 'موعد صرف الأرباح',
        ]);

        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/conversations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('conversations.data.0.outcome.label', 'تذكرة')
                ->where('conversations.data.0.outcome.tone', 'warning')
                ->where('conversations.data.0.escalation.label', 'تذكرة'));
    }

    #[Test]
    public function detail_screen_carries_the_full_timeline(): void
    {
        $conversation = Conversation::factory()->create([
            'outcome' => ConversationOutcome::Resolved,
            'detected_intent' => 'متابعة حالة مشاركة',
            'started_at' => now()->subHour(),
        ]);

        $conversation->messages()->create([
            'role' => MessageRole::User,
            'content' => 'أين مشاركتي؟',
            'tokens' => 120,
        ]);
        $conversation->messages()->create([
            'role' => MessageRole::Assistant,
            'content' => 'مشاركتك قيد التوثيق.',
            'tokens' => 210,
            'latency_ms' => 1400,
        ]);
        $conversation->tools()->create([
            'name' => 'قراءة المشاركات',
            'outcome' => ToolOutcome::Succeeded,
            'duration_ms' => 320,
        ]);
        $conversation->clicks()->create([
            'screen' => 'مشاركاتي / تفاصيل المشاركة',
            'led_to_resolution' => true,
        ]);
        $conversation->events()->create(['type' => 'outcome', 'label' => 'تم الحل']);

        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get("/conversations/{$conversation->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Conversations/Show')
                ->where('conversation.reference', $conversation->reference)
                ->where('conversation.detected_intent', 'متابعة حالة مشاركة')
                ->has('conversation.messages', 2)
                ->has('conversation.tools', 1)
                ->has('conversation.clicks', 1)
                ->has('conversation.timeline', 1)
                ->where('conversation.messages.1.role.label', 'المساعد')
                ->where('conversation.tools.0.outcome.tone', 'success'));
    }

    /**
     * محتوى المحادثة صلاحية منفصلة عن رؤيتها: محلل التكلفة يرى المقاييس
     * والمسار، ولا يرى ما كتبه مستخدم Hi-Share.
     */
    #[Test]
    public function raw_message_content_is_redacted_without_the_content_permission(): void
    {
        $conversation = Conversation::factory()->create();
        $conversation->messages()->create([
            'role' => MessageRole::User,
            'content' => 'رقم حسابي البنكي 1234',
            'tokens' => 40,
        ]);

        $analyst = User::factory()->role(Role::CostAnalyst)->create();

        $this->actingAs($analyst)
            ->get("/conversations/{$conversation->id}")
            ->assertOk()
            ->assertDontSee('رقم حسابي البنكي')
            ->assertInertia(fn ($page) => $page
                ->where('conversation.can_view_content', false)
                ->where('conversation.messages.0.content', null)
                // المقاييس تبقى ظاهرة — الحجب على النص وحده.
                ->where('conversation.messages.0.tokens', 40));

        $support = User::factory()->role(Role::SupportAgent)->create();

        $this->actingAs($support)
            ->get("/conversations/{$conversation->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('conversation.can_view_content', true)
                ->where('conversation.messages.0.content', 'رقم حسابي البنكي 1234'));
    }
}
