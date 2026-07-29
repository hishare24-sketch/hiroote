<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Http;

use App\Domains\Assistants\Actions\DeleteProjectSection;
use App\Domains\Assistants\Actions\SaveProjectSection;
use App\Domains\Assistants\Actions\ToggleSectionCapability;
use App\Domains\Assistants\Enums\SectionCapability;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use App\Domains\Providers\Models\AiModel;
use App\Http\Controllers\Controller;
use App\Support\Enums\EnumPayload;
use App\Support\Http\SystemStatus;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * مصفوفة تكامل أقسام المشروع — وثيقة 06 §14.
 */
class SectionsController extends Controller
{
    public function __construct(private readonly CurrentProject $current) {}

    public function index(): Response
    {
        $project = $this->current->require();
        $usage = $this->usageBySection($project);

        return Inertia::render('Sections/Index', [
            'systemStatus' => SystemStatus::current(),
            'project' => ['id' => $project->id, 'name' => $project->name],
            'capabilities' => array_map(
                fn (SectionCapability $capability): array => [
                    'key' => $capability->value,
                    'label' => $capability->label(),
                    'short_label' => $capability->shortLabel(),
                    'description' => $capability->description(),
                    'sensitive' => $capability->isSensitive(),
                    'depends_on' => $capability->dependsOn()?->value,
                    'depends_on_label' => $capability->dependsOn()?->label(),
                ],
                SectionCapability::cases(),
            ),
            'sections' => ProjectSection::query()
                ->forProject($project)
                ->with('model:id,display_name')
                ->ordered()
                ->get()
                ->map(function (ProjectSection $section) use ($usage): array {
                    $stats = $usage[$section->name] ?? ['conversations' => 0, 'resolved' => 0, 'escalated' => 0];

                    return [
                        'id' => $section->id,
                        'name' => $section->name,
                        'slug' => $section->slug,
                        'description' => $section->description,
                        'sort_order' => $section->sort_order,
                        'capabilities' => $this->capabilityValues($section),
                        'level' => $section->level === null ? null : EnumPayload::from($section->level),
                        'model_id' => $section->model_id,
                        'model' => $section->model?->display_name,
                        'conversations' => $stats['conversations'],
                        'resolution_rate' => $stats['conversations'] === 0
                            ? null
                            : round($stats['resolved'] / $stats['conversations'] * 100, 1),
                        'escalation_rate' => $stats['conversations'] === 0
                            ? null
                            : round($stats['escalated'] / $stats['conversations'] * 100, 1),
                        'updated_at' => $section->updated_at->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
            'models' => AiModel::query()
                ->where('is_enabled', true)
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn (AiModel $model): array => [
                    'value' => (string) $model->id,
                    'label' => $model->display_name,
                ])
                ->all(),
            'levelOptions' => array_map(
                fn (AssistantLevel $level): array => [
                    'value' => $level->value,
                    'label' => $level->label(),
                ],
                AssistantLevel::cases(),
            ),
        ]);
    }

    public function store(Request $request, SaveProjectSection $action): RedirectResponse
    {
        $project = $this->current->require();

        $action->handle($project, $request->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueName($project)],
            'description' => ['nullable', 'string', 'max:300'],
        ], ['name.unique' => 'يوجد قسم بهذا الاسم في هذا المشروع.']));

        return back()->with('success', 'أُضيف القسم.');
    }

    public function update(Request $request, ProjectSection $section, SaveProjectSection $action): RedirectResponse
    {
        $this->authorizeSection($section);

        $project = $this->current->require();

        $action->handle($project, $request->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueName($project, $section)],
            'description' => ['nullable', 'string', 'max:300'],
            'level' => ['nullable', 'string', 'in:direct,balanced,proactive,expert'],
            'model_id' => ['nullable', 'integer', 'exists:ai_models,id'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ], ['name.unique' => 'يوجد قسم بهذا الاسم في هذا المشروع.']), $section);

        return back()->with('success', 'حُفظ القسم.');
    }

    public function destroy(ProjectSection $section, DeleteProjectSection $action): RedirectResponse
    {
        $this->authorizeSection($section);

        try {
            $action->handle($section);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['section' => $exception->getMessage()]);
        }

        return back()->with('success', 'حُذف القسم.');
    }

    public function toggle(Request $request, ProjectSection $section, ToggleSectionCapability $action): RedirectResponse
    {
        $this->authorizeSection($section);

        $validated = $request->validate([
            'capability' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        $capability = SectionCapability::tryFrom($validated['capability']);
        abort_if($capability === null, 404);

        $action->handle($section, $capability, $validated['enabled']);

        return back();
    }

    private function authorizeSection(ProjectSection $section): void
    {
        abort_unless($section->project_id === $this->current->require()->id, 404);
    }

    /**
     * اسم القسم فريد داخل مشروعه.
     *
     * التفرّد على مستوى المشروع لا المنصة: «المحفظة» قد توجد في أكثر من مشروع.
     * ومنعه هنا برسالة عربية لا بخطأ قاعدة بيانات: الفهرس الفريد يحمي البيانات،
     * والرسالة تشرح للمشغّل ما فعله.
     */
    private function uniqueName(Project $project, ?ProjectSection $section = null): Unique
    {
        return Rule::unique('project_sections', 'name')
            ->where(fn (Builder $query) => $query->where('project_id', $project->id))
            ->ignore($section?->id);
    }

    /** @return array<string, bool> */
    private function capabilityValues(ProjectSection $section): array
    {
        $values = [];

        foreach (SectionCapability::cases() as $capability) {
            $values[$capability->value] = (bool) $section->getAttribute($capability->value);
        }

        return $values;
    }

    /**
     * الاستخدام والحل والتصعيد لكل قسم — العمود الأخير في وثيقة 06 §14.
     *
     * يُقرأ من المحادثات الفعلية لا من عدّاد محفوظ: عدّادٌ ثانٍ يعني رقمين
     * للحقيقة الواحدة، وأحدهما سيتأخر.
     *
     * @return array<string, array{conversations: int, resolved: int, escalated: int}>
     */
    private function usageBySection(Project $project): array
    {
        /** @var list<object{section: string, total: int, resolved: int|null, escalated: int|null}> $rows */
        $rows = Conversation::query()
            ->forProject($project)
            ->where('started_at', '>=', now()->subDays(30))
            ->selectRaw('section, count(*) as total')
            ->selectRaw('count(*) filter (where outcome = ?) as resolved', [ConversationOutcome::Resolved->value])
            ->selectRaw('count(*) filter (where outcome in (?, ?)) as escalated', [
                ConversationOutcome::Human->value,
                ConversationOutcome::Ticket->value,
            ])
            ->groupBy('section')
            ->toBase()
            ->get()
            ->all();

        $usage = [];

        foreach ($rows as $row) {
            $usage[$row->section] = [
                'conversations' => (int) $row->total,
                'resolved' => (int) ($row->resolved ?? 0),
                'escalated' => (int) ($row->escalated ?? 0),
            ];
        }

        return $usage;
    }
}
