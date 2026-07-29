<?php

declare(strict_types=1);

namespace App\Domains\Providers\Models;

use App\Domains\Providers\Enums\ProviderSetting;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property bool $enabled
 */
class ProviderSettingValue extends Model
{
    protected $table = 'provider_settings';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /**
     * A missing row means the switch was never touched, so the enum's default
     * applies rather than a silent `false`.
     */
    public static function isEnabled(ProviderSetting $setting): bool
    {
        $row = self::query()->where('key', $setting->value)->first();

        return $row === null ? $setting->defaultEnabled() : $row->enabled;
    }

    /**
     * @param  list<ProviderSetting>  $settings
     * @return array<string, bool>
     */
    public static function map(array $settings): array
    {
        $stored = self::query()->pluck('enabled', 'key');

        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->value] = (bool) ($stored[$setting->value] ?? $setting->defaultEnabled());
        }

        return $result;
    }
}
