<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مشاهدات شاشةٍ ونقراتها في يومٍ واحد.
 *
 * @property int $id
 * @property int $project_pulse_id
 * @property string $screen_key
 * @property int|null $views
 * @property int|null $clicks
 */
class ProjectScreenPulse extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return BelongsTo<ProjectPulse, $this> */
    public function pulse(): BelongsTo
    {
        return $this->belongsTo(ProjectPulse::class, 'project_pulse_id');
    }
}
