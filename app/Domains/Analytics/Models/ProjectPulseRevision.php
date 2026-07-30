<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * القيمة التي أُزيحت حين أُعيد إرسال يومٍ سبق إرساله.
 *
 * @property int $id
 * @property int $project_pulse_id
 * @property int $revision
 * @property array<string, mixed> $superseded_values
 * @property Carbon $replaced_at
 */
class ProjectPulseRevision extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'superseded_values' => 'array',
            'replaced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProjectPulse, $this> */
    public function pulse(): BelongsTo
    {
        return $this->belongsTo(ProjectPulse::class, 'project_pulse_id');
    }
}
