<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use App\Domains\Conversations\Models\Conversation;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * سجل توكن — append-only على مستوى قاعدة البيانات (وثيقة 02 §8).
 *
 * @property int $id
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $knowledge_tokens
 * @property int $attachment_tokens
 * @property int $tool_tokens
 * @property string|null $section
 * @property Carbon $recorded_on
 */
class TokenUsageRecord extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['recorded_on' => 'date', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<AiProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('token_usage_records is append-only.'));
        static::deleting(static fn (): never => throw new LogicException('token_usage_records is append-only.'));
    }
}
