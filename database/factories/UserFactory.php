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
            'is_platform_admin' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * كل مستخدم يُنشأ عضوًا في المشروع الافتراضي بدوره.
     *
     * الدور بلا عضوية لا صلاحية له (ADR-0003 §3)، فمستخدمٌ بلا مشروع يجعل كل
     * اختبار صلاحية يفشل لسبب لا علاقة له بما يقيسه.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->is_platform_admin) {
                return;
            }

            ProjectFactory::default()->members()->syncWithoutDetaching([
                $user->id => ['role' => $user->role->value],
            ]);
        });
    }

    /** مدير منصة: عضوية ضمنية في كل مشروع. */
    public function platformAdmin(): static
    {
        return $this->state(fn (): array => ['is_platform_admin' => true]);
    }

    /** بلا عضوية في أي مشروع — لاختبار الحرمان لا الصلاحية. */
    public function withoutProject(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->projects()->detach();
        });
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
