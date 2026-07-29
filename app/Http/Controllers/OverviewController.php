<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Administration\Enums\AuditCategory;
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
 * مؤشرات المحادثات والتوكن تصل مع محرك القياس في المرحلة الثانية. بدل حجز
 * نصف الشاشة لبطاقات فارغة حتى ذلك الحين، تعرض الشاشة ما هو مقيس فعلًا الآن —
 * البنية والمزودون والمفاتيح والفحص — وتظهر خطوات التشغيل الناقصة.
 */
class OverviewController extends Controller
{
    public function __invoke(): Response
    {
        $providers = AiProvider::query()
            ->with(['models' => fn ($query) => $query->where('is_default', true)])
            ->orderBy('priority')
            ->get();

        $enabled = $providers->where('is_enabled', true);
        $withKey = $enabled->filter(fn (AiProvider $provider): bool => $provider->activeCredential() !== null);
        $active = $providers->firstWhere('is_active', true);
        $checked = $enabled->whereNotNull('last_checked_at');

        return Inertia::render('Overview/Index', [
            'systemStatus' => SystemStatus::current(),

            'stats' => [
                [
                    'key' => 'providers',
                    'label' => 'المزودون المفعّلون',
                    'value' => (string) $enabled->count(),
                    'caption' => 'من أصل '.$providers->count().' مزود',
                    'tone' => $enabled->isEmpty() ? 'danger' : 'accent',
                    'progress' => $providers->isEmpty() ? 0 : ($enabled->count() / $providers->count()) * 100,
                ],
                [
                    'key' => 'keys',
                    'label' => 'المفاتيح الفعالة',
                    'value' => (string) $withKey->count(),
                    'caption' => $withKey->count() === $enabled->count()
                        ? 'كل مزود مفعّل لديه مفتاح'
                        : 'ينقص '.($enabled->count() - $withKey->count()).' مفتاح',
                    'tone' => $withKey->isEmpty() ? 'danger' : ($withKey->count() < $enabled->count() ? 'warning' : 'success'),
                    'progress' => $enabled->isEmpty() ? 0 : ($withKey->count() / $enabled->count()) * 100,
                ],
                [
                    'key' => 'health',
                    'label' => 'المزودون المفحوصون',
                    'value' => (string) $checked->count(),
                    'caption' => $checked->count() === $enabled->count()
                        ? 'آخر فحص: '.($enabled->max('last_checked_at')?->diffForHumans() ?? '—')
                        : 'بانتظار أول فحص ذاتي',
                    'tone' => $checked->isEmpty() ? 'neutral' : 'info',
                    'progress' => $enabled->isEmpty() ? 0 : ($checked->count() / $enabled->count()) * 100,
                ],
                [
                    'key' => 'audit',
                    'label' => 'أحداث اليوم',
                    'value' => (string) AuditLog::query()->whereDate('created_at', today())->count(),
                    'caption' => 'مسجلة في سجل التدقيق',
                    'tone' => 'success',
                ],
            ],

            'setupSteps' => $this->setupSteps($enabled->count(), $withKey->count(), $checked->count(), $active),

            'providers' => $providers->map(fn (AiProvider $provider): array => [
                'id' => $provider->id,
                'name' => $provider->name,
                'model' => $provider->models->first()?->display_name,
                'is_active' => $provider->is_active,
                'is_enabled' => $provider->is_enabled,
                'status' => $provider->status->value,
                'status_label' => $provider->status->label(),
                'has_key' => $provider->activeCredential() !== null,
                'latency_ms' => $provider->avg_latency_ms,
                'balance' => (float) $provider->balance,
                'currency' => $provider->currency === 'SAR' ? 'ر.س' : $provider->currency,
            ])->values(),

            'quickControls' => collect(ProviderSetting::quickControls())
                ->map(fn (ProviderSetting $setting): array => [
                    'key' => $setting->value,
                    'label' => $setting->label(),
                    'enabled' => ProviderSettingValue::isEnabled($setting),
                ])
                ->values(),

            'recentActivity' => AuditLog::query()
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(function (AuditLog $log): array {
                    $category = AuditCategory::fromAction($log->action);

                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'category' => $category->label(),
                        'tone' => $category->tone(),
                        'actor' => $log->actor_label ?? 'النظام',
                        'created_at' => $log->created_at->toIso8601String(),
                    ];
                })
                ->values(),
        ]);
    }

    /**
     * خطوات تشغيل النظام — تختفي اللوحة كاملة عند اكتمالها.
     *
     * @return list<array{title: string, detail: string, done: bool, href: ?string, cta: ?string}>
     */
    private function setupSteps(int $enabled, int $withKey, int $checked, ?AiProvider $active): array
    {
        return [
            [
                'title' => 'تفعيل مزود واحد على الأقل',
                'detail' => $enabled > 0 ? "{$enabled} مزود مفعّل" : 'لا يوجد مزود مفعّل',
                'done' => $enabled > 0,
                'href' => '/providers',
                'cta' => 'إدارة المزودين',
            ],
            [
                'title' => 'إضافة مفتاح API',
                'detail' => $withKey > 0
                    ? "{$withKey} مزود لديه مفتاح فعال"
                    : 'يحفظ المفتاح مشفرًا ولا يظهر كاملًا بعدها',
                'done' => $withKey > 0,
                'href' => '/providers',
                'cta' => 'إضافة مفتاح',
            ],
            [
                'title' => 'تحديد المزود النشط',
                'detail' => $active === null ? 'لا يوجد مزود نشط يستقبل الطلبات' : "المزود النشط: {$active->name}",
                'done' => $active !== null,
                'href' => '/providers',
                'cta' => 'تحديد المزود',
            ],
            [
                'title' => 'تشغيل أول فحص ذاتي',
                'detail' => $checked > 0
                    ? "{$checked} مزود تم فحصه"
                    : 'يتأكد من صلاحية المفتاح والاتصال بالمزود',
                'done' => $checked > 0,
                'href' => '/providers',
                'cta' => 'تنفيذ فحص',
            ],
            [
                'title' => 'ربط محرك المحادثات',
                'detail' => 'يفعّل مؤشرات التوكن والتكلفة والمحادثات — المرحلة 2',
                'done' => false,
                'href' => null,
                'cta' => null,
            ],
        ];
    }
}
