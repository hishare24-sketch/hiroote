<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Models;

use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * إعداد تحكم المستخدم بالمستوى — وثيقة 06 §12.
 *
 * @property int $id
 * @property int $project_id
 * @property AssistantLevel $default_level
 * @property bool $allow_level_change
 * @property string $level_scope
 * @property string $availability
 * @property string|null $availability_key
 */
class AssistantProfile extends Model
{
    use BelongsToProject;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'default_level' => AssistantLevel::class,
            'allow_level_change' => 'boolean',
        ];
    }

    /** إعداد المشروع، أو إعدادٌ افتراضي حين لم يُحفظ بعد. */
    public static function forProject(Project $project): self
    {
        return self::query()->firstOrCreate(
            ['project_id' => $project->id],
            [
                'default_level' => AssistantLevel::Balanced,
                'allow_level_change' => true,
                'level_scope' => 'persistent',
                'availability' => 'all',
            ],
        );
    }
}
