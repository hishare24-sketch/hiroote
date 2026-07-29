<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\FeedbackSource;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * رصدٌ معلَّق على قسم وشاشة — لا يُبنى عليه تعديل قبل تحقق ميداني.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $section_id
 * @property int|null $screen_id
 * @property int|null $knowledge_item_id
 * @property int|null $conversation_id
 * @property FeedbackKind $kind
 * @property FeedbackSource $source
 * @property string $body
 * @property int $occurrences
 * @property int|null $assigned_to
 * @property Carbon|null $resolved_at
 * @property string|null $resolution
 * @property Carbon $created_at
 * @property-read User|null $assignee
 * @property-read KnowledgeScreen|null $screen
 */
class KnowledgeFeedback extends Model
{
    use BelongsToProject;

    protected $table = 'knowledge_feedback';

    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => FeedbackKind::class,
            'source' => FeedbackSource::class,
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProjectSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ProjectSection::class, 'section_id');
    }

    /** @return BelongsTo<KnowledgeScreen, $this> */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(KnowledgeScreen::class, 'screen_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<FeedbackVerification, $this> */
    public function verifications(): HasMany
    {
        return $this->hasMany(FeedbackVerification::class, 'knowledge_feedback_id');
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }

    /**
     * هل يجوز البناء على هذا الرصد.
     *
     * ما يرفعه موظف الدعم شهادةُ من رأى فلا يحتاج إعادة إثبات؛ وما يرفعه
     * المساعد أو المستخدم إشارةٌ لا تصير أساسًا لتعديل قبل أن يجرّبها إنسان.
     */
    public function isActionable(): bool
    {
        if (! $this->source->needsVerification()) {
            return true;
        }

        return $this->verifications
            ->contains(fn (FeedbackVerification $entry): bool => $entry->outcome->justifiesEdit());
    }
}
