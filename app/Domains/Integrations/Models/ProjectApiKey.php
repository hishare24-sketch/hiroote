<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * مفتاح وصول لمشروع واحد.
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $prefix
 * @property string $hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by
 * @property-read User|null $author
 */
class ProjectApiKey extends Model
{
    use BelongsToProject;

    /** بادئة ظاهرة تميّز مفاتيح هاي روت عن غيرها في سجلات العميل. */
    public const TOKEN_PREFIX = 'hrt_';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * مفتاح جديد: النص الصريح يُعاد مرة واحدة، والمخزَّن تجزئته.
     *
     * @return array{token: string, prefix: string, hash: string}
     */
    public static function mint(): array
    {
        $secret = Str::random(48);
        $token = self::TOKEN_PREFIX.$secret;

        return [
            'token' => $token,
            'prefix' => mb_substr($token, 0, 12),
            'hash' => hash('sha256', $token),
        ];
    }

    /** @param Builder<self> $query */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('revoked_at')
            ->where(function (Builder $inner): void {
                $inner->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** سبب الرفض — يُعرض للمشغّل في اللوحة لا للعميل في الرد. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->revoked_at !== null => 'مُبطَل',
            $this->expires_at !== null && $this->expires_at->isPast() => 'منتهٍ',
            default => 'فعّال',
        };
    }
}
