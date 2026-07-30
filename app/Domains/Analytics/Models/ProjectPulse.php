<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * يومٌ واحد من نبض مشروع — مجاميعُ لا صفوفٌ لفرد.
 *
 * @property int $id
 * @property int $project_id
 * @property Carbon $pulse_date
 * @property string $timezone
 * @property bool $is_final
 * @property int $revision
 * @property int|null $active_users
 * @property int|null $logins
 * @property int|null $sessions
 * @property int|null $peak_concurrent
 * @property int|null $presence_minutes
 * @property int|null $peak_hour
 * @property int|null $peak_hour_actions
 * @property int|null $storage_megabytes
 * @property array<string, int>|null $section_actions
 * @property list<array{name: string, subscribers: int}>|null $packages
 * @property array<string, float>|null $health_indicators
 */
class ProjectPulse extends Model
{
    use BelongsToProject;

    protected $guarded = [];

    /** المقاييس العددية التي يقبلها العقد — مصدرٌ واحد يقرأه التحقّق والتقرير. */
    public const METRICS = [
        'active_users',
        'logins',
        'sessions',
        'peak_concurrent',
        'presence_minutes',
        'peak_hour_actions',
        'storage_megabytes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pulse_date' => 'date',
            'is_final' => 'boolean',
            'section_actions' => 'array',
            'packages' => 'array',
            'health_indicators' => 'array',
        ];
    }

    /** @return HasMany<ProjectScreenPulse, $this> */
    public function screens(): HasMany
    {
        return $this->hasMany(ProjectScreenPulse::class);
    }

    /** @return HasMany<ProjectPulseRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ProjectPulseRevision::class);
    }
}
