<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Knowledge\Enums\FeedbackSource;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiBridgeTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private ProjectSection $section;

    private KnowledgeScreen $screen;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
        $this->section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المحفظة',
            'slug' => 'wallet',
            'description' => 'الرصيد وحركاته.',
        ]);
        $this->screen = KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'name' => 'طلب سحب',
            'key' => 'wallet.withdraw',
            'description' => 'إنشاء طلب سحب إلى الحساب البنكي.',
            'elements' => ['المبلغ', 'الحساب البنكي'],
        ]);

        $this->token = $this->mint($this->project);
    }

    #[Test]
    public function the_context_endpoint_returns_the_screen_and_its_published_knowledge(): void
    {
        $this->item('مواعيد الصرف', KnowledgeStatus::Published);
        $this->item('مسودة داخلية', KnowledgeStatus::Draft);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/context?screen=wallet.withdraw')
            ->assertOk();

        $response->assertJsonPath('screen.key', 'wallet.withdraw');
        $response->assertJsonPath('section.name', 'المحفظة');
        $response->assertJsonPath('screen.elements.0', 'المبلغ');

        // المنشور وحده يعبر: المسودة عملٌ داخلي لم يعتمده أحد.
        $response->assertJsonCount(1, 'knowledge');
        $response->assertJsonPath('knowledge.0.title', 'مواعيد الصرف');
    }

    #[Test]
    public function a_key_reaches_only_its_own_project(): void
    {
        // المشروع يُشتقّ من المفتاح لا من الطلب — وهذا ما يمنع سؤال مشروع بمفتاح
        // مشروع آخر.
        $other = Project::factory()->create(['slug' => 'other-one', 'sort_order' => 4]);
        $otherSection = ProjectSection::query()->create([
            'project_id' => $other->id,
            'name' => 'قسم آخر',
            'slug' => 'other-section',
        ]);
        KnowledgeScreen::query()->create([
            'project_id' => $other->id,
            'section_id' => $otherSection->id,
            'name' => 'شاشة أخرى',
            'key' => 'other.screen',
            'description' => 'وصف.',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/context?screen=other.screen')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'screen_not_found');

        // وبمفتاح المشروع الآخر تُقرأ شاشته هو.
        $this->withToken($this->mint($other))
            ->getJson('/api/v1/context?screen=other.screen')
            ->assertOk();
    }

    #[Test]
    public function a_revoked_key_stops_working_and_says_nothing_about_why(): void
    {
        $key = ProjectApiKey::query()->firstOrFail();
        $key->forceFill(['revoked_at' => now()])->save();

        $this->withToken($this->token)
            ->getJson('/api/v1/context?screen=wallet.withdraw')
            ->assertUnauthorized()
            // الرسالة نفسها لمفتاح مجهول: تمييزها يخبر من يجرّب أيُّها كان صحيحًا.
            ->assertJsonPath('error.code', 'api_key_invalid');

        $this->withToken('hrt_'.str_repeat('x', 48))
            ->getJson('/api/v1/context?screen=wallet.withdraw')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'api_key_invalid');
    }

    #[Test]
    public function an_expired_key_is_refused(): void
    {
        ProjectApiKey::query()->firstOrFail()
            ->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withToken($this->token)
            ->getJson('/api/v1/context?screen=wallet.withdraw')
            ->assertUnauthorized();
    }

    #[Test]
    public function a_request_without_a_key_is_refused(): void
    {
        $this->getJson('/api/v1/context?screen=wallet.withdraw')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'api_key_missing');
    }

    #[Test]
    public function an_inactive_project_is_refused_even_with_a_valid_key(): void
    {
        $this->project->forceFill(['is_active' => false])->save();

        $this->withToken($this->token)
            ->getJson('/api/v1/context?screen=wallet.withdraw')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'project_inactive');
    }

    #[Test]
    public function a_conversation_is_recorded_with_its_screen_key(): void
    {
        // مفتاح الشاشة على المحادثة هو ما يجعل قياس أثر تعديل الوصف ممكنًا.
        $this->withToken($this->token)
            ->postJson('/api/v1/conversations', [
                'reference' => 'HS-90001',
                'screen' => 'wallet.withdraw',
                'outcome' => 'human',
                'message_count' => 6,
                'confidence' => 42,
            ])
            ->assertCreated();

        $conversation = Conversation::query()->forProject($this->project)->firstOrFail();

        $this->assertSame('wallet.withdraw', $conversation->screen_key);
        $this->assertSame('المحفظة', $conversation->section);
    }

    #[Test]
    public function resending_the_same_reference_updates_instead_of_duplicating(): void
    {
        // إعادة الإرسال بعد فشل شبكة لا يجوز أن تضاعف كل إحصاء.
        $payload = [
            'reference' => 'HS-90002',
            'screen' => 'wallet.withdraw',
            'outcome' => 'open',
            'message_count' => 2,
        ];

        $this->withToken($this->token)->postJson('/api/v1/conversations', $payload)->assertCreated();

        $this->withToken($this->token)
            ->postJson('/api/v1/conversations', [...$payload, 'outcome' => 'resolved', 'message_count' => 9])
            ->assertOk();

        $this->assertSame(1, Conversation::query()->forProject($this->project)->count());

        $conversation = Conversation::query()->forProject($this->project)->firstOrFail();
        $this->assertSame(9, $conversation->message_count);
    }

    #[Test]
    public function an_unknown_screen_key_is_refused_rather_than_stored(): void
    {
        // مفتاحٌ مكتوب خطأً يُحفظ بصمت يبني إحصاءً لشاشة لا وجود لها.
        $this->withToken($this->token)
            ->postJson('/api/v1/conversations', [
                'reference' => 'HS-90003',
                'screen' => 'wallet.typo',
                'outcome' => 'open',
            ])
            ->assertNotFound();

        $this->assertSame(0, Conversation::query()->count());
    }

    #[Test]
    public function incoming_feedback_lands_as_an_assistant_observation_awaiting_verification(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/v1/feedback', [
                'screen' => 'wallet.withdraw',
                'body' => 'كم الحد الأدنى للسحب؟',
            ])
            ->assertCreated()
            ->assertJsonPath('created', true);

        $note = KnowledgeFeedback::query()->forProject($this->project)->firstOrFail();

        $this->assertSame(FeedbackSource::Assistant, $note->source);
        $this->assertSame($this->screen->id, $note->screen_id);
        $this->assertSame($this->section->id, $note->section_id);
        // ما يصل من الجسر إشارةٌ لا شهادة: لا يُبنى عليها تعديل قبل تحقق ميداني.
        $this->assertFalse($note->loadMissing('verifications')->isActionable());
    }

    #[Test]
    public function the_same_observation_raises_its_counter_instead_of_multiplying(): void
    {
        // سبع نسخ من سؤال واحد تُخفي أنه سؤال واحد سأله سبعة.
        foreach (range(1, 3) as $ignored) {
            $this->withToken($this->token)->postJson('/api/v1/feedback', [
                'screen' => 'wallet.withdraw',
                'body' => 'كم الحد الأدنى للسحب؟',
            ]);
        }

        $this->assertSame(1, KnowledgeFeedback::query()->count());
        $this->assertSame(3, KnowledgeFeedback::query()->firstOrFail()->occurrences);
    }

    #[Test]
    public function an_issued_key_is_never_written_anywhere_in_clear_text(): void
    {
        // من يقرأ نسخةً من قاعدة البيانات — أو سجل التدقيق — لا يستعيد مفتاحًا.
        $admin = User::factory()->role(Role::SystemAdmin)->create();
        $issued = $this->actingAs($admin)
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->post("/projects/{$this->project->id}/keys", ['name' => 'جسر هاي شير'])
            ->assertRedirect()
            ->assertSessionHas('issued_api_key');

        $token = (string) session('issued_api_key');
        $this->assertStringStartsWith(ProjectApiKey::TOKEN_PREFIX, $token);

        $key = ProjectApiKey::query()->where('name', 'جسر هاي شير')->firstOrFail();
        $this->assertSame(hash('sha256', $token), $key->hash);
        $this->assertStringNotContainsString($token, json_encode($key->toArray(), JSON_THROW_ON_ERROR));

        $trail = DB::table('audit_logs')
            ->whereRaw('new_values::text LIKE ?', ['%'.$token.'%'])
            ->orWhereRaw('old_values::text LIKE ?', ['%'.$token.'%'])
            ->orWhere('reason', 'LIKE', '%'.$token.'%')
            ->count();

        $this->assertSame(0, $trail, 'سجل التدقيق يحفظ المفتاح صريحًا.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'integrations.api_key_issue']);

        // والمفتاح المُصدَر يعمل فعلًا.
        $this->withToken($token)->getJson('/api/v1/context?screen=wallet.withdraw')->assertOk();

        // ثم يتوقف فور إبطاله.
        $this->actingAs($admin)
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->delete("/projects/{$this->project->id}/keys/{$key->id}")
            ->assertRedirect();

        $this->withToken($token)
            ->getJson('/api/v1/context?screen=wallet.withdraw')
            ->assertUnauthorized();
    }

    private function mint(Project $project): string
    {
        $minted = ProjectApiKey::mint();

        ProjectApiKey::query()->create([
            'project_id' => $project->id,
            'name' => 'مفتاح اختبار',
            'prefix' => $minted['prefix'],
            'hash' => $minted['hash'],
        ]);

        return $minted['token'];
    }

    private function item(string $title, KnowledgeStatus $status): void
    {
        KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'title' => $title,
            'kind' => KnowledgeKind::Faq,
            'status' => $status,
            'body' => 'نص',
            'published_at' => $status->isLive() ? now() : null,
        ]);
    }
}
