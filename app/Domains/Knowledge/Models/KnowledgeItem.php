<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * عنصر معرفة — وثيقة 06 §15.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $section_id
 * @property string $title
 * @property KnowledgeKind $kind
 * @property KnowledgeStatus $status
 * @property string|null $summary
 * @property string $body
 * @property int $version
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property Carbon|null $published_at
 * @property Carbon $updated_at
 */
class KnowledgeItem extends Model
{
    use BelongsToProject;
    use HasUlids;

    protected $guarded = [];

    /** @return array<int, string> */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => KnowledgeKind::class,
            'status' => KnowledgeStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProjectSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ProjectSection::class, 'section_id');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** @return BelongsToMany<KnowledgeTag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeTag::class, 'knowledge_item_tag');
    }

    /** @return HasMany<KnowledgeVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeVersion::class);
    }

    /** @return HasMany<KnowledgeSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(KnowledgeSource::class);
    }

    /** @param Builder<self> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', KnowledgeStatus::Published->value);
    }
}
