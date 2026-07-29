<?php

declare(strict_types=1);

namespace App\Domains\Providers\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Providers\Enums\FailoverReason;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $from_provider_id
 * @property int|null $to_provider_id
 * @property FailoverReason $reason
 * @property int|null $triggered_by
 * @property array<string, mixed>|null $details
 * @property Carbon $created_at
 */
class AiFailoverEvent extends Model
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
            'reason' => FailoverReason::class,
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function fromProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'from_provider_id');
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function toProvider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'to_provider_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
