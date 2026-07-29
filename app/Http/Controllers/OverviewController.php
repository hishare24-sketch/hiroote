<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Administration\Models\AuditLog;
use App\Domains\Providers\Enums\ProviderSetting;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\ProviderSettingValue;
use App\Support\Http\SystemStatus;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة النظرة العامة — وثيقة التصميم §4.
 *
 * مؤشرات المحادثات والتوكن تأتي من محرك التكلفة في المرحلة الثانية؛ حتى ذلك
 * الحين تُرسل `null` وتعرض الواجهة حالة «لا توجد بيانات» بدل رقم مختلق.
 */
class OverviewController extends Controller
{
    public function __invoke(): Response
    {
        $providers = AiProvider::query()
            ->enabled()
            ->with(['models' => fn ($query) => $query->where('is_default', true)])
            ->orderBy('priority')
            ->get()
            ->map(fn (AiProvider $provider): array => [
                'id' => $provider->id,
                'name' => $provider->name,
                'model' => $provider->models->first()?->display_name,
                'is_active' => $provider->is_active,
                'priority' => $provider->priority,
                'status' => $provider->status->value,
            ]);

        return Inertia::render('Overview/Index', [
            'systemStatus' => SystemStatus::current(),
            'metrics' => [
                'tokens' => null,
                'conversations' => null,
                'avgDuration' => null,
                'autoResolutionRate' => null,
            ],
            'escalations' => null,
            'providers' => $providers,
            'quickControls' => collect(ProviderSetting::quickControls())
                ->map(fn (ProviderSetting $setting): array => [
                    'key' => $setting->value,
                    'label' => $setting->label(),
                    'enabled' => ProviderSettingValue::isEnabled($setting),
                ])
                ->values(),
            'attentionAlerts' => $this->attentionAlerts(),
            'recentActivity' => AuditLog::query()
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (AuditLog $log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->actor_label ?? 'النظام',
                    'created_at' => $log->created_at->toIso8601String(),
                ]),
        ]);
    }

    /**
     * تنبيهات تحتاج إجراء — مشتقة من الحالة الفعلية، لا قائمة ثابتة.
     *
     * @return list<array{title: string, detail: string, tone: string, href: string}>
     */
    private function attentionAlerts(): array
    {
        $alerts = [];

        $withoutKey = AiProvider::query()
            ->enabled()
            ->whereDoesntHave('credentials', fn ($query) => $query->where('is_active', true))
            ->pluck('name');

        if ($withoutKey->isNotEmpty()) {
            $alerts[] = [
                'title' => 'مزودون بلا مفتاح فعال',
                'detail' => $withoutKey->implode('، '),
                'tone' => 'danger',
                'href' => '/providers',
            ];
        }

        $lowBalance = AiProvider::query()
            ->enabled()
            ->where('balance', '<', 2000)
            ->orderBy('balance')
            ->first();

        if ($lowBalance !== null) {
            $alerts[] = [
                'title' => 'اقتراب حد الميزانية',
                'detail' => "المتبقي لدى {$lowBalance->name}: ".number_format((float) $lowBalance->balance).' '.($lowBalance->currency === 'SAR' ? 'ر.س' : $lowBalance->currency),
                'tone' => 'warning',
                'href' => '/usage',
            ];
        }

        $neverChecked = AiProvider::query()->enabled()->whereNull('last_checked_at')->count();

        if ($neverChecked > 0) {
            $alerts[] = [
                'title' => 'مزودون لم يُفحصوا بعد',
                'detail' => "{$neverChecked} مزود بانتظار أول فحص ذاتي",
                'tone' => 'info',
                'href' => '/providers',
            ];
        }

        return $alerts;
    }
}
