<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Http\Api;

use App\Domains\Analytics\Models\ProjectPulse;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateProjectApiKey;
use App\Support\Http\RequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * النبض اليومي — دفعةٌ مجمَّعة عن يومٍ واحد (`docs/09 §10`).
 *
 * تختلف هذه النهاية عن أخواتها في شيء واحد: **خطؤها لا يظهر**. محادثةٌ ضاعت
 * تُلاحَظ، ورقمٌ يومي خاطئ يدخل الرسم البياني ويُبنى عليه قرارٌ بعد شهور. ولذلك
 * تُرفض هنا أشياء تُقبل في غيرها، ويُقال في الردّ ما لم يُقبل بدل أن يُبلع.
 */
class PulseIntakeController extends Controller
{
    /** أبعد يومٍ يُقبل بأثرٍ رجعي — يكفي لسدّ انقطاعٍ طويل ولا يفتح الباب لتاريخٍ مُختلَق. */
    private const BACKFILL_DAYS = 400;

    /** حدّ الشاشات في الدفعة الواحدة — ويُعلَن في الردّ ما تجاوزه. */
    private const MAX_SCREENS = 300;

    /** حقولٌ تدلّ على أشخاص — النبض مجاميع لا صفوفٌ لفرد (قرار المالك ٢٠٢٦-٠٧-٣٠). */
    private const FORBIDDEN_KEYS = ['external_user_id', 'user_label', 'users', 'subscribers', 'emails'];

    public function __invoke(Request $request): JsonResponse
    {
        $project = $request->attributes->get(AuthenticateProjectApiKey::PROJECT);

        if (! $project instanceof Project) {
            abort(401);
        }

        // الحدّ يُفحص قبل التحقّق لا بعده: حقلٌ يحمل هويّة يُردّ صراحةً برسالة
        // تقول السبب، لا يُهمَل بصمت فيظنّ المُرسِل أنه وصل ويُبنى عليه.
        $offending = array_values(array_intersect(self::FORBIDDEN_KEYS, array_keys($request->all())));

        if ($offending !== []) {
            return $this->error(
                'identity_not_accepted',
                'النبض مجاميعُ لا بيانات أفراد — أزل هذه الحقول.',
                ['fields' => $offending],
                422,
            );
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'timezone' => ['required', 'string', 'timezone'],
            'final' => ['nullable', 'boolean'],

            'active_users' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'logins' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'sessions' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'peak_concurrent' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'presence_minutes' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'peak_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'peak_hour_actions' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'storage_megabytes' => ['nullable', 'integer', 'min:0', 'max:100000000000'],

            'section_actions' => ['nullable', 'array', 'max:200'],
            'section_actions.*' => ['integer', 'min:0', 'max:100000000'],

            'packages' => ['nullable', 'array', 'max:100'],
            'packages.*.name' => ['required', 'string', 'max:120'],
            'packages.*.subscribers' => ['required', 'integer', 'min:0', 'max:100000000'],

            'health' => ['nullable', 'array', 'max:50'],
            'health.*' => ['numeric'],

            'screens' => ['nullable', 'array'],
            'screens.*.key' => ['required', 'string', 'max:120'],
            'screens.*.views' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'screens.*.clicks' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $timezone = $validated['timezone'];
        $date = Carbon::createFromFormat('Y-m-d', $validated['date'], $timezone);

        if (! $date instanceof Carbon) {
            return $this->error('invalid_date', 'تاريخٌ غير صالح.', ['date' => $validated['date']], 422);
        }

        $today = Carbon::now($timezone)->startOfDay();

        // يومٌ في المستقبل ليس بيانات — إمّا منطقة زمنية خاطئة عند المُرسِل أو
        // ساعةٌ منحرفة. وقبولُه يزرع صفًّا لا يُطابقه شيء حين يجيء ذلك اليوم.
        if ($date->startOfDay()->greaterThan($today)) {
            return $this->error(
                'date_in_future',
                'اليوم المُرسَل لم يأتِ بعد في منطقته الزمنية.',
                ['date' => $validated['date'], 'timezone' => $timezone],
                422,
            );
        }

        if ($date->startOfDay()->lessThan($today->copy()->subDays(self::BACKFILL_DAYS))) {
            return $this->error(
                'date_too_old',
                'اليوم أقدم من نافذة الأثر الرجعي المقبولة.',
                ['date' => $validated['date'], 'max_days' => self::BACKFILL_DAYS],
                422,
            );
        }

        /** @var list<array{key: string, views?: int|null, clicks?: int|null}> $screens */
        $screens = $validated['screens'] ?? [];
        $truncated = 0;

        if (count($screens) > self::MAX_SCREENS) {
            // الاقتطاع يُعلَن ولا يقع صامتًا: ناقصٌ يبدو مكتملًا أسوأ من ناقصٍ
            // يقول إنه ناقص.
            $truncated = count($screens) - self::MAX_SCREENS;
            $screens = array_slice($screens, 0, self::MAX_SCREENS);
        }

        $known = KnowledgeScreen::query()
            ->forProject($project)
            ->pluck('key')
            ->all();

        $accepted = [];
        $ignored = [];

        foreach ($screens as $screen) {
            // مفتاحٌ مجهول لا يُسقط الدفعة: خسارةُ يومٍ كامل لأجل مفتاحٍ واحد
            // صفقةٌ خاسرة — ويُذكر في الردّ فلا يضيع صامتًا.
            if (! in_array($screen['key'], $known, strict: true)) {
                $ignored[] = $screen['key'];

                continue;
            }

            $accepted[$screen['key']] = [
                'views' => $screen['views'] ?? null,
                'clicks' => $screen['clicks'] ?? null,
            ];
        }

        $values = [
            'timezone' => $timezone,
            'is_final' => $validated['final'] ?? true,
            'peak_hour' => $validated['peak_hour'] ?? null,
            'section_actions' => $validated['section_actions'] ?? null,
            'packages' => $validated['packages'] ?? null,
            'health_indicators' => $validated['health'] ?? null,
        ];

        foreach (ProjectPulse::METRICS as $metric) {
            $values[$metric] = $validated[$metric] ?? null;
        }

        $result = DB::transaction(
            fn (): array => $this->store($project, $date->toDateString(), $values, $accepted),
        );

        $payload = [
            'date' => $date->toDateString(),
            'revision' => $result['revision'],
            'final' => $values['is_final'],
            'screens_accepted' => count($accepted),
        ];

        if ($ignored !== []) {
            $payload['screens_ignored'] = $ignored;
        }

        if ($truncated > 0) {
            $payload['screens_truncated'] = $truncated;
        }

        return response()->json($payload, $result['created'] ? 201 : 200);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, array{views: int|null, clicks: int|null}>  $screens
     * @return array{created: bool, revision: int}
     */
    private function store(Project $project, string $date, array $values, array $screens): array
    {
        $existing = ProjectPulse::query()
            ->forProject($project)
            ->where('pulse_date', $date)
            ->lockForUpdate()
            ->first();

        if ($existing === null) {
            $pulse = ProjectPulse::query()->create([
                'project_id' => $project->id,
                'pulse_date' => $date,
                'revision' => 1,
                ...$values,
            ]);

            $this->syncScreens($pulse, $screens);

            return ['created' => true, 'revision' => 1];
        }

        // القيمة المُزاحة تُحفظ كما كانت — العدّاد يقول إنّ تغييرًا وقع، وهذا
        // يقول ماذا كان قبله.
        $existing->revisions()->create([
            'revision' => $existing->revision,
            'superseded_values' => [
                ...$existing->only([...ProjectPulse::METRICS, 'peak_hour', 'is_final', 'timezone']),
                'section_actions' => $existing->section_actions,
                'packages' => $existing->packages,
                'health_indicators' => $existing->health_indicators,
                'screens' => $existing->screens()
                    ->get(['screen_key', 'views', 'clicks'])
                    ->map(static fn ($row): array => $row->only(['screen_key', 'views', 'clicks']))
                    ->all(),
            ],
        ]);

        $revision = $existing->revision + 1;

        $existing->forceFill([...$values, 'revision' => $revision])->save();

        $existing->screens()->delete();
        $this->syncScreens($existing, $screens);

        return ['created' => false, 'revision' => $revision];
    }

    /** @param array<string, array{views: int|null, clicks: int|null}> $screens */
    private function syncScreens(ProjectPulse $pulse, array $screens): void
    {
        foreach ($screens as $key => $counts) {
            $pulse->screens()->create([
                'screen_key' => $key,
                'views' => $counts['views'],
                'clicks' => $counts['clicks'],
            ]);
        }
    }

    /** @param array<string, mixed> $details */
    private function error(string $code, string $message, array $details, int $status): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => RequestId::current(),
            ],
        ], $status);
    }
}
