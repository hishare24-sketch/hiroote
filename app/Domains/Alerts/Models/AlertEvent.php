<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Enums\AlertComparison;
use App\Domains\Alerts\Enums\AlertEventStatus;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Enums\AlertSeverity;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * مرة تجاوزٍ واحدة لقاعدة — تبقى مفتوحة حتى يعود المؤشر تحت الحد.
 *
 * @property int $id
 * @property int $project_id
 * @property int $alert_rule_id
 * @property AlertEventStatus $status
 * @property AlertSeverity $severity
 * @property AlertMetric $metric
 * @property AlertComparison $comparison
 * @property float $threshold
 * @property float $observed_value
 * @property float $peak_value
 * @property int $window_minutes
 * @property array<string, mixed>|null $context
 * @property Carbon $triggered_at
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $resolved_at
 * @property-read AlertRule|null $rule
 * @property-read User|null $acknowledger
 */
class AlertEvent extends Model
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
            'status' => AlertEventStatus::class,
            'severity' => AlertSeverity::class,
            'metric' => AlertMetric::class,
            'comparison' => AlertComparison::class,
            'threshold' => 'float',
            'observed_value' => 'float',
            'peak_value' => 'float',
            'context' => 'array',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AlertRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return HasMany<NotificationDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    /** @param Builder<self> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [
            AlertEventStatus::Triggered->value,
            AlertEventStatus::Acknowledged->value,
        ]);
    }
}
