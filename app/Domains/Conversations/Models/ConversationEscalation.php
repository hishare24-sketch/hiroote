<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Models;

use App\Domains\Conversations\Enums\EscalationSeverity;
use App\Domains\Conversations\Enums\EscalationTarget;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $project_id
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
    use BelongsToProject;
    use HasUlids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * التصعيد يرث مشروع محادثته دائمًا.
     *
     * الاشتقاق هنا لا في كل موضع إنشاء: تصعيدٌ يخالف مشروع محادثته حالة لا
     * معنى لها، ومنعها في النموذج أضمن من تذكّرها في كل مُنشئ.
     */
    protected static function booted(): void
    {
        static::creating(function (self $escalation): void {
            if ($escalation->getAttribute('project_id') !== null) {
                return;
            }

            $inherited = $escalation->conversation?->project_id;

            if ($inherited === null) {
                throw new LogicException(
                    'تصعيد بلا مشروع ولا محادثة يُشتق منها — لا يمكن تحديد مالكه.',
                );
            }

            $escalation->project_id = $inherited;
        });
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
