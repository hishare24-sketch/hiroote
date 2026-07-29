<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Models;

use App\Domains\Knowledge\Enums\SourceKind;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $section_id
 * @property int|null $knowledge_item_id
 * @property SourceKind $kind
 * @property string $label
 * @property string|null $url
 * @property string|null $note
 * @property string|null $file_path
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property Carbon $created_at
 */
class KnowledgeSource extends Model
{
    use BelongsToProject;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => SourceKind::class];
    }
}
