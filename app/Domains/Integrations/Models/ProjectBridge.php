<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Models;

use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * إعداد الجسر الصادر إلى مشروع خارجي.
 *
 * @property int $id
 * @property int $project_id
 * @property string $driver
 * @property string $base_url
 * @property string $auth_mode
 * @property array<string, string>|null $credentials
 * @property bool $is_enabled
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 */
class ProjectBridge extends Model
{
    use BelongsToProject;

    public const MODE_SERVICE_ACCOUNT = 'service_account';

    public const MODE_BEARER = 'bearer';

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['credentials'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // `encrypted:array` لا `array`: السرّ يمرّ إلى موازين فيلزم فكّه،
            // لكنه لا يستريح في القرص صريحًا.
            'credentials' => 'encrypted:array',
            'is_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function secret(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** هل الإعداد مكتمل بما يكفي لمحاولة الاتصال. */
    public function isConfigured(): bool
    {
        if (! $this->is_enabled || $this->base_url === '') {
            return false;
        }

        return $this->auth_mode === self::MODE_BEARER
            ? $this->secret('token') !== null
            : $this->secret('email') !== null && $this->secret('password') !== null;
    }

    /**
     * حالة الجسر كما تُعرض.
     *
     * «متصل» يشترط نجاحًا حديثًا لا مجرّد غياب خطأ: جسرٌ آخر نجاحه أمس وأخفق
     * اليوم ليس متصلًا، وإعلانه كذلك يجعل المشغّل يثق برقم قديم.
     */
    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_enabled => 'موقوف',
            ! $this->isConfigured() => 'غير مكتمل',
            $this->last_error !== null => 'أخفق',
            $this->last_synced_at === null => 'لم يُجرَّب بعد',
            default => 'متصل',
        };
    }

    public function statusTone(): string
    {
        return match ($this->statusLabel()) {
            'متصل' => 'success',
            'أخفق' => 'danger',
            'موقوف' => 'neutral',
            default => 'warning',
        };
    }
}
