<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models\Concerns;

use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * يربط سجلًّا تشغيليًا بمشروعه — ADR-0003.
 *
 * التقييد صريح لا global scope مقصود: scope عام يقرأ الجلسة يعيد صفرًا بصمت
 * داخل مهمة طابور بلا جلسة، فيبدو أن لا بيانات وهي موجودة. الاستدعاء الظاهر
 * `forProject()` يُنسى في مكان واحد فيكشفه الاختبار، ولا يكذب في كل مكان.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToProject
{
    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @param Builder<static> $query */
    public function scopeForProject(Builder $query, Project|int $project): void
    {
        $query->where(
            $this->qualifyColumn('project_id'),
            $project instanceof Project ? $project->id : $project,
        );
    }
}
