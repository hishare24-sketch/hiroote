<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Models;

use App\Domains\Conversations\Enums\EscalationSeverity;
use App\Domains\Conversations\Enums\EscalationTarget;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $conversation_id
 * @property string $reference
 * @property EscalationTarget $target
 * @property EscalationSeverity $severity
 * @property string $reason
 * @property string $section
 * @property string $subject
 * @property int|null $wait_seconds
 * @property int|null $handling_seconds
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 */
class ConversationEscalation extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

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
            'target' => EscalationTarget::class,
            'severity' => EscalationSeverity::class,
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
