<?php

declare(strict_types=1);

namespace App\Domains\Providers\Models;

use App\Domains\Providers\Enums\ProviderStatus;
use Database\Factories\AiProviderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property string $base_url
 * @property int $priority
 * @property bool $is_enabled
 * @property bool $is_active
 * @property ProviderStatus $status
 * @property int $consecutive_failures
 * @property Carbon|null $last_checked_at
 */
class AiProvider extends Model
{
    /** @use HasFactory<AiProviderFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    /** @var class-string<AiProviderFactory> */
    protected static string $factory = AiProviderFactory::class;

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
            'is_enabled' => 'boolean',
            'is_active' => 'boolean',
            'status' => ProviderStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    /**
     * @return HasMany<AiProviderCredential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(AiProviderCredential::class, 'provider_id');
    }

    /**
     * @return HasMany<AiProviderHealthCheck, $this>
     */
    public function healthChecks(): HasMany
    {
        return $this->hasMany(AiProviderHealthCheck::class, 'provider_id');
    }

    public function activeCredential(): ?AiProviderCredential
    {
        return $this->credentials()->where('is_active', true)->latest('id')->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public static function active(): ?self
    {
        return self::query()->where('is_active', true)->first();
    }

    /**
     * المرشح التالي للتحويل: أول مزود مفعل بأولوية أعلى ليس هو الحالي.
     */
    public static function nextCandidate(?self $excluding = null): ?self
    {
        $query = self::query()->enabled()->orderBy('priority');

        if ($excluding !== null) {
            $query->whereKeyNot($excluding->id);
        }

        return $query->first();
    }
}
