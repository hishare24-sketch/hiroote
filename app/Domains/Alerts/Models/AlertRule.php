<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Enums\AlertAction;
use App\Domains\Alerts\Enums\AlertComparison;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Enums\AlertSeverity;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * قاعدة تنبيه — وثيقة 06 §11.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $description
 * @property AlertMetric $metric
 * @property AlertComparison $comparison
 * @property float $threshold
 * @property int $window_minutes
 * @property AlertSeverity $severity
 * @property bool $is_enabled
 * @property int $cooldown_minutes
 * @property AlertAction $auto_action
 * @property list<int>|null $section_ids
 * @property list<int>|null $provider_ids
 * @property Carbon|null $last_evaluated_at
 * @property Carbon|null $last_triggered_at
 * @property float|null $last_value
 * @property int $trigger_count
 * @property int|null $created_by
 */
class AlertRule extends Model
{
    use BelongsToProject;
    use HasUlids;
    use SoftDeletes;

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
            'metric' => AlertMetric::class,
            'comparison' => AlertComparison::class,
            'severity' => AlertSeverity::class,
            'auto_action' => AlertAction::class,
            'threshold' => 'float',
            'last_value' => 'float',
            'is_enabled' => 'boolean',
            'section_ids' => 'array',
            'provider_ids' => 'array',
            'last_evaluated_at' => 'datetime',
            'last_triggered_at' => 'datetime',
        ];
    }

    /** @return HasMany<AlertRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(AlertRecipient::class);
    }

    /** @return HasMany<AlertEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param Builder<self> $query */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * هل ما زالت القاعدة في فترة التهدئة بعد آخر تفعيل.
     *
     * تُقاس من آخر تفعيل لا من آخر حل: قاعدةٌ تتذبذب حول حدّها ترسل إشعارًا كل
     * دقيقة لولا هذا، فيتعلّم المستلم تجاهلها.
     */
    public function isCoolingDown(): bool
    {
        if ($this->last_triggered_at === null || $this->cooldown_minutes === 0) {
            return false;
        }

        return $this->last_triggered_at->addMinutes($this->cooldown_minutes)->isFuture();
    }
}
