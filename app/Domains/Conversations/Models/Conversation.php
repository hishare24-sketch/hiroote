<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Models;

use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Providers\Models\AiModel;
use App\Domains\Providers\Models\AiProvider;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string|null $user_label
 * @property string|null $external_user_id
 * @property int|null $model_id
 * @property string $section
 * @property string|null $assistant
 * @property AssistantLevel $level
 * @property int|null $confidence
 * @property ConversationOutcome $outcome
 * @property bool $resolved_first_answer
 * @property bool $understood_intent
 * @property bool $rephrased
 * @property int $message_count
 * @property int $duration_seconds
 * @property int $total_tokens
 * @property numeric-string $cost
 * @property int|null $first_response_ms
 * @property int|null $avg_response_ms
 * @property numeric-string|null $rating
 * @property string|null $detected_intent
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use HasUlids;

    /** @var class-string<ConversationFactory> */
    protected static string $factory = ConversationFactory::class;

    protected $guarded = [];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => AssistantLevel::class,
            'outcome' => ConversationOutcome::class,
            'resolved_first_answer' => 'boolean',
            'understood_intent' => 'boolean',
            'rephrased' => 'boolean',
            'cost' => 'decimal:4',
            'rating' => 'decimal:1',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AiProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    /** @return BelongsTo<AiModel, $this> */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }

    /** @return HasMany<ConversationMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    /** @return HasMany<ConversationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class);
    }

    /** @return HasMany<ConversationTool, $this> */
    public function tools(): HasMany
    {
        return $this->hasMany(ConversationTool::class);
    }

    /** @return HasMany<ConversationClick, $this> */
    public function clicks(): HasMany
    {
        return $this->hasMany(ConversationClick::class);
    }

    /** @return HasMany<ConversationEscalation, $this> */
    public function escalations(): HasMany
    {
        return $this->hasMany(ConversationEscalation::class);
    }

    /** @param Builder<self> $query */
    public function scopeInPeriod(Builder $query, Carbon $from, Carbon $to): void
    {
        $query->whereBetween('started_at', [$from, $to]);
    }
}
