<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Analytics\Models\TokenUsageRecord;
use App\Domains\Analytics\Models\UsageBudget;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Enums\EscalationSeverity;
use App\Domains\Conversations\Enums\EscalationTarget;
use App\Domains\Conversations\Enums\MessageRole;
use App\Domains\Conversations\Enums\ToolOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\ConversationEscalation;
use App\Domains\Projects\Models\Project;
use App\Domains\Providers\Models\AiProvider;
use Database\Factories\SeedVocabulary;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * بيانات تجريبية للتطوير والعرض — **لا تُشغَّل في الإنتاج**.
 *
 * تولّد محادثات ورسائل وأحداث واستهلاكًا وتصعيدات بأرقام متسقة، فتظهر الشاشات
 * بمحتوى واقعي الشكل قبل وصول الـ Orchestrator. عند وصوله يُستبدل المصدر ولا
 * تتغير الشاشات — العقود هي نفسها.
 */
class DemoDataSeeder extends Seeder
{
    /** حجم كل مشروع مختلف عمدًا: مركز تحكم يقارن مشاريع متفاوتة لا متطابقة. */
    private const CONVERSATION_COUNTS = ['hi-share' => 180, 'mawazin' => 95];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('DemoDataSeeder تُتخطى في الإنتاج.');

            return;
        }

        $providers = AiProvider::query()->with('models')->get();

        if ($providers->isEmpty()) {
            $this->command->warn('لا يوجد مزودون — شغّل ProvidersSeeder أولًا.');

            return;
        }

        foreach (self::CONVERSATION_COUNTS as $slug => $count) {
            $project = Project::query()->where('slug', $slug)->first();

            if ($project === null) {
                continue;
            }

            // إعادة الزرع لا تُضاعف الحركة: مشروعٌ فيه محادثات سلفًا يُترك كما
            // هو. ٩٥ محادثة تصير ١٩٠ فتتضاعف كل نسبة على اللوحة بلا سبب ظاهر،
            // ورقمٌ مضاعَف يُقرأ قياسًا لا خطأ زرع.
            if (Conversation::query()->where('project_id', $project->id)->exists()) {
                $this->command->info("«{$project->name}» فيه بيانات تجريبية سلفًا — تُركت كما هي.");

                continue;
            }

            UsageBudget::query()->firstOrCreate(
                ['project_id' => $project->id, 'scope' => 'platform', 'scope_key' => null],
                ['monthly_limit' => $slug === 'hi-share' ? '6750.00' : '3200.00', 'currency' => 'SAR'],
            );

            Conversation::factory()
                ->count($count)
                ->create(['project_id' => $project->id])
                ->each(function (Conversation $conversation) use ($providers, $project): void {
                    $provider = $providers->random();

                    $conversation->forceFill([
                        'provider_id' => $provider->id,
                        'model_id' => $provider->models->firstWhere('is_default', true)?->id,
                    ])->save();

                    $this->seedTimeline($conversation);
                    $this->seedUsage($conversation, $provider, $project);
                    $this->seedEscalation($conversation, $project);
                });

            $this->openRecentEscalations($project);
        }
    }

    /**
     * تُبقي أحدث الحالات مفتوحة حتى تظهر بطاقة «الحالات المفتوحة الآن» بمحتوى.
     *
     * تُنفَّذ بعد التوليد لا أثناءه: الاعتماد على الاحتمال وحده كان يخرج أحيانًا
     * بصفر حالة مفتوحة، فتبدو الشاشة فارغة بلا سبب.
     */
    private function openRecentEscalations(Project $project): void
    {
        $severities = [
            EscalationSeverity::Critical,
            EscalationSeverity::Critical,
            EscalationSeverity::High,
            EscalationSeverity::High,
            EscalationSeverity::High,
            EscalationSeverity::Medium,
            EscalationSeverity::Medium,
        ];

        ConversationEscalation::query()
            ->forProject($project)
            ->orderByDesc('created_at')
            ->limit(count($severities))
            ->get()
            ->each(function (ConversationEscalation $escalation, int $index) use ($severities): void {
                $escalation->forceFill([
                    'severity' => $severities[$index],
                    'resolved_at' => null,
                    'handling_seconds' => null,
                ])->save();
            });
    }

    private function seedTimeline(Conversation $conversation): void
    {
        $clock = $conversation->started_at->copy();

        $intent = $conversation->detected_intent;
        $answer = SeedVocabulary::REPLIES[$intent] ?? SeedVocabulary::FALLBACK_REPLY;

        for ($i = 0; $i < $conversation->message_count; $i++) {
            $isUser = $i % 2 === 0;
            $clock = $clock->addSeconds(fake()->numberBetween(8, 60));

            $conversation->messages()->create([
                'role' => $isUser ? MessageRole::User : MessageRole::Assistant,
                // أول سؤال هو النية المكتشفة نفسها، ثم متابعات؛ وأول رد يجيبها
                // فعلًا. نصّ واحد مكرر يجعل الشاشة تبدو صحيحة وهي فارغة المعنى.
                'content' => match (true) {
                    $isUser && $i === 0 => ($intent ?? 'لدي استفسار').'؟',
                    $isUser => fake()->randomElement(SeedVocabulary::FOLLOW_UPS),
                    $i === 1 => $answer,
                    default => fake()->randomElement(SeedVocabulary::REPLIES),
                },
                'tokens' => fake()->numberBetween(90, 420),
                'latency_ms' => $isUser ? null : fake()->numberBetween(900, 3600),
                'created_at' => $clock,
            ]);
        }

        foreach (fake()->randomElements(SeedVocabulary::TOOLS, fake()->numberBetween(1, 4)) as $tool) {
            $conversation->tools()->create([
                'name' => $tool,
                'outcome' => fake()->boolean(88) ? ToolOutcome::Succeeded : ToolOutcome::Failed,
                'duration_ms' => fake()->numberBetween(120, 1400),
                'created_at' => $conversation->started_at->copy()->addSeconds(fake()->numberBetween(10, 200)),
            ]);
        }

        if ($conversation->outcome === ConversationOutcome::Resolved) {
            $conversation->clicks()->create([
                'screen' => fake()->randomElement(SeedVocabulary::SCREENS),
                'led_to_resolution' => true,
                'created_at' => $conversation->ended_at ?? $conversation->started_at,
            ]);
        }

        $conversation->events()->create([
            'type' => 'outcome',
            'label' => $conversation->outcome->label(),
            'detail' => $conversation->outcome === ConversationOutcome::Resolved
                ? 'تم تتبع النقر والوصول إلى الشاشة — اعتُبرت المحادثة محلولة'
                : null,
            'created_at' => $conversation->ended_at ?? $conversation->started_at,
        ]);
    }

    private function seedUsage(Conversation $conversation, AiProvider $provider, Project $project): void
    {
        $total = $conversation->total_tokens;

        // نِسَب التفصيل مأخوذة من شاشة الاستهلاك: إدخال 62% · إخراج 29% · معرفة 6%.
        $input = (int) round($total * 0.62);
        $output = (int) round($total * 0.29);
        $knowledge = (int) round($total * 0.06);
        $attachment = (int) round($total * 0.02);

        TokenUsageRecord::query()->create([
            'project_id' => $project->id,
            'conversation_id' => $conversation->id,
            'provider_id' => $provider->id,
            'model_id' => $conversation->model_id,
            'section' => $conversation->section,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'knowledge_tokens' => $knowledge,
            'attachment_tokens' => $attachment,
            'tool_tokens' => max(0, $total - $input - $output - $knowledge - $attachment),
            'recorded_on' => $conversation->started_at->toDateString(),
        ]);

        CostUsageRecord::query()->create([
            'project_id' => $project->id,
            'conversation_id' => $conversation->id,
            'provider_id' => $provider->id,
            'section' => $conversation->section,
            'operation' => 'محادثة',
            'amount' => $conversation->cost,
            'currency' => $provider->currency,
            'recorded_on' => $conversation->started_at->toDateString(),
        ]);
    }

    private function seedEscalation(Conversation $conversation, Project $project): void
    {
        $target = match ($conversation->outcome) {
            ConversationOutcome::Ticket => EscalationTarget::Ticket,
            ConversationOutcome::Human => EscalationTarget::HumanAgent,
            default => null,
        };

        // نسبة من المحادثات المحلولة مرّت بمساعد متخصص قبل حلّها.
        if ($target === null && $conversation->assistant === 'متخصص' && fake()->boolean(55)) {
            $target = EscalationTarget::SpecialistAssistant;
        }

        if ($target === null) {
            return;
        }

        $severity = fake()->randomElement([
            EscalationSeverity::Medium,
            EscalationSeverity::Medium,
            EscalationSeverity::High,
            EscalationSeverity::Critical,
        ]);

        $conversation->escalations()->create([
            'project_id' => $project->id,
            'reference' => $this->escalationReference($target),
            'target' => $target,
            'severity' => $severity,
            'reason' => fake()->randomElement(SeedVocabulary::ESCALATION_REASONS),
            'section' => $conversation->section,
            'subject' => fake()->randomElement(SeedVocabulary::INTENTS),
            'wait_seconds' => fake()->numberBetween(60, 1200),
            'handling_seconds' => fake()->numberBetween(120, 2400),
            // الحالات الحرجة والمرتفعة الحديثة تبقى مفتوحة لتظهر في «الحالات المفتوحة الآن».
            'resolved_at' => $this->shouldStayOpen($conversation->started_at, $severity)
                ? null
                : $conversation->ended_at,
            'created_at' => $conversation->started_at,
        ]);
    }

    private function escalationReference(EscalationTarget $target): string
    {
        $prefix = match ($target) {
            EscalationTarget::Ticket => 'T',
            EscalationTarget::HumanAgent => 'W',
            EscalationTarget::SpecialistAssistant => 'P',
        };

        return "#{$prefix}-".fake()->unique()->numberBetween(1000, 9999);
    }

    private function shouldStayOpen(Carbon $startedAt, EscalationSeverity $severity): bool
    {
        return $startedAt->isAfter(now()->subDay())
            && in_array($severity, [EscalationSeverity::Critical, EscalationSeverity::High], true);
    }
}
