<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\Http;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Orchestrator\Actions\RunAssistant;
use App\Domains\Orchestrator\DTOs\AssistantRequest;
use App\Domains\Orchestrator\Services\DriverRegistry;
use App\Domains\Projects\Services\CurrentProject;
use App\Domains\Providers\Models\AiProvider;
use App\Http\Controllers\Controller;
use App\Support\Http\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة الاختبار والتجربة — وثيقة 06 §16.
 *
 * تسأل المساعد **بالمسار الحقيقي**: نفس `RunAssistant` التي يسلكها كل نداء،
 * بنفس المزود والمفتاح والمعرفة والمحاسبة. شاشةُ تجربةٍ بمسارٍ خاصّ تُطمئن على
 * ما لا يعمل في الإنتاج.
 *
 * ولأنها المسار نفسه: **ما يجري هنا يُسجَّل محادثةً ويُحتسب كلفةً** — والشاشة
 * تقول ذلك، فتجربةٌ صامتة تلوّث الإحصاء بلا أن يدري مشغّلها.
 */
class PlaygroundController extends Controller
{
    public function __construct(
        private readonly CurrentProject $current,
        private readonly DriverRegistry $registry,
    ) {}

    public function index(): Response
    {
        $project = $this->current->require();

        $active = AiProvider::query()->where('is_active', true)->first();

        return Inertia::render('Playground/Index', [
            'systemStatus' => SystemStatus::current(),
            'project' => ['id' => $project->id, 'name' => $project->name],
            'readiness' => [
                'provider' => $active?->name,
                // ثلاثة شروط، وكلٌّ يُقال على حدة: «غير جاهز» وحدها تترك
                // المشغّل يجرّب الثلاثة عشوائيًّا.
                'has_driver' => $active !== null && $this->registry->for($active) !== null,
                'has_model' => $active !== null && $active->models()->where('is_enabled', true)->exists(),
                'has_credential' => $active !== null && $active->activeCredential() !== null,
                'supported' => $this->registry->supported(),
            ],
            'screens' => KnowledgeScreen::query()
                ->forProject($project)
                ->whereNotNull('key')
                ->orderBy('name')
                ->get()
                ->map(fn (KnowledgeScreen $screen): array => [
                    'value' => (string) $screen->key,
                    'label' => $screen->name,
                ])
                ->values()
                ->all(),
            'sections' => ProjectSection::query()
                ->forProject($project)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ProjectSection $section): array => [
                    'value' => $section->name,
                    'label' => $section->name,
                ])
                ->values()
                ->all(),
            'levels' => array_map(
                fn (AssistantLevel $level): array => [
                    'value' => $level->value,
                    'label' => $level->label(),
                ],
                AssistantLevel::cases(),
            ),
        ]);
    }

    public function run(Request $request, RunAssistant $assistant): RedirectResponse
    {
        $project = $this->current->require();
        $actor = $request->user();

        abort_if(
            $actor === null || ! $actor->hasPermission(Permission::AssistantsManage, $project),
            403,
        );

        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2000'],
            'screen' => ['nullable', 'string', 'max:120'],
            'section' => ['nullable', 'string', 'max:120'],
            'level' => ['required', Rule::enum(AssistantLevel::class)],
        ]);

        $reply = $assistant->handle(new AssistantRequest(
            project: $project,
            messages: [['role' => 'user', 'content' => $data['message']]],
            sectionName: $data['section'] ?? null,
            screenKey: $data['screen'] ?? null,
            level: AssistantLevel::from($data['level']),
            userLabel: $actor->name,
            reference: 'playground-'.$actor->id.'-'.now()->timestamp,
        ));

        return back()->with('playground', [
            'ok' => $reply->ok,
            'text' => $reply->text,
            'error' => $reply->error,
            'input_tokens' => $reply->inputTokens,
            'output_tokens' => $reply->outputTokens,
            'cost' => $reply->cost,
            'latency_ms' => $reply->latencyMs,
            'provider' => $reply->provider,
            'model' => $reply->model,
            'failed_over' => $reply->failedOver,
            'conversation_id' => $reply->conversation?->id,
        ]);
    }
}
