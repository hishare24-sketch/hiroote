<?php

declare(strict_types=1);

namespace App\Domains\Administration\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An entry in the tamper-evident audit trail (وثيقة 01 §5-ط).
 *
 * Postgres triggers already reject UPDATE/DELETE/TRUNCATE. The guards below
 * exist so the violation surfaces as a clear application error during
 * development instead of a raw driver exception at runtime.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property string|null $actor_role
 * @property string $action
 * @property string|null $auditable_type
 * @property string|null $auditable_id
 * @property string|null $section
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $reason
 * @property string|null $request_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeForAction(Builder $query, string $action): void
    {
        $query->where('action', $action);
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('audit_logs entries are immutable and cannot be updated.');
        });

        static::deleting(static function (): never {
            throw new LogicException('audit_logs entries are immutable and cannot be deleted.');
        });
    }
}
