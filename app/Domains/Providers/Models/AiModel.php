<?php

declare(strict_types=1);

namespace App\Domains\Providers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $provider_id
 * @property string $name
 * @property string $display_name
 * @property bool $is_enabled
 * @property bool $is_default
 * @property numeric-string|null $input_cost_per_million
 * @property numeric-string|null $output_cost_per_million
 */
class AiModel extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
            'input_cost_per_million' => 'decimal:4',
            'output_cost_per_million' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }
}
