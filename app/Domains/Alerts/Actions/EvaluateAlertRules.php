<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Actions;

use App\Domains\Alerts\DTOs\MetricReading;
use App\Domains\Alerts\Enums\AlertEventStatus;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Alerts\Services\MetricReader;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * يقيس كل قاعدة مفعَّلة في مشروع ويفتح أو يغلق أحداثها.
 *
 * الحدث يُفتح مرة واحدة ويبقى مفتوحًا حتى يعود المؤشر تحت الحد: تكرار الفتح
 * كل تقييم يحوّل السجل إلى ضجيج يخفي متى بدأ العطل فعلًا. والحدث المفتوح
 * يتتبّع أسوأ قيمة بلغها، لأن «تجاوز ٢٢٪» أقل نفعًا من «بلغ ٤١٪».
 */
final readonly class EvaluateAlertRules
{
    public function __construct(
        private MetricReader $reader,
        private DispatchAlertNotifications $notify,
    ) {}

    /**
     * @return array{evaluated: int, triggered: int, resolved: int, skipped: int}
     */
    public function handle(Project $project): array
    {
        $summary = ['evaluated' => 0, 'triggered' => 0, 'resolved' => 0, 'skipped' => 0];

        $rules = AlertRule::query()
            ->forProject($project)
            ->enabled()
            ->with('recipients.user')
            ->get();

        foreach ($rules as $rule) {
            $reading = $this->reader->forRule($rule, $project);

            if (! $reading->isMeasurable()) {
                // لا تُحدَّث `last_evaluated_at`: القاعدة لم تُقَس، والادعاء بأنها
                // قيست يخفي أن أحدًا لا يراقب هذا المؤشر.
                $summary['skipped']++;

                continue;
            }

            $summary['evaluated']++;
            $breached = $rule->comparison->holds((float) $reading->value, $rule->threshold);
            $open = $this->openEvent($rule);

            DB::transaction(function () use ($rule, $reading, $breached, $open, &$summary): void {
                $rule->forceFill([
                    'last_evaluated_at' => now(),
                    'last_value' => $reading->value,
                ])->save();

                if (! $breached) {
                    if ($open !== null) {
                        $open->forceFill([
                            'status' => AlertEventStatus::Resolved,
                            'observed_value' => $reading->value,
                            'resolved_at' => now(),
                        ])->save();

                        $summary['resolved']++;
                    }

                    return;
                }

                if ($open !== null) {
                    $this->deepen($open, $reading, $rule);

                    return;
                }

                if ($rule->isCoolingDown()) {
                    return;
                }

                $this->open($rule, $reading);
                $summary['triggered']++;
            });
        }

        return $summary;
    }

    private function openEvent(AlertRule $rule): ?AlertEvent
    {
        return AlertEvent::query()
            ->where('alert_rule_id', $rule->id)
            ->open()
            ->latest('triggered_at')
            ->first();
    }

    private function open(AlertRule $rule, MetricReading $reading): void
    {
        $event = AlertEvent::query()->create([
            'project_id' => $rule->project_id,
            'alert_rule_id' => $rule->id,
            'status' => AlertEventStatus::Triggered,
            'severity' => $rule->severity,
            'metric' => $rule->metric,
            'comparison' => $rule->comparison,
            'threshold' => $rule->threshold,
            'observed_value' => $reading->value,
            'peak_value' => $reading->value,
            'window_minutes' => $rule->window_minutes,
            'context' => [
                'sample' => $reading->sampleLabel,
                'sample_size' => $reading->sampleSize,
                ...$reading->context,
            ],
            'triggered_at' => now(),
        ]);

        $rule->forceFill([
            'last_triggered_at' => now(),
            'trigger_count' => $rule->trigger_count + 1,
        ])->save();

        $this->notify->handle($event, $rule);
    }

    /** يحدّث الحدث المفتوح ويحفظ أسوأ قيمة بلغها المؤشر. */
    private function deepen(AlertEvent $event, MetricReading $reading, AlertRule $rule): void
    {
        $value = (float) $reading->value;
        $worse = $rule->comparison->holds($value, $event->peak_value);

        $event->forceFill([
            'observed_value' => $value,
            'peak_value' => $worse ? $value : $event->peak_value,
        ])->save();
    }
}
