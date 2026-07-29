<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /** @var class-string<Conversation> */
    protected $model = Conversation::class;

    /**
     * @return array<string, mixed>
     */
    /**
     * مجموعة مستخدمين ثابتة يُسحب منها.
     *
     * بلا تجميع يصبح لكل محادثة مستخدم فريد، فتتساوى «تكلفة المحادثة» و«تكلفة
     * المستخدم» — رقمان متطابقان يخفيان أن المستخدم الواحد يسأل أكثر من مرة.
     *
     * @var list<array{id: string, name: string}>|null
     */
    private static ?array $userPool = null;

    /** @return array{id: string, name: string} */
    private function user(): array
    {
        self::$userPool ??= array_map(
            fn (int $index): array => [
                'id' => (string) (4000 + $index),
                'name' => fake()->name(),
            ],
            range(1, 48),
        );

        return fake()->randomElement(self::$userPool);
    }

    public function definition(): array
    {
        $user = $this->user();
        $started = fake()->dateTimeBetween('-30 days', 'now');
        $duration = fake()->numberBetween(60, 900);
        $messages = fake()->numberBetween(2, 14);
        $tokens = $messages * fake()->numberBetween(180, 600);

        $outcome = fake()->randomElement([
            ConversationOutcome::Resolved,
            ConversationOutcome::Resolved,
            ConversationOutcome::Resolved,
            ConversationOutcome::Ticket,
            ConversationOutcome::Human,
            ConversationOutcome::Abandoned,
        ]);

        return [
            'reference' => '#HS-'.fake()->unique()->numberBetween(50000, 59999),
            'user_label' => $user['name'],
            'external_user_id' => $user['id'],
            'section' => fake()->randomElement(SeedVocabulary::SECTIONS),
            'assistant' => fake()->randomElement(['عام', 'متخصص']),
            'level' => fake()->randomElement(AssistantLevel::cases()),
            'detected_intent' => fake()->randomElement(SeedVocabulary::INTENTS),
            'confidence' => fake()->numberBetween(55, 99),
            'outcome' => $outcome,
            'resolved_first_answer' => $outcome === ConversationOutcome::Resolved && fake()->boolean(60),
            'understood_intent' => fake()->boolean(94),
            'rephrased' => fake()->boolean(14),
            'message_count' => $messages,
            'duration_seconds' => $duration,
            'total_tokens' => $tokens,
            // متوسط تكلفة تقريبي 0.26 ريال لكل ألف توكن — يُستبدل بالحساب الفعلي.
            'cost' => round($tokens * 0.00026, 4),
            'first_response_ms' => fake()->numberBetween(800, 3200),
            'avg_response_ms' => fake()->numberBetween(1200, 4200),
            'rating' => $outcome === ConversationOutcome::Abandoned
                ? null
                : fake()->randomFloat(1, 3.4, 5.0),
            'started_at' => $started,
            'ended_at' => (clone $started)->modify("+{$duration} seconds"),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'outcome' => ConversationOutcome::Resolved,
            'rating' => fake()->randomFloat(1, 4.0, 5.0),
        ]);
    }

    public function ulid(): static
    {
        return $this->state(fn (): array => ['ulid' => (string) Str::ulid()]);
    }
}
