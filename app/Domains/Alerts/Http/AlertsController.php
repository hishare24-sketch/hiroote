<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Http;

use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Actions\EvaluateAlertRules;
use App\Domains\Alerts\Actions\ResolveAlertEvent;
use App\Domains\Alerts\Actions\SaveAlertRule;
use App\Domains\Alerts\Enums\AlertAction;
use App\Domains\Alerts\Enums\AlertChannel;
use App\Domains\Alerts\Enums\AlertComparison;
use App\Domains\Alerts\Enums\AlertEventStatus;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Enums\AlertSeverity;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Alerts\Services\AlertsPresenter;
use App\Domains\Alerts\Services\MetricReader;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Projects\Services\CurrentProject;
use App\Domains\Providers\Models\AiProvider;
use App\Http\Controllers\Controller;
use App\Support\Http\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة التنبيهات — وثيقة 06 §11.
 */
class AlertsController extends Controller
{
    public function __construct(
        private readonly CurrentProject $current,
        private readonly AlertsPresenter $presenter,
        private readonly MetricReader $reader,
    ) {}

    public function index(): Response
    {
        $project = $this->current->require();

        $rules = AlertRule::query()
            ->forProject($project)
            ->with(['recipients.user:id,name,email'])
            ->orderByDesc('is_enabled')
            ->orderBy('name')
            ->get();

        $events = AlertEvent::query()
            ->forProject($project)
            ->with(['rule:id,name', 'acknowledger:id,name', 'deliveries'])
            ->orderByRaw("CASE WHEN status = 'resolved' THEN 1 ELSE 0 END")
            ->orderByDesc('triggered_at')
            ->limit(40)
            ->get();

        return Inertia::render('Alerts/Index', [
            'systemStatus' => SystemStatus::current(),
            'project' => ['id' => $project->id, 'name' => $project->name],
            'rules' => $this->presenter->rules($rules, $project),
            'events' => $this->presenter->events($events),
            'metrics' => $this->presenter->metricOptions(),
            'options' => [
                'comparisons' => array_map(
                    fn (AlertComparison $c): array => [
                        'value' => $c->value,
                        'label' => $c->label(),
                    ],
                    AlertComparison::cases(),
                ),
                'severities' => array_map(
                    fn (AlertSeverity $s): array => [
                        'value' => $s->value,
                        'label' => $s->label(),
                        'tone' => $s->tone(),
                    ],
                    AlertSeverity::cases(),
                ),
                'channels' => array_map(
                    fn (AlertChannel $c): array => [
                        'value' => $c->value,
                        'label' => $c->label(),
                        'wired' => $c->isWired(),
                        'pending_reason' => $c->pendingReason(),
                    ],
                    AlertChannel::cases(),
                ),
                'actions' => array_map(
                    fn (AlertAction $a): array => [
                        'value' => $a->value,
                        'label' => $a->label(),
                        'awaits' => $a->awaitsImplementation(),
                    ],
                    AlertAction::cases(),
                ),
                'sections' => ProjectSection::query()
                    ->forProject($project)
                    ->ordered()
                    ->get(['id', 'name'])
                    ->map(fn (ProjectSection $s): array => ['id' => $s->id, 'name' => $s->name])
                    ->values()
                    ->all(),
                'providers' => AiProvider::query()
                    ->orderBy('priority')
                    ->get(['id', 'name'])
                    ->map(fn (AiProvider $p): array => ['id' => $p->id, 'name' => $p->name])
                    ->values()
                    ->all(),
                'members' => $project->members()
                    ->orderBy('name')
                    ->get(['users.id', 'users.name', 'users.email'])
                    ->map(fn (User $u): array => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function store(Request $request, SaveAlertRule $action): RedirectResponse
    {
        $project = $this->current->require();
        $validated = $this->validated($request);

        $action->handle($project, $validated['attributes'], $validated['recipients']);

        return back()->with('success', 'أُضيفت قاعدة التنبيه.');
    }

    public function update(Request $request, AlertRule $rule, SaveAlertRule $action): RedirectResponse
    {
        $project = $this->current->require();
        abort_unless($rule->project_id === $project->id, 404);

        $validated = $this->validated($request);

        $action->handle($project, $validated['attributes'], $validated['recipients'], $rule);

        return back()->with('success', 'حُفظت قاعدة التنبيه.');
    }

    public function destroy(AlertRule $rule): RedirectResponse
    {
        abort_unless($rule->project_id === $this->current->require()->id, 404);

        $rule->delete();

        return back()->with('success', 'حُذفت قاعدة التنبيه.');
    }

    /**
     * تجربة القاعدة: تقيس الآن وتعرض النتيجة بلا فتح حدث ولا إرسال إشعار.
     *
     * الاختبار الذي يفتح حدثًا يلوّث السجل الذي يفترض به أن يُقرأ لاحقًا كأنه
     * تاريخ ما حدث فعلًا.
     */
    public function test(AlertRule $rule): RedirectResponse
    {
        $project = $this->current->require();
        abort_unless($rule->project_id === $project->id, 404);

        $reading = $this->reader->forRule($rule, $project);

        if (! $reading->isMeasurable()) {
            return back()->with('warning', "تعذّر القياس: {$reading->sampleLabel}.");
        }

        $value = (float) $reading->value;
        $breached = $rule->comparison->holds($value, $rule->threshold);
        $shown = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return back()->with(
            $breached ? 'warning' : 'success',
            $breached
                ? "القيمة الآن {$shown} — متجاوزة للحد ({$reading->sampleLabel})."
                : "القيمة الآن {$shown} — ضمن الحد ({$reading->sampleLabel})."
        );
    }

    /** تشغيل التقييم لكل قواعد المشروع الآن. */
    public function evaluate(EvaluateAlertRules $action): RedirectResponse
    {
        $summary = $action->handle($this->current->require());

        $parts = ["قُيِّمت {$summary['evaluated']} قاعدة"];

        if ($summary['triggered'] > 0) {
            $parts[] = "فُتح {$summary['triggered']} حدثًا";
        }

        if ($summary['resolved'] > 0) {
            $parts[] = "أُغلق {$summary['resolved']} حدثًا";
        }

        if ($summary['skipped'] > 0) {
            $parts[] = "تعذّر قياس {$summary['skipped']} قاعدة";
        }

        return back()->with('success', implode(' · ', $parts).'.');
    }

    public function resolveEvent(Request $request, AlertEvent $event, ResolveAlertEvent $action): RedirectResponse
    {
        abort_unless($event->project_id === $this->current->require()->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:acknowledged,resolved'],
        ]);

        $action->handle($event, AlertEventStatus::from($validated['status']));

        return back();
    }

    /**
     * @return array{
     *     attributes: array<string, mixed>,
     *     recipients: list<array{user_id?: int|null, email?: string|null, channel: string}>
     * }
     */
    private function validated(Request $request): array
    {
        $metrics = implode(',', array_column(AlertMetric::cases(), 'value'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'],
            'metric' => ['required', 'string', "in:{$metrics}"],
            'comparison' => ['required', 'string', 'in:gt,gte,lt,lte'],
            'threshold' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'window_minutes' => ['required', 'integer', 'min:5', 'max:43200'],
            'severity' => ['required', 'string', 'in:info,warning,critical'],
            'cooldown_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'auto_action' => ['required', 'string', 'in:notify_only,escalate_to_human,pause_section,failover_provider,raise_assistant_level'],
            'is_enabled' => ['boolean'],
            'section_ids' => ['array', 'max:50'],
            'section_ids.*' => ['integer'],
            'provider_ids' => ['array', 'max:20'],
            'provider_ids.*' => ['integer'],
            'recipients' => ['array', 'max:20'],
            'recipients.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recipients.*.email' => ['nullable', 'email', 'max:150'],
            'recipients.*.channel' => ['required', 'string', 'in:in_app,email,webhook'],
        ]);

        $ceiling = AlertMetric::from($validated['metric'])->unit()->ceiling();

        if ($ceiling !== null && (float) $validated['threshold'] > $ceiling) {
            abort(422, 'الحد يتجاوز أقصى قيمة ممكنة لهذا المؤشر.');
        }

        /** @var list<array{user_id?: int|null, email?: string|null, channel: string}> $recipients */
        $recipients = array_values($validated['recipients'] ?? []);

        return [
            'attributes' => [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'metric' => $validated['metric'],
                'comparison' => $validated['comparison'],
                'threshold' => (float) $validated['threshold'],
                'window_minutes' => (int) $validated['window_minutes'],
                'severity' => $validated['severity'],
                'cooldown_minutes' => (int) $validated['cooldown_minutes'],
                'auto_action' => $validated['auto_action'],
                'is_enabled' => (bool) ($validated['is_enabled'] ?? true),
                'section_ids' => $validated['section_ids'] ?? [],
                'provider_ids' => $validated['provider_ids'] ?? [],
            ],
            'recipients' => $recipients,
        ];
    }
}
