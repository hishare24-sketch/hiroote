<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** @var class-string<User> */
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::SupportAgent,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn (array $attributes): array => ['role' => $role]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => ['email_verified_at' => null]);
    }
}
