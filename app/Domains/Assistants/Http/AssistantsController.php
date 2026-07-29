<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Http;

use App\Domains\Assistants\Actions\ProvisionAssistantDefaults;
use App\Domains\Assistants\Actions\ToggleAssistantFunction;
use App\Domains\Assistants\Actions\UpdateAssistantLevel;
use App\Domains\Assistants\Actions\UpdateAssistantProfile;
use App\Domains\Assistants\Enums\AssistantFunction;
use App\Domains\Assistants\Models\AssistantFunctionSetting;
use App\Domains\Assistants\Models\AssistantLevelSetting;
use App\Domains\Assistants\Models\AssistantProfile;
use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Projects\Services\CurrentProject;
use App\Domains\Providers\Models\AiModel;
use App\Http\Controllers\Controller;
use App\Support\Enums\EnumPayload;
use App\Support\Http\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة إعدادات وسلوك المساعد — وثيقة 06 §12 و§13.
 */
class AssistantsController extends Controller
{
    public function __construct(
        private readonly CurrentProject $current,
        private readonly ProvisionAssistantDefaults $provision,
    ) {}

    public function index(): Response
    {
        $project = $this->current->require();

        // مشروعٌ لم يُجهَّز بعد يعرض شاشة فارغة تبدو معطوبة — يُجهَّز عند أول فتح.
        $this->provision->handle($project);

        $profile = AssistantProfile::forProject($project);

        return Inertia::render('Assistants/Index', [
            'systemStatus' => SystemStatus::current(),
            'project' => ['id' => $project->id, 'name' => $project->name],
            'levels' => AssistantLevelSetting::query()
                ->forProject($project)
                ->with('model:id,display_name')
                ->ordered()
                ->get()
                ->map(fn (AssistantLevelSetting $level): array => [
                    'id' => $level->id,
                    'key' => EnumPayload::from($level->key),
                    'label' => $level->label,
                    'description' => $level->description,
                    'response_length' => $level->response_length,
                    'token_limit' => $level->token_limit,
                    'intelligence' => $level->intelligence,
                    'initiative' => $level->initiative,
                    'creativity' => $level->creativity,
                    'detail' => $level->detail,
                    'formality' => $level->formality,
                    'reads_attachments' => $level->reads_attachments,
                    'calls_data' => $level->calls_data,
                    'executes_actions' => $level->executes_actions,
                    'confidence_threshold' => $level->confidence_threshold,
                    'model_id' => $level->model_id,
                    'model' => $level->model?->display_name,
                    'expected_cost' => (float) $level->expected_cost,
                    'is_available' => $level->is_available,
                    'is_default' => $level->key === $profile->default_level,
                ])
                ->values()
                ->all(),
            'profile' => [
                'default_level' => $profile->default_level->value,
                'allow_level_change' => $profile->allow_level_change,
                'level_scope' => $profile->level_scope,
                'availability' => $profile->availability,
                'availability_key' => $profile->availability_key,
            ],
            'functions' => $this->functionPayload($project->id),
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

    public function updateLevel(Request $request, AssistantLevelSetting $level, UpdateAssistantLevel $action): RedirectResponse
    {
        abort_unless($level->project_id === $this->current->require()->id, 404);

        $action->handle($level, $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:400'],
            'response_length' => ['required', 'string', 'max:60'],
            'token_limit' => ['required', 'integer', 'min:100', 'max:32000'],
            'intelligence' => ['required', 'integer', 'min:1', 'max:5'],
            'initiative' => ['required', 'integer', 'min:1', 'max:5'],
            'creativity' => ['required', 'integer', 'min:0', 'max:100'],
            'detail' => ['required', 'integer', 'min:1', 'max:5'],
            'formality' => ['required', 'integer', 'min:1', 'max:5'],
            'reads_attachments' => ['required', 'boolean'],
            'calls_data' => ['required', 'boolean'],
            'executes_actions' => ['required', 'boolean'],
            'confidence_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'model_id' => ['nullable', 'integer', 'exists:ai_models,id'],
            'expected_cost' => ['required', 'numeric', 'min:0', 'max:999'],
            'is_available' => ['required', 'boolean'],
        ]));

        return back()->with('success', 'حُفظ المستوى.');
    }

    public function updateProfile(Request $request, UpdateAssistantProfile $action): RedirectResponse
    {
        $action->handle($this->current->require(), $request->validate([
            'default_level' => ['required', 'string', 'in:direct,balanced,proactive,expert'],
            'allow_level_change' => ['required', 'boolean'],
            'level_scope' => ['required', 'string', 'in:persistent,conversation'],
            'availability' => ['required', 'string', 'in:all,membership,role'],
            'availability_key' => ['nullable', 'string', 'max:60'],
        ]));

        return back()->with('success', 'حُفظ إعداد تحكم المستخدم.');
    }

    public function toggleFunction(Request $request, ToggleAssistantFunction $action): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
        ]);

        $function = AssistantFunction::tryFrom($validated['key']);
        abort_if($function === null, 404);

        // موقوف حتى تُبنى الميزة لا حتى يُقرَّر تعريفها: التعريف مُعتمد (نمط
        // موازين)، والتفعيل قبل التنفيذ يَعِد بسلوك لا وجود له.
        abort_if($function->awaitsImplementation(), 422, 'هذه الوظيفة بانتظار التنفيذ.');

        $action->handle($this->current->require(), $function, $validated['enabled']);

        return back();
    }

    /** @return list<array<string, mixed>> */
    private function functionPayload(int $projectId): array
    {
        $stored = AssistantFunctionSetting::query()
            ->where('project_id', $projectId)
            ->pluck('is_enabled', 'key');

        return array_map(
            fn (AssistantFunction $function): array => [
                'key' => $function->value,
                'label' => $function->label(),
                'description' => $function->description(),
                'enabled' => (bool) ($stored[$function->value] ?? $function->defaultEnabled()),
                'sensitive' => $function->isSensitive(),
                'awaits_implementation' => $function->awaitsImplementation(),
                'depends_on' => $function->dependsOn()?->value,
                'depends_on_label' => $function->dependsOn()?->label(),
                'tone' => $function->tone(),
            ],
            AssistantFunction::cases(),
        );
    }
}
