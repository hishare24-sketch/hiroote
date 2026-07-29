<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * مشروع تابع للشركة — ADR-0003.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $api_base_url
 * @property bool $is_active
 * @property int $sort_order
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use HasUlids;
    use SoftDeletes;

    /** @var class-string<ProjectFactory> */
    protected static string $factory = ProjectFactory::class;

    protected $guarded = [];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * دور العضوية المسجّل لهذا المستخدم، أو null إن لم يكن عضوًا.
     *
     * لا يعرف شيئًا عن مدير المنصة — ذلك يُحلّ في `User::roleIn()` وحده حتى لا
     * يكون للدور الفعّال موضعان يُحسب فيهما.
     */
    public function membershipRoleFor(User $user): ?Role
    {
        $pivot = $this->members()->where('users.id', $user->id)->first()?->pivot;

        if ($pivot === null) {
            return null;
        }

        /** @var string $role */
        $role = $pivot->getAttribute('role');

        return Role::tryFrom($role);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
