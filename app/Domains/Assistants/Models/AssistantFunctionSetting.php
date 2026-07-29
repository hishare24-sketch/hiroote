<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Models;

use App\Domains\Assistants\Enums\AssistantFunction;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Model;

/**
 * سويتش وظيفة داخل مشروع — وثيقة 06 §13.
 *
 * @property int $id
 * @property int $project_id
 * @property AssistantFunction $key
 * @property bool $is_enabled
 */
class AssistantFunctionSetting extends Model
{
    use BelongsToProject;

    protected $table = 'assistant_functions';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['key' => AssistantFunction::class, 'is_enabled' => 'boolean'];
    }

    /**
     * حالة كل وظيفة في المشروع، مع الافتراضي حين لا صفَّ لها.
     *
     * صفٌّ غائب يعني «لم يُمسّ» لا «مطفأ»: الوظيفة الجديدة تبدأ بافتراضها
     * المعلن في الـ enum بدل أن تُعتبر محظورة بصمت.
     *
     * @return array<string, bool>
     */
    public static function mapFor(Project $project): array
    {
        $stored = self::query()->forProject($project)->pluck('is_enabled', 'key');

        $map = [];

        foreach (AssistantFunction::cases() as $function) {
            $map[$function->value] = (bool) ($stored[$function->value] ?? $function->defaultEnabled());
        }

        return $map;
    }
}
