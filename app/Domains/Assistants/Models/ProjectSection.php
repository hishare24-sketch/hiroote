<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Models;

use App\Domains\Assistants\Enums\SectionCapability;
use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use App\Domains\Providers\Models\AiModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * قسم من أقسام المشروع وصفٌّ في مصفوفة التكامل — وثيقة 06 §14.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property AssistantLevel|null $level
 * @property int|null $model_id
 * @property int|null $updated_by
 * @property Carbon $updated_at
 */
class ProjectSection extends Model
{
    use BelongsToProject;
    use HasUlids;

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
            'ai_enabled' => 'boolean',
            'knowledge' => 'boolean',
            'database_link' => 'boolean',
            'api_call' => 'boolean',
            'show_data' => 'boolean',
            'suggest_action' => 'boolean',
            'execute_action' => 'boolean',
            'read_files' => 'boolean',
            'create_ticket' => 'boolean',
            'human_handoff' => 'boolean',
            'feedback' => 'boolean',
        ];
    }

    /** @return BelongsTo<AiModel, $this> */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }

    /**
     * هل القدرة مفعّلة فعليًا.
     *
     * إطفاء «تفعيل الذكاء» يطفئ كل ما تحته: قدرةٌ مفعّلة في قسم معطّل تعِد بما
     * لا يحدث.
     */
    public function grants(SectionCapability $capability): bool
    {
        if ($capability !== SectionCapability::AiEnabled && ! (bool) $this->getAttribute('ai_enabled')) {
            return false;
        }

        return (bool) $this->getAttribute($capability->value);
    }

    /** @param Builder<self> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }
}
