<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Enums\AlertChannel;
use App\Domains\Alerts\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * محاولة إيصال واحدة — لمستلم واحد عبر قناة واحدة.
 *
 * @property int $id
 * @property int $alert_event_id
 * @property int|null $user_id
 * @property AlertChannel $channel
 * @property string $target
 * @property DeliveryStatus $status
 * @property string|null $note
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 */
class NotificationDelivery extends Model
{
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => AlertChannel::class,
            'status' => DeliveryStatus::class,
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AlertEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(AlertEvent::class, 'alert_event_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
