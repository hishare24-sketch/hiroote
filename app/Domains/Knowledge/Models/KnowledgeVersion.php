<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * لقطة ثابتة من عنصر معرفة — وثيقة 06 §15 (المقارنة والرجوع).
 *
 * @property int $id
 * @property int $knowledge_item_id
 * @property int $version
 * @property string $title
 * @property string|null $summary
 * @property string $body
 * @property KnowledgeStatus $status
 * @property int|null $changed_by
 * @property string|null $change_note
 * @property Carbon $created_at
 */
class KnowledgeVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => KnowledgeStatus::class, 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return BelongsTo<KnowledgeItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(KnowledgeItem::class, 'knowledge_item_id');
    }
}
