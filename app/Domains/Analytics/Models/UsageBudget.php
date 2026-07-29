<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $scope
 * @property string|null $scope_key
 * @property numeric-string $monthly_limit
 * @property string $currency
 * @property int $warn_at_percent
 * @property int $critical_at_percent
 * @property bool $hard_stop
 */
class UsageBudget extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['monthly_limit' => 'decimal:2', 'hard_stop' => 'boolean'];
    }

    /** الميزانية العامة للمنصة — المرجع حين لا توجد ميزانية قسم أو مزود. */
    public static function platform(): ?self
    {
        return self::query()->where('scope', 'platform')->whereNull('scope_key')->first();
    }
}
