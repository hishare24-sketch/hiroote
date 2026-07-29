<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Models;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * شاشة داخل قسم، بعناصرها وإجراءاتها وحالاتها — وثيقة 06 §15.
 *
 * @property int $id
 * @property int $project_id
 * @property int $section_id
 * @property string $name
 * @property string|null $key
 * @property string|null $path
 * @property string|null $description
 * @property string|null $image_path
 * @property string|null $image_mime
 * @property int|null $image_size
 * @property list<string>|null $elements
 * @property list<string>|null $actions
 * @property list<string>|null $states
 * @property int $sort_order
 * @property Carbon $updated_at
 */
class KnowledgeScreen extends Model
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
        return ['elements' => 'array', 'actions' => 'array', 'states' => 'array'];
    }

    /** @return BelongsTo<ProjectSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ProjectSection::class, 'section_id');
    }

    /** الرابط العام للصورة، أو null إن لم تُرفع. */
    public function imageUrl(): ?string
    {
        return $this->image_path === null
            ? null
            : Storage::disk('public')->url($this->image_path);
    }
}
