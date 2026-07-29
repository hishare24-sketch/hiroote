<?php

declare(strict_types=1);

namespace App\Domains\Administration\Models;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Administration\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * An operator of the Hiroote AI admin panel — never a Hi-Share end user.
 * Hi-Share identities stay in Hi-Share (وثيقة 01 §6).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /** @var class-string<UserFactory> */
    protected static string $factory = UserFactory::class;

    use Notifiable;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * A deactivated account keeps its history but loses every permission, so
     * revoking access never requires deleting the audit trail behind it.
     */
    public function hasPermission(Permission $permission): bool
    {
        return $this->is_active && $this->role->grants($permission);
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        if (! $this->is_active) {
            return [];
        }

        return array_map(
            static fn (Permission $permission): string => $permission->value,
            $this->role->permissions(),
        );
    }
}
