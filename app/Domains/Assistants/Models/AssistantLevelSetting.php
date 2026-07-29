<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Models;

use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use App\Domains\Providers\Models\AiModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * بطاقة مستوى مساعد داخل مشروع — وثيقة 06 §12.
 *
 * @property int $id
 * @property int $project_id
 * @property AssistantLevel $key
 * @property string $label
 * @property string $description
 * @property string $response_length
 * @property int $token_limit
 * @property int $intelligence
 * @property int $initiative
 * @property int $creativity
 * @property int $detail
 * @property int $formality
 * @property bool $reads_attachments
 * @property bool $calls_data
 * @property bool $executes_actions
 * @property int $confidence_threshold
 * @property int|null $model_id
 * @property numeric-string $expected_cost
 * @property bool $is_available
 * @property int $sort_order
 */
class AssistantLevelSetting extends Model
{
    use BelongsToProject;

    protected $table = 'assistant_levels';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'key' => AssistantLevel::class,
            'reads_attachments' => 'boolean',
            'calls_data' => 'boolean',
            'executes_actions' => 'boolean',
            'is_available' => 'boolean',
            'expected_cost' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<AiModel, $this> */
    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'model_id');
    }

    /** @param Builder<self> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order');
    }
}
