<?php

declare(strict_types=1);

namespace App\Domains\Administration\Models;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Administration\Enums\Role;
use App\Domains\Projects\Models\Project;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property Role $role الدور الافتراضي عند الإضافة لمشروع؛ النافذ هو دور العضوية.
 * @property bool $is_platform_admin
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property Carbon|null $email_verified_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'is_platform_admin'])]
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
            'is_platform_admin' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * الدور النافذ داخل مشروع بعينه — ADR-0003 §3.
     *
     * مدير المنصة يُعامَل كعضو بدور مدير النظام في كل مشروع. هذه عضوية ضمنية
     * لا تجاوز للبوابة: الصلاحية تبقى تُقرأ من `Role::permissions()` وحدها.
     */
    public function roleIn(?Project $project): ?Role
    {
        if (! $this->is_active) {
            return null;
        }

        if ($this->is_platform_admin) {
            return Role::SystemAdmin;
        }

        return $project?->membershipRoleFor($this);
    }

    /**
     * A deactivated account keeps its history but loses every permission, so
     * revoking access never requires deleting the audit trail behind it.
     *
     * بلا مشروع نشط لا صلاحية تشغيلية: عدم الانتماء منعٌ، لا سماحٌ افتراضي.
     */
    public function hasPermission(Permission $permission, ?Project $project): bool
    {
        return $this->roleIn($project)?->grants($permission) ?? false;
    }

    /**
     * @return list<string>
     */
    public function permissionNames(?Project $project): array
    {
        $role = $this->roleIn($project);

        if ($role === null) {
            return [];
        }

        return array_map(
            static fn (Permission $permission): string => $permission->value,
            $role->permissions(),
        );
    }
}
