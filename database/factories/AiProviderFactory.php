<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Providers\Enums\ProviderStatus;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    /** @var class-string<AiProvider> */
    protected $model = AiProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'slug' => fake()->unique()->slug(2),
            'base_url' => 'https://api.example.test/v1',
            'priority' => fake()->unique()->numberBetween(1, 1000),
            'is_enabled' => true,
            'is_active' => false,
            'status' => ProviderStatus::Unknown,
            'consecutive_failures' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_enabled' => true,
            'is_active' => true,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_enabled' => false,
            'is_active' => false,
        ]);
    }
}
