<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Actions;

use App\Domains\Alerts\Enums\AlertChannel;
use App\Domains\Alerts\Enums\DeliveryStatus;
use App\Domains\Alerts\Mail\AlertOpened;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Alerts\Models\NotificationDelivery;
use App\Domains\Alerts\Models\ProjectWebhook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * يرسل التنبيه ويسجّل إيصالًا لكل مستلم.
 *
 * **«وصل» تعني أنه أُرسل فعلًا.** القناة غير المربوطة تُسجَّل «معلّقة» بسببها،
 * والإرسالُ الذي يُخفق يُسجَّل «أخفق» بنصّ خطئه — سجل إرسالٍ يكذب أسوأ من سجل
 * ناقص، لأنه يُقنع المشغّل بأن أحدًا أُبلغ.
 */
final readonly class DispatchAlertNotifications
{
    public function __construct(private SendAlertWebhook $webhooks) {}

    public function handle(AlertEvent $event, AlertRule $rule): void
    {
        foreach ($rule->recipients as $recipient) {
            $channel = $recipient->channel;
            $target = $recipient->target();

            if (! $channel->isWired()) {
                $this->record($event, $recipient->user_id, $channel, $target, DeliveryStatus::Pending, $channel->pendingReason());

                continue;
            }

            if ($channel === AlertChannel::InApp) {
                // «داخل اللوحة» تصل بمجرّد وجود الصفّ: الشاشة تقرؤه.
                $this->record($event, $recipient->user_id, $channel, $target, DeliveryStatus::Delivered, null);

                continue;
            }

            if ($channel === AlertChannel::Webhook) {
                $webhook = ProjectWebhook::query()->where('project_id', $event->project_id)->first();

                if (! $webhook instanceof ProjectWebhook || ! $webhook->isUsable()) {
                    $this->record($event, $recipient->user_id, $channel, $target, DeliveryStatus::Pending, 'لا وجهة مضبوطة لهذا المشروع.');

                    continue;
                }

                $outcome = $this->webhooks->handle($webhook, $event, $rule);

                $this->record(
                    $event,
                    $recipient->user_id,
                    $channel,
                    $webhook->url,
                    $outcome['ok'] ? DeliveryStatus::Delivered : DeliveryStatus::Failed,
                    $outcome['error'],
                );

                continue;
            }

            // بريدٌ بلا عنوان لا يُرسَل ولا يُعدّ واصلًا: `—` عنوانٌ لا وجود له.
            if (! filter_var($target, FILTER_VALIDATE_EMAIL)) {
                $this->record($event, $recipient->user_id, $channel, $target, DeliveryStatus::Failed, 'لا عنوان بريد صالح لهذا المستلم.');

                continue;
            }

            try {
                Mail::to($target)->send(new AlertOpened($event, $rule));
                $this->record($event, $recipient->user_id, $channel, $target, DeliveryStatus::Delivered, null);
            } catch (Throwable $exception) {
                // إخفاق مستلمٍ لا يمنع البقية، ولا يُسقط فتح الحدث نفسه:
                // تنبيهٌ لم يُفتح لأن بريدًا أخفق يخسر الإنذار كله.
                Log::warning('تعذّر إرسال بريد التنبيه', ['target' => $target, 'error' => $exception->getMessage()]);
                $this->record($event, $recipient->user_id, $channel, $target, DeliveryStatus::Failed, $exception->getMessage());
            }
        }
    }

    private function record(
        AlertEvent $event,
        ?int $userId,
        AlertChannel $channel,
        string $target,
        DeliveryStatus $status,
        ?string $note,
    ): void {
        NotificationDelivery::query()->create([
            'alert_event_id' => $event->id,
            'user_id' => $userId,
            'channel' => $channel,
            'target' => $target,
            'status' => $status,
            'note' => $note,
            'delivered_at' => $status === DeliveryStatus::Delivered ? now() : null,
        ]);
    }
}
