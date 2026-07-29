<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Models;

use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * وجهة دفع التنبيهات في مشروع.
 *
 * @property int $id
 * @property int $project_id
 * @property string $url
 * @property string $secret
 * @property bool $is_enabled
 * @property Carbon|null $last_delivered_at
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 */
class ProjectWebhook extends Model
{
    use BelongsToProject;

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['secret'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'is_enabled' => 'boolean',
            'last_delivered_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->is_enabled && $this->url !== '' && $this->secret !== '';
    }

    /**
     * حالة الوجهة كما تُعرض.
     *
     * «تعمل» تشترط تسليمًا ناجحًا بعد آخر إخفاق، لا غيابَ إخفاق: وجهةٌ نجحت
     * أمس وأخفقت اليوم ليست عاملة، وإعلانها كذلك يُقنع المشغّل بأن إنذاره يصل.
     */
    public function statusLabel(): string
    {
        return match (true) {
            ! $this->is_enabled => 'موقوفة',
            ! $this->isUsable() => 'غير مكتملة',
            $this->last_error !== null => 'أخفقت',
            $this->last_delivered_at === null => 'لم تُجرَّب بعد',
            default => 'تعمل',
        };
    }
}
