<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Models;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $section_id
 * @property int|null $knowledge_item_id
 * @property int|null $conversation_id
 * @property FeedbackKind $kind
 * @property string $body
 * @property int $occurrences
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
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
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProjectSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ProjectSection::class, 'section_id');
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }
}
