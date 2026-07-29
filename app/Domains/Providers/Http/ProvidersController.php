<?php

declare(strict_types=1);

namespace App\Domains\Providers\Http;

use App\Domains\Providers\Actions\PerformFailover;
use App\Domains\Providers\Actions\ReorderProviderPriority;
use App\Domains\Providers\Actions\RevokeProviderCredential;
use App\Domains\Providers\Actions\StoreProviderCredential;
use App\Domains\Providers\Actions\ToggleProvider;
use App\Domains\Providers\Enums\FailoverReason;
use App\Domains\Providers\Enums\ProviderSetting;
use App\Domains\Providers\Models\AiFailoverEvent;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\AiProviderCredential;
use App\Domains\Providers\Models\ProviderSettingValue;
use App\Domains\Providers\Services\ProviderHealthService;
use App\Http\Controllers\Controller;
use App\Support\Http\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ProvidersController extends Controller
{
    public function index(): Response
    {
        $providers = AiProvider::query()
            ->with([
                'models' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name'),
                'credentials' => fn ($query) => $query->latest('id'),
            ])
            ->orderBy('priority')
            ->get();

        $active = $providers->firstWhere('is_active', true);
        $intervalMinutes = config()->integer('hiroote.health_check.interval_minutes', 60);

        return Inertia::render('Providers/Index', [
            'systemStatus' => SystemStatus::current(),
            'providers' => $providers->map($this->presentProvider(...))->values(),
            'activeProvider' => $active === null ? null : $this->presentProvider($active),
            'healthCheck' => [
                'intervalMinutes' => $intervalMinutes,
                'failureThreshold' => config()->integer('hiroote.health_check.failure_threshold', 3),
                // ثوانٍ متبقية حتى الفحص القادم — الواجهة تعدّها تنازليًا.
                'nextCheckInSeconds' => $this->secondsUntilNextCheck($active?->last_checked_at, $intervalMinutes),
            ],
            'failoverPolicies' => collect(ProviderSetting::failoverPolicies())
                ->map(fn (ProviderSetting $setting): array => [
                    'key' => $setting->value,
                    'label' => $setting->label(),
                    'enabled' => ProviderSettingValue::isEnabled($setting),
                ])
                ->values(),
            'recentFailovers' => AiFailoverEvent::query()
                ->with(['fromProvider', 'toProvider', 'triggeredBy'])
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (AiFailoverEvent $event): array => [
                    'id' => $event->id,
                    'from' => $event->fromProvider?->name,
                    'to' => $event->toProvider?->name,
                    'reason' => $event->reason->label(),
                    'triggered_by' => $event->triggeredBy?->name,
                    'created_at' => $event->created_at->toIso8601String(),
                ])
                ->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentProvider(AiProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'name' => $provider->name,
            'slug' => $provider->slug,
            'priority' => $provider->priority,
            'is_enabled' => $provider->is_enabled,
            'is_active' => $provider->is_active,
            'status' => $provider->status->value,
            'status_label' => $provider->status->label(),
            'consecutive_failures' => $provider->consecutive_failures,
            'last_checked_at' => $provider->last_checked_at?->toIso8601String(),
            'latency_ms' => $provider->avg_latency_ms,
            'error_rate' => (float) $provider->error_rate,
            'balance' => (float) $provider->balance,
            'burn_rate' => (float) $provider->burn_rate_per_minute,
            'currency' => $provider->currency,
            'default_model' => $provider->models->firstWhere('is_default', true)?->display_name,
            'models' => $provider->models->map(fn ($model): array => [
                'id' => $model->id,
                'name' => $model->name,
                'display_name' => $model->display_name,
                'is_default' => $model->is_default,
            ])->values(),
            'credentials' => $provider->credentials->map(fn (AiProviderCredential $credential): array => [
                'id' => $credential->id,
                'label' => $credential->label,
                'key_hint' => $credential->key_hint,
                'is_active' => $credential->is_active,
                'last_used_at' => $credential->last_used_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function secondsUntilNextCheck(?Carbon $lastCheck, int $intervalMinutes): ?int
    {
        if ($lastCheck === null) {
            return null;
        }

        $next = $lastCheck->copy()->addMinutes($intervalMinutes);

        return max(0, (int) now()->diffInSeconds($next, absolute: false));
    }

    public function toggle(Request $request, AiProvider $provider, ToggleProvider $action): RedirectResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        $action->handle($provider, (bool) $validated['enabled'], $request->user()?->id);

        return back()->with('success', $validated['enabled'] ? 'تم تفعيل المزود.' : 'تم تعطيل المزود.');
    }

    public function reorder(Request $request, ReorderProviderPriority $action): RedirectResponse
    {
        /** @var array{order: list<int>} $validated */
        $validated = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);

        $action->handle($validated['order']);

        return back()->with('success', 'تم تحديث ترتيب الأولوية.');
    }

    public function storeCredential(
        StoreCredentialRequest $request,
        AiProvider $provider,
        StoreProviderCredential $action,
    ): RedirectResponse {
        $action->handle(
            provider: $provider,
            label: $request->string('label')->value(),
            apiKey: $request->string('api_key')->value(),
            createdBy: $request->user()?->id,
        );

        return back()->with('success', 'تم حفظ المفتاح مشفرًا وإبطال المفاتيح السابقة.');
    }

    public function revokeCredential(
        AiProvider $provider,
        AiProviderCredential $credential,
        RevokeProviderCredential $action,
    ): RedirectResponse {
        abort_unless($credential->provider_id === $provider->id, 404);

        $action->handle($credential);

        return back()->with('success', 'تم إبطال المفتاح.');
    }

    public function activate(Request $request, AiProvider $provider, PerformFailover $failover): RedirectResponse
    {
        $event = $failover->handle(
            reason: FailoverReason::Manual,
            to: $provider,
            triggeredBy: $request->user()?->id,
        );

        return $event === null
            ? back()->with('error', 'تعذر التحويل: المزود معطل أو نشط بالفعل.')
            : back()->with('success', "تم التحويل إلى {$provider->name}.");
    }

    public function check(AiProvider $provider, ProviderHealthService $health): RedirectResponse
    {
        $check = $health->check($provider);

        return $check->healthy
            ? back()->with('success', "الفحص ناجح — زمن الاستجابة {$check->latency_ms} مللي ثانية.")
            : back()->with('error', "الفحص فشل: {$check->error_message}");
    }
}
