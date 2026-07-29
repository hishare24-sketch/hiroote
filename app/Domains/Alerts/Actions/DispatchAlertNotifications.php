<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Actions;

use App\Domains\Alerts\Enums\DeliveryStatus;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Alerts\Models\NotificationDelivery;

/**
 * يسجّل إيصالًا لكل مستلم عند فتح حدث.
 *
 * القناة غير المربوطة تُسجَّل «معلّقة» مع سبب التعليق ولا تُسجَّل «وصلت»: سجل
 * إرسالٍ يكذب أسوأ من سجل ناقص، لأنه يُقنع المشغّل بأن أحدًا أُبلغ.
 */
final readonly class DispatchAlertNotifications
{
    public function handle(AlertEvent $event, AlertRule $rule): void
    {
        foreach ($rule->recipients as $recipient) {
            $channel = $recipient->channel;
            $wired = $channel->isWired();

            NotificationDelivery::query()->create([
                'alert_event_id' => $event->id,
                'user_id' => $recipient->user_id,
                'channel' => $channel,
                'target' => $recipient->target(),
                'status' => $wired ? DeliveryStatus::Delivered : DeliveryStatus::Pending,
                'note' => $channel->pendingReason(),
                'delivered_at' => $wired ? now() : null,
            ]);
        }
    }
}
