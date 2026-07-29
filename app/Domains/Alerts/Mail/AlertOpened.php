<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Mail;

use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * بريد فتح التنبيه.
 *
 * يحمل الرقم المرصود والحدَّ معًا: رسالةٌ تقول «تجاوزت القاعدة حدّها» بلا
 * رقمين تُجبر المستلم على فتح اللوحة ليعرف إن كان التجاوز شعرةً أم ضِعفًا.
 */
class AlertOpened extends Mailable
{
    public function __construct(
        public readonly AlertEvent $event,
        public readonly AlertRule $rule,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->event->severity->label()}] {$this->rule->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.alerts.opened',
            with: [
                'ruleName' => $this->rule->name,
                'severity' => $this->event->severity->label(),
                'metric' => $this->event->metric->label(),
                'observed' => $this->event->observed_value,
                'threshold' => $this->event->threshold,
                'window' => $this->event->window_minutes,
                'project' => $this->rule->project?->name,
            ],
        );
    }
}
