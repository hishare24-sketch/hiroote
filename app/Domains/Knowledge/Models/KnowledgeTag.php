<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Models;

use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string $slug
 */
class KnowledgeTag extends Model
{
    use BelongsToProject;

    protected $guarded = [];

    /** @return BelongsToMany<KnowledgeItem, $this> */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeItem::class, 'knowledge_item_tag');
    }
}
