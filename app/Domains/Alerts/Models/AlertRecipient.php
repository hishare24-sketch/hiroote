<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Enums\AlertChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مستلم واحد لقاعدة واحدة عبر قناة واحدة.
 *
 * @property int $id
 * @property int $alert_rule_id
 * @property int|null $user_id
 * @property string|null $email
 * @property AlertChannel $channel
 * @property-read User|null $user
 */
class AlertRecipient extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['channel' => AlertChannel::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AlertRule, $this> */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }

    /** العنوان الذي يُرسل إليه فعلًا: بريد العضو أو البريد الخارجي. */
    public function target(): string
    {
        return $this->user->email ?? $this->email ?? '—';
    }

    public function displayName(): string
    {
        return $this->user->name ?? $this->email ?? '—';
    }
}
