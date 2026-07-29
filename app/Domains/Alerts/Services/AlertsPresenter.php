<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Services;

use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRecipient;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Alerts\Models\NotificationDelivery;
use App\Domains\Projects\Models\Project;
use App\Support\Enums\EnumPayload;
use Illuminate\Support\Collection;

/**
 * يحوّل القواعد والأحداث إلى عقد الواجهة.
 *
 * كل صف قاعدة يحمل قيمتها الآن — لا آخر قيمة محفوظة وحدها: قاعدةٌ لم تُقيَّم
 * منذ يومين تعرض رقمًا عمره يومان، والمشغّل يقرؤه على أنه الحاضر.
 */
class AlertsPresenter
{
    public function __construct(private readonly MetricReader $reader) {}

    /**
     * @param  Collection<int, AlertRule>  $rules
     * @return list<array<string, mixed>>
     */
    public function rules(Collection $rules, Project $project): array
    {
        $rows = [];

        foreach ($rules as $rule) {
            $reading = $this->reader->forRule($rule, $project);
            $value = $reading->value;

            $rows[] = [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => $rule->description,
                'metric' => EnumPayload::from($rule->metric),
                'metric_hint' => $rule->metric->hint(),
                'unit' => $rule->metric->unit()->value,
                'windowed' => $rule->metric->isWindowed(),
                'supports_sections' => $rule->metric->supportsSectionScope(),
                'comparison' => [
                    'value' => $rule->comparison->value,
                    'label' => $rule->comparison->label(),
                ],
                'threshold' => $rule->threshold,
                'window_minutes' => $rule->window_minutes,
                'cooldown_minutes' => $rule->cooldown_minutes,
                'severity' => EnumPayload::from($rule->severity),
                'auto_action' => [
                    ...EnumPayload::from($rule->auto_action),
                    'awaits' => $rule->auto_action->awaitsImplementation(),
                ],
                'is_enabled' => $rule->is_enabled,
                'section_ids' => $rule->section_ids ?? [],
                'provider_ids' => $rule->provider_ids ?? [],
                'trigger_count' => $rule->trigger_count,
                'last_evaluated_at' => $rule->last_evaluated_at?->toIso8601String(),
                'last_triggered_at' => $rule->last_triggered_at?->toIso8601String(),
                'cooling_down' => $rule->isCoolingDown(),
                'current_value' => $value,
                'current_sample' => $reading->sampleLabel,
                'breached' => $value !== null && $rule->comparison->holds($value, $rule->threshold),
                'recipients' => $rule->recipients
                    ->map(fn (AlertRecipient $recipient): array => [
                        'user_id' => $recipient->user_id,
                        'email' => $recipient->email,
                        'name' => $recipient->displayName(),
                        'channel' => EnumPayload::from($recipient->channel),
                        'wired' => $recipient->channel->isWired(),
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, AlertEvent>  $events
     * @return list<array<string, mixed>>
     */
    public function events(Collection $events): array
    {
        $rows = [];

        foreach ($events as $event) {
            $rows[] = [
                'id' => $event->id,
                'rule_id' => $event->alert_rule_id,
                'rule_name' => $event->rule->name ?? 'قاعدة محذوفة',
                'status' => EnumPayload::from($event->status),
                'severity' => EnumPayload::from($event->severity),
                'metric' => EnumPayload::from($event->metric),
                'unit' => $event->metric->unit()->value,
                'comparison' => $event->comparison->label(),
                'threshold' => $event->threshold,
                'observed_value' => $event->observed_value,
                'peak_value' => $event->peak_value,
                'window_minutes' => $event->window_minutes,
                'sample' => is_string($event->context['sample'] ?? null)
                    ? $event->context['sample']
                    : null,
                'triggered_at' => $event->triggered_at->toIso8601String(),
                'resolved_at' => $event->resolved_at?->toIso8601String(),
                'acknowledged_by' => $event->acknowledger?->name,
                'deliveries' => $event->deliveries
                    ->map(fn (NotificationDelivery $delivery): array => [
                        'channel' => EnumPayload::from($delivery->channel),
                        'target' => $delivery->target,
                        'status' => EnumPayload::from($delivery->status),
                        'note' => $delivery->note,
                    ])
                    ->values()
                    ->all(),
            ];
        }

        return $rows;
    }

    /**
     * خيارات المؤشرات مجمّعة بعائلتها لمنشئ القاعدة.
     *
     * @return list<array<string, mixed>>
     */
    public function metricOptions(): array
    {
        $options = [];

        foreach (AlertMetric::cases() as $metric) {
            $options[] = [
                'value' => $metric->value,
                'label' => $metric->label(),
                'hint' => $metric->hint(),
                'family' => $metric->family()->value,
                'family_label' => $metric->family()->label(),
                'unit' => $metric->unit()->value,
                'unit_label' => $metric->unit()->label(),
                'ceiling' => $metric->unit()->ceiling(),
                'windowed' => $metric->isWindowed(),
                'supports_sections' => $metric->supportsSectionScope(),
                'suggested_comparison' => $metric->suggestedComparison()->value,
                'suggested_threshold' => $metric->suggestedThreshold(),
            ];
        }

        return $options;
    }
}
