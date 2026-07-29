<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Http;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Actions\AssignKnowledgeFeedback;
use App\Domains\Knowledge\Actions\CloseKnowledgeFeedback;
use App\Domains\Knowledge\Actions\RecordFeedbackVerification;
use App\Domains\Knowledge\Actions\RestoreKnowledgeVersion;
use App\Domains\Knowledge\Actions\SaveKnowledgeItem;
use App\Domains\Knowledge\Actions\SaveKnowledgeScreen;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Enums\VerificationOutcome;
use App\Domains\Knowledge\Models\FeedbackVerification;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Knowledge\Models\KnowledgeSource;
use App\Domains\Knowledge\Models\KnowledgeVersion;
use App\Domains\Knowledge\Services\KnowledgeSearch;
use App\Domains\Knowledge\Services\SectionKnowledgeReport;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Controllers\Controller;
use App\Support\Enums\EnumPayload;
use App\Support\Http\SystemStatus;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * قاعدة المعرفة وتفاصيل القسم — وثيقة 06 §15.
 */
class KnowledgeController extends Controller
{
    /** سقف نتائج البحث المعروضة — وما فوقه يُعلَن عددًا لا يُبتَر صامتًا. */
    private const MAX_RESULTS = 30;

    public function __construct(
        private readonly CurrentProject $current,
        private readonly SectionKnowledgeReport $report,
        private readonly KnowledgeSearch $search,
    ) {}

    public function index(Request $request): Response
    {
        $project = $this->current->require();
        $report = $this->report->forProject($project);

        $sections = ProjectSection::query()->forProject($project)->ordered()->get();
        $query = trim((string) $request->query('q', ''));

        return Inertia::render('Knowledge/Index', [
            'systemStatus' => SystemStatus::current(),
            'project' => ['id' => $project->id, 'name' => $project->name],
            'criteria' => SectionKnowledgeReport::CRITERIA,
            'query' => $query,
            'results' => $query === '' ? null : $this->results($project, $query, $sections),
            'sections' => $sections
                ->map(fn (ProjectSection $section): array => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'description' => $section->description,
                    'ai_enabled' => (bool) $section->getAttribute('ai_enabled'),
                    'updated_at' => $section->updated_at->toIso8601String(),
                    ...($report[$section->id] ?? []),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * نتائج البحث عبر أقسام المشروع كلها.
     *
     * تُعرض المسودات مع المنشور و**تُوسَم**: محرِّرٌ لا يرى مسوّدته يكتبها
     * ثانيةً؛ ومحرِّرٌ يراها بلا وسم يظنّ المساعد يجيب بها، فيبحث عن سبب
     * «الجواب الخاطئ» في مكانٍ آخر.
     *
     * @param  Collection<int, ProjectSection>  $sections
     * @return array{total: int, shown: int, items: list<array<string, mixed>>}
     */
    private function results(Project $project, string $query, Collection $sections): array
    {
        $names = $sections->pluck('name', 'id');

        $base = $this->search->apply(
            KnowledgeItem::query()->forProject($project),
            $query,
        );

        $total = (clone $base)->count();

        $items = (clone $base)
            // المنشور أولًا: هو ما يجيب به المساعد فعلًا، والمسودة عملٌ لم
            // يعتمده أحد.
            ->orderByRaw('case when status = ? then 0 else 1 end', [KnowledgeStatus::Published->value])
            ->orderByDesc('updated_at')
            ->limit(self::MAX_RESULTS)
            ->get();

        return [
            'total' => $total,
            'shown' => $items->count(),
            'items' => array_values($items->map(fn (KnowledgeItem $item): array => [
                'id' => $item->id,
                'title' => $item->title,
                'section_id' => $item->section_id,
                'section' => $names[$item->section_id] ?? '—',
                'kind' => EnumPayload::from($item->kind),
                'status' => EnumPayload::from($item->status),
                'excerpt' => $this->search->excerpt((string) $item->body, $query),
                'updated_at' => $item->updated_at->toIso8601String(),
                // ما يراه المساعد هو المنشور وحده — تُقال هنا كي لا يُبحث عن
                // سبب «لا أعرف» في المزود وهو في حالة العنصر.
                'visible_to_assistant' => $item->status === KnowledgeStatus::Published,
            ])->all()),
        ];
    }

    public function show(ProjectSection $section): Response
    {
        $project = $this->current->require();
        abort_unless($section->project_id === $project->id, 404);

        $report = $this->report->forProject($project);

        return Inertia::render('Knowledge/Show', [
            'systemStatus' => SystemStatus::current(),
            'section' => [
                'id' => $section->id,
                'name' => $section->name,
                'description' => $section->description,
                'ai_enabled' => (bool) $section->getAttribute('ai_enabled'),
                'knowledge_enabled' => (bool) $section->getAttribute('knowledge'),
                'level' => $section->level === null ? null : EnumPayload::from($section->level),
                ...($report[$section->id] ?? []),
            ],
            'items' => KnowledgeItem::query()
                ->forProject($project)
                ->where('section_id', $section->id)
                ->with(['tags:id,name', 'editor:id,name'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (KnowledgeItem $item): array => [
                    'id' => $item->id,
                    'title' => $item->title,
                    'summary' => $item->summary,
                    'body' => $item->body,
                    'kind' => EnumPayload::from($item->kind),
                    'status' => EnumPayload::from($item->status),
                    'version' => $item->version,
                    'tags' => $item->tags->pluck('name')->values()->all(),
                    'editor' => $item->editor?->name,
                    'updated_at' => $item->updated_at->toIso8601String(),
                ])
                ->values()
                ->all(),
            'screens' => KnowledgeScreen::query()
                ->forProject($project)
                ->where('section_id', $section->id)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (KnowledgeScreen $screen): array => [
                    'id' => $screen->id,
                    'name' => $screen->name,
                    'key' => $screen->key,
                    'path' => $screen->path,
                    'description' => $screen->description,
                    'image_url' => $screen->imageUrl(),
                    'elements' => $screen->elements ?? [],
                    'actions' => $screen->actions ?? [],
                    'states' => $screen->states ?? [],
                ])
                ->values()
                ->all(),
            'sources' => KnowledgeSource::query()
                ->forProject($project)
                ->where('section_id', $section->id)
                ->latest('id')
                ->get()
                ->map(fn (KnowledgeSource $source): array => [
                    'id' => $source->id,
                    'kind' => EnumPayload::from($source->kind),
                    'label' => $source->label,
                    'url' => $source->url,
                    'note' => $source->note,
                    'created_at' => $source->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
            'feedback' => KnowledgeFeedback::query()
                ->forProject($project)
                ->where('section_id', $section->id)
                ->with(['screen:id,name', 'assignee:id,name', 'verifications.verifier:id,name', 'verifications.screen:id,name'])
                ->orderBy('resolved_at')
                ->orderByDesc('occurrences')
                ->get()
                ->map(fn (KnowledgeFeedback $entry): array => [
                    'id' => $entry->id,
                    'kind' => EnumPayload::from($entry->kind),
                    'source' => EnumPayload::from($entry->source),
                    'needs_verification' => $entry->source->needsVerification(),
                    'actionable' => $entry->isActionable(),
                    'screen' => $entry->screen === null ? null : [
                        'id' => $entry->screen->id,
                        'name' => $entry->screen->name,
                    ],
                    'assignee' => $entry->assignee?->name,
                    'body' => $entry->body,
                    'occurrences' => $entry->occurrences,
                    'resolved' => $entry->resolved_at !== null,
                    'resolution' => $entry->resolution,
                    'created_at' => $entry->created_at->toIso8601String(),
                    'verifications' => $entry->verifications
                        ->map(fn (FeedbackVerification $check): array => [
                            'id' => $check->id,
                            'outcome' => EnumPayload::from($check->outcome),
                            'steps' => $check->steps,
                            'finding' => $check->finding,
                            'screen' => $check->screen?->name,
                            'verifier' => $check->verifier?->name,
                            'created_at' => $check->created_at->toIso8601String(),
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'kindOptions' => array_map(
                fn (KnowledgeKind $kind): array => [
                    'value' => $kind->value,
                    'label' => $kind->label(),
                    'description' => $kind->description(),
                ],
                KnowledgeKind::cases(),
            ),
            'statusOptions' => array_map(
                fn (KnowledgeStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                KnowledgeStatus::cases(),
            ),
            'feedbackKinds' => array_map(
                fn (FeedbackKind $kind): array => [
                    'value' => $kind->value,
                    'label' => $kind->label(),
                ],
                FeedbackKind::cases(),
            ),
            'verificationOutcomes' => array_map(
                fn (VerificationOutcome $outcome): array => [
                    'value' => $outcome->value,
                    'label' => $outcome->label(),
                    'description' => $outcome->hint(),
                ],
                VerificationOutcome::cases(),
            ),
        ]);
    }

    public function storeItem(Request $request, ProjectSection $section, SaveKnowledgeItem $action): RedirectResponse
    {
        $project = $this->current->require();
        abort_unless($section->project_id === $project->id, 404);

        $validated = $this->validateItem($request);

        $action->handle(
            project: $project,
            attributes: [...$validated['attributes'], 'section_id' => $section->id],
            tags: $validated['tags'],
            changeNote: $validated['change_note'],
        );

        return back()->with('success', 'أُضيف عنصر المعرفة.');
    }

    public function updateItem(Request $request, KnowledgeItem $item, SaveKnowledgeItem $action): RedirectResponse
    {
        $project = $this->current->require();
        abort_unless($item->project_id === $project->id, 404);

        $validated = $this->validateItem($request);

        $action->handle(
            project: $project,
            attributes: [...$validated['attributes'], 'section_id' => $item->section_id],
            tags: $validated['tags'],
            item: $item,
            changeNote: $validated['change_note'],
        );

        return back()->with('success', 'حُفظ عنصر المعرفة.');
    }

    public function versions(KnowledgeItem $item): Response
    {
        abort_unless($item->project_id === $this->current->require()->id, 404);

        return Inertia::render('Knowledge/Versions', [
            'systemStatus' => SystemStatus::current(),
            'item' => [
                'id' => $item->id,
                'title' => $item->title,
                'version' => $item->version,
                'section_id' => $item->section_id,
            ],
            'versions' => $item->versions()
                ->with('author:id,name')
                ->orderByDesc('version')
                ->get()
                ->map(fn (KnowledgeVersion $version): array => [
                    'id' => $version->id,
                    'version' => $version->version,
                    'title' => $version->title,
                    'summary' => $version->summary,
                    'body' => $version->body,
                    'status' => EnumPayload::from($version->status),
                    'author' => $version->author?->name,
                    'change_note' => $version->change_note,
                    'created_at' => $version->created_at->toIso8601String(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function restore(KnowledgeItem $item, KnowledgeVersion $version, RestoreKnowledgeVersion $action): RedirectResponse
    {
        $project = $this->current->require();

        abort_unless($item->project_id === $project->id, 404);
        abort_unless($version->knowledge_item_id === $item->id, 404);

        $action->handle($item, $project, $version);

        return back()->with('success', "رجع العنصر إلى الإصدار {$version->version}.");
    }

    /** تسجيل محضر تحقق ميداني — الخطوة التي تسبق أي تعديل. */
    public function verifyFeedback(
        Request $request,
        KnowledgeFeedback $feedback,
        RecordFeedbackVerification $action,
    ): RedirectResponse {
        $project = $this->current->require();
        abort_unless($feedback->project_id === $project->id, 404);

        $validated = $request->validate([
            'outcome' => ['required', 'string', 'in:reproduced,not_reproduced,different_cause'],
            'steps' => ['required', 'string', 'min:10', 'max:2000'],
            'finding' => ['nullable', 'string', 'max:2000'],
            'screen_id' => ['nullable', 'integer'],
        ], [
            'steps.required' => 'اذكر ما فعلته بوصفك مستخدمًا — «تحقّقتُ» وحدها ليست إثباتًا.',
            'steps.min' => 'الخطوات مختصرة أكثر مما يفيد من يقرؤها لاحقًا.',
        ]);

        $screenId = $validated['screen_id'] ?? null;

        if ($screenId !== null) {
            abort_unless(
                KnowledgeScreen::query()->forProject($project)->whereKey($screenId)->exists(),
                404,
            );
        }

        $action->handle(
            feedback: $feedback,
            outcome: VerificationOutcome::from($validated['outcome']),
            steps: $validated['steps'],
            finding: $validated['finding'] ?? null,
            screenId: $screenId,
        );

        return back()->with('success', 'سُجّل التحقق الميداني.');
    }

    public function closeFeedback(
        Request $request,
        KnowledgeFeedback $feedback,
        CloseKnowledgeFeedback $action,
    ): RedirectResponse {
        abort_unless($feedback->project_id === $this->current->require()->id, 404);

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:fixed,dismissed,reopen'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);

        if ($validated['resolution'] === 'reopen') {
            $action->reopen($feedback);

            return back();
        }

        try {
            $action->handle($feedback, $validated['resolution'], $validated['note'] ?? null);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['resolution' => $exception->getMessage()]);
        }

        return back()->with('success', 'أُغلق الرصد.');
    }

    public function assignFeedback(KnowledgeFeedback $feedback, AssignKnowledgeFeedback $action): RedirectResponse
    {
        abort_unless($feedback->project_id === $this->current->require()->id, 404);

        $action->handle($feedback);

        return back();
    }

    public function storeScreen(Request $request, ProjectSection $section, SaveKnowledgeScreen $action): RedirectResponse
    {
        $project = $this->current->require();
        abort_unless($section->project_id === $project->id, 404);

        $validated = $this->validateScreen($request, $project);

        $action->handle(
            project: $project,
            section: $section,
            attributes: $validated,
            image: $request->file('image') instanceof UploadedFile ? $request->file('image') : null,
        );

        return back()->with('success', 'أُضيفت الشاشة.');
    }

    public function updateScreen(Request $request, KnowledgeScreen $screen, SaveKnowledgeScreen $action): RedirectResponse
    {
        $project = $this->current->require();
        abort_unless($screen->project_id === $project->id, 404);

        $section = ProjectSection::query()->findOrFail($screen->section_id);
        $validated = $this->validateScreen($request, $project, $screen);

        $action->handle(
            project: $project,
            section: $section,
            attributes: $validated,
            screen: $screen,
            image: $request->file('image') instanceof UploadedFile ? $request->file('image') : null,
            removeImage: $request->boolean('remove_image'),
        );

        return back()->with('success', 'حُفظت الشاشة.');
    }

    public function destroyScreen(KnowledgeScreen $screen, SaveKnowledgeScreen $action): RedirectResponse
    {
        abort_unless($screen->project_id === $this->current->require()->id, 404);

        $action->delete($screen);

        return back()->with('success', 'حُذفت الشاشة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateScreen(Request $request, Project $project, ?KnowledgeScreen $screen = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // المفتاح لاتيني بنقاط: هو ما يرسله المشروع الخارجي، وحرفٌ عربي أو
            // مسافة فيه يجعل المطابقة تعتمد على ترميز الرابط.
            'key' => [
                'nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('knowledge_screens', 'key')
                    ->where(fn (Builder $query) => $query->where('project_id', $project->id))
                    ->ignore($screen?->id),
            ],
            'path' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'elements' => ['array', 'max:30'],
            'elements.*' => ['string', 'max:120'],
            'actions' => ['array', 'max:30'],
            'actions.*' => ['string', 'max:120'],
            'states' => ['array', 'max:30'],
            'states.*' => ['string', 'max:120'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ], [
            'key.regex' => 'المفتاح حروف لاتينية صغيرة وأرقام تفصلها نقطة، مثل wallet.withdraw.',
            'key.unique' => 'هذا المفتاح مستخدم في شاشة أخرى من المشروع نفسه.',
            'image.max' => 'حجم الصورة يتجاوز ٤ ميغابايت.',
        ]);

        return [
            'name' => $validated['name'],
            'key' => $validated['key'] ?? null,
            'path' => $validated['path'] ?? null,
            'description' => $validated['description'] ?? null,
            'elements' => array_values($validated['elements'] ?? []),
            'actions' => array_values($validated['actions'] ?? []),
            'states' => array_values($validated['states'] ?? []),
        ];
    }

    /**
     * @return array{attributes: array<string, mixed>, tags: list<string>, change_note: string|null}
     */
    private function validateItem(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'summary' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string', 'max:20000'],
            'kind' => ['required', 'string', 'in:article,faq,procedure,policy'],
            'status' => ['required', 'string', 'in:draft,review,published,archived'],
            'tags' => ['array', 'max:12'],
            'tags.*' => ['string', 'max:40'],
            'change_note' => ['nullable', 'string', 'max:150'],
        ]);

        /** @var list<string> $tags */
        $tags = array_values($validated['tags'] ?? []);

        return [
            'attributes' => [
                'title' => $validated['title'],
                'summary' => $validated['summary'] ?? null,
                'body' => $validated['body'],
                'kind' => $validated['kind'],
                'status' => $validated['status'],
            ],
            'tags' => $tags,
            'change_note' => $validated['change_note'] ?? null,
        ];
    }
}
