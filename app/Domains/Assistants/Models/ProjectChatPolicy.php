<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Models;

use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;

/**
 * إذن الشات في مشروع — **حوكمةٌ لا محتوى**.
 *
 * هاي روت لا يخزّن رسالةً واحدة من رسائل مستخدمي المشروع: هويّاتهم ومحادثاتهم
 * تبقى عنده (وثيقة 01 §6). ما يُحفظ هنا هو ما سمح به المالك، ويقرأه المشروع
 * عبر جسر الوارد ليطبّقه في واجهته.
 *
 * @property int $id
 * @property int $project_id
 * @property bool $is_enabled
 * @property list<string> $channel_kinds
 * @property list<string> $scopes
 * @property bool $assistant_participates
 * @property bool $attachments_allowed
 * @property int $retention_days
 */
class ProjectChatPolicy extends Model
{
    use BelongsToProject;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'channel_kinds' => 'array',
            'scopes' => 'array',
            'assistant_participates' => 'boolean',
            'attachments_allowed' => 'boolean',
            'retention_days' => 'integer',
        ];
    }

    public function allows(string $kind): bool
    {
        return $this->is_enabled && in_array($kind, $this->channel_kinds, strict: true);
    }

    /** صفرٌ في الحفظ يعني **بلا حدّ** لا «لا تحفظ» — والفرق بينهما كامل. */
    public function keepsForever(): bool
    {
        return $this->retention_days === 0;
    }
}
