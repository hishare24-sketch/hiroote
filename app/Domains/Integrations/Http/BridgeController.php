<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Http;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Analytics\Models\TokenUsageRecord;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Integrations\DTOs\BridgeResult;
use App\Domains\Integrations\Models\ProjectBridge;
use App\Domains\Integrations\Services\ConnectionMethods;
use App\Domains\Integrations\Services\MawazinBridge;
use App\Domains\Integrations\Services\MawazinPresenter;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Controllers\Controller;
use App\Support\Http\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * لوحة المشروع الخارجي داخل هاي روت — قراءة فقط.
 *
 * تعرض إعداد مساعد موازين وإحصاءاته كما هي، ومعها مشتقّات لا يحسبها موازين
 * لنفسه. لا زرّ كتابة في هذه الشاشة: القراءة أولًا قرارُ المرحلة، وغياب المسار
 * يجعله قرارًا في البنية لا وعدًا في التوثيق.
 */
class BridgeController extends Controller
{
    public function __construct(
        private readonly CurrentProject $current,
        private readonly MawazinBridge $bridge,
        private readonly MawazinPresenter $presenter,
        private readonly ConnectionMethods $methods,
    ) {}

    public function index(): Response
    {
        $project = $this->current->require();
        $bridge = ProjectBridge::query()->forProject($project)->first();

        $snapshot = $bridge !== null && $bridge->isConfigured()
            ? $this->bridge->snapshot($bridge)
            : [];

        if ($bridge !== null && $snapshot !== []) {
            $this->recordOutcome($bridge, $snapshot);
        }

        return Inertia::render('Bridge/Index', [
            'systemStatus' => SystemStatus::current(),
            'project' => ['id' => $project->id, 'name' => $project->name],
            'bridge' => $bridge === null ? null : [
                'id' => $bridge->id,
                'driver' => $bridge->driver,
                'base_url' => $bridge->base_url,
                'auth_mode' => $bridge->auth_mode,
                'is_enabled' => $bridge->is_enabled,
                'configured' => $bridge->isConfigured(),
                'status' => $bridge->statusLabel(),
                'tone' => $bridge->statusTone(),
                'last_synced_at' => $bridge->last_synced_at?->toIso8601String(),
                'last_error' => $bridge->last_error,
                'last_error_at' => $bridge->last_error_at?->toIso8601String(),
            ],
            'methods' => $this->methods->forProject($project),
            'snapshot' => $snapshot === []
                ? null
                : $this->presenter->present($snapshot, $this->localFigures($project)),
            'fetchedAt' => now()->toIso8601String(),
        ]);
    }

    public function save(Request $request, RecordAuditEntry $audit): RedirectResponse
    {
        $project = $this->current->require();

        $validated = $request->validate([
            'base_url' => ['required', 'url', 'max:200'],
            'auth_mode' => ['required', 'string', 'in:service_account,bearer'],
            'email' => ['nullable', 'email', 'max:150'],
            'password' => ['nullable', 'string', 'max:200'],
            'token' => ['nullable', 'string', 'max:2000'],
            'is_enabled' => ['boolean'],
        ]);

        $bridge = ProjectBridge::query()->firstOrNew(['project_id' => $project->id]);

        // السرّ الفارغ يعني «اترك ما هو محفوظ» لا «امحُه»: من يفتح الشاشة ليغيّر
        // العنوان وحده لا ينوي مسح كلمة المرور بترك حقلها خاليًا.
        $credentials = $bridge->credentials ?? [];

        foreach (['email', 'password', 'token'] as $field) {
            $value = $validated[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $credentials[$field] = $value;
            }
        }

        $bridge->forceFill([
            'project_id' => $project->id,
            'driver' => 'mawazin',
            'base_url' => rtrim($validated['base_url'], '/'),
            'auth_mode' => $validated['auth_mode'],
            'credentials' => $credentials,
            'is_enabled' => (bool) ($validated['is_enabled'] ?? true),
            'updated_by' => auth()->id(),
        ])->save();

        $audit->handle(new AuditEntry(
            action: 'integrations.bridge_save',
            auditable: $bridge,
            section: 'integrations',
            // العنوان ونمط المصادقة فقط — لا السرّ نفسه.
            newValues: ['العنوان' => $bridge->base_url, 'المصادقة' => $bridge->auth_mode],
            reason: "المشروع: {$project->name}",
        ));

        return back()->with('success', 'حُفظ إعداد الجسر.');
    }

    /**
     * ما سجّله هاي روت عن هذا المشروع — ليُقرأ بجانب ما يقوله المشروع عن نفسه.
     *
     * تُحسب لحظة العرض لا تُقرأ من آخر حفظ، ويُصرَّح بعملتها: الفجوة بين
     * الرقمين معلومةٌ في ذاتها (أحد الطرفين لا يُغذّى)، لا خطأ يُخفى.
     *
     * @return array{conversations: int, tokens: int, cost: float, currency: string}
     */
    private function localFigures(Project $project): array
    {
        $tokens = TokenUsageRecord::query()
            ->forProject($project)
            ->selectRaw('coalesce(sum(input_tokens + output_tokens + knowledge_tokens + attachment_tokens + tool_tokens), 0) as total')
            ->value('total');

        return [
            'conversations' => Conversation::query()->forProject($project)->count(),
            'tokens' => (int) $tokens,
            'cost' => round((float) CostUsageRecord::query()->forProject($project)->sum('amount'), 2),
            'currency' => 'SAR',
        ];
    }

    /**
     * يسجّل نتيجة آخر جلب على الجسر نفسه.
     *
     * النجاح الجزئي يُعدّ إخفاقًا في العرض: شاشةٌ تقول «متصل» ونصف بطاقاتها
     * فارغة تُقرأ على أن موازين لا يملك تلك الأرقام، لا على أن الجلب أخفق.
     *
     * @param  array<string, BridgeResult>  $snapshot
     */
    private function recordOutcome(ProjectBridge $bridge, array $snapshot): void
    {
        $failures = [];

        foreach ($snapshot as $name => $result) {
            if (! $result->ok) {
                $failures[] = "{$name}: {$result->error}";
            }
        }

        $bridge->forceFill($failures === []
            ? ['last_synced_at' => now(), 'last_error' => null, 'last_error_at' => null]
            : [
                'last_error' => mb_substr(implode(' · ', $failures), 0, 250),
                'last_error_at' => now(),
            ])->save();
    }
}
