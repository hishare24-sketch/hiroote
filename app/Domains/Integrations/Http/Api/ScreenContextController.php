<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Http\Api;

use App\Domains\Assistants\Models\ProjectChatPolicy;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateProjectApiKey;
use App\Support\Http\RequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * سياق الشاشة التي فُتح منها الشات — وثيقة 06 §15.
 *
 * المشروع الخارجي ينقر مستخدمُه أيقونة المساعدة في «المحفظة ← طلب سحب»، فيسأل
 * هذا المسار بمفتاح الشاشة، ويعود بما يعرفه هاي روت عنها: وصفها وعناصرها
 * وإجراءاتها وحالاتها، ومعرفة قسمها **المنشورة**.
 *
 * المنشور وحده يعبر: المسودة عملٌ داخلي، ووصولها إلى مستخدم المشروع يعني
 * إجابةً بمعلومة لم يعتمدها أحد.
 */
class ScreenContextController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $project = $request->attributes->get(AuthenticateProjectApiKey::PROJECT);

        if (! $project instanceof Project) {
            abort(401);
        }

        $validated = $request->validate([
            'screen' => ['required', 'string', 'max:120'],
        ]);

        $screen = KnowledgeScreen::query()
            ->forProject($project)
            ->where('key', $validated['screen'])
            ->with('section')
            ->first();

        if ($screen === null) {
            return response()->json([
                'error' => [
                    'code' => 'screen_not_found',
                    'message' => 'لا شاشة بهذا المفتاح في هذا المشروع.',
                    'details' => ['screen' => $validated['screen']],
                    'request_id' => RequestId::current(),
                ],
            ], 404);
        }

        $section = $screen->section;

        return response()->json([
            'project' => ['slug' => $project->slug, 'name' => $project->name],
            'screen' => [
                'key' => $screen->key,
                'name' => $screen->name,
                'description' => $screen->description,
                'elements' => $screen->elements ?? [],
                'actions' => $screen->actions ?? [],
                'states' => $screen->states ?? [],
            ],
            'section' => $section instanceof ProjectSection ? [
                'name' => $section->name,
                'description' => $section->description,
                'ai_enabled' => (bool) $section->getAttribute('ai_enabled'),
            ] : null,
            'knowledge' => $section instanceof ProjectSection
                ? $this->knowledge($project, $section)
                : [],
            // إذن الشات يُقرأ من هنا لا يُعاد تعريفه في المشروع: مصدرٌ واحد
            // للإذن يعني أن إطفاء المالك يسري فورًا، وأن نسختين لا تتباعدان.
            'chat' => $this->chat($project),
        ]);
    }

    /**
     * إذن الشات كما ضبطه المالك — لا رسائل ولا هويات.
     *
     * @return array<string, mixed>
     */
    private function chat(Project $project): array
    {
        $policy = ProjectChatPolicy::query()->forProject($project)->first();

        if ($policy === null) {
            return ['enabled' => false, 'kinds' => [], 'scopes' => []];
        }

        return [
            'enabled' => $policy->is_enabled,
            'kinds' => $policy->channel_kinds,
            'scopes' => $policy->scopes,
            'assistant_participates' => $policy->assistant_participates,
            'attachments_allowed' => $policy->attachments_allowed,
            // صفرٌ يعني «بلا حدّ» لا «لا تحفظ» — يُرسل null كي لا يُقرأ صفرًا.
            'retention_days' => $policy->keepsForever() ? null : $policy->retention_days,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function knowledge(Project $project, ProjectSection $section): array
    {
        $rows = [];

        $items = KnowledgeItem::query()
            ->forProject($project)
            ->where('section_id', $section->id)
            ->where('status', KnowledgeStatus::Published->value)
            ->with('tags:id,name')
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        foreach ($items as $item) {
            $rows[] = [
                'title' => $item->title,
                'kind' => $item->kind->value,
                'summary' => $item->summary,
                'body' => $item->body,
                'tags' => $item->tags->pluck('name')->values()->all(),
                'version' => $item->version,
                'updated_at' => $item->updated_at->toIso8601String(),
            ];
        }

        return $rows;
    }
}
