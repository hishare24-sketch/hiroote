<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Integrations\Models\ProjectBridge;
use App\Domains\Projects\Models\Project;

/**
 * كل طرق الربط الممكنة بين هاي روت ومشروع خارجي — وثيقة 07.
 *
 * السجل هنا لا في الواجهة: الطريقة التي تُبنى تُضاف مرة واحدة فتظهر في الشاشة
 * وفي الوثيقة وفي الاختبار معًا. ولوحةٌ تعرض طرقًا مكتوبة يدويًا في JSX تنسى
 * تحديث نفسها عند أول طريقة جديدة.
 *
 * والحالة تُحسب من قاعدة البيانات لا تُعلن: «مُهيّأ» يجب أن يعني وجود مفتاح
 * فعّال، لا وجود شرحٍ للطريقة.
 */
class ConnectionMethods
{
    public const READY = 'ready';

    public const AVAILABLE = 'available';

    public const PLANNED = 'planned';

    /**
     * @return list<array<string, mixed>>
     */
    public function forProject(Project $project): array
    {
        $keys = ProjectApiKey::query()->forProject($project)->usable()->count();
        $bridge = ProjectBridge::query()->forProject($project)->first();

        return [
            [
                'key' => 'inbound_api_key',
                'icon' => 'key',
                'title' => 'مفتاح مشروع — وارد',
                'direction' => 'المشروع ← هاي روت',
                'summary' => 'المشروع يسأل هاي روت عن سياق الشاشة، ويسجّل محادثاته، ويرفع ما رصده.',
                'status' => $keys > 0 ? self::READY : self::AVAILABLE,
                'status_note' => $keys > 0
                    ? "{$keys} مفتاحًا فعّالًا"
                    : 'لا مفتاح فعّال بعد',
                'needs' => [
                    'مفتاح يُصدَر من شاشة المشاريع ويُنسخ مرة واحدة',
                    'أن يرسله المشروع في ترويسة Authorization: Bearer',
                ],
                'endpoints' => [
                    'GET /api/v1/context?screen=…',
                    'POST /api/v1/conversations',
                    'POST /api/v1/feedback',
                ],
                'where' => 'المشاريع ← المفاتيح',
                'route' => '/projects',
            ],
            [
                'key' => 'outbound_service_account',
                'icon' => 'user-cog',
                'title' => 'حساب خدمة — صادر',
                'direction' => 'هاي روت ← المشروع',
                'summary' => 'هاي روت يسجّل دخول حساب قراءة في المشروع ويجدّد رمزه تلقائيًا، ثم يقرأ إعداده وإحصاءاته.',
                'status' => match (true) {
                    $bridge?->auth_mode === ProjectBridge::MODE_SERVICE_ACCOUNT
                        && $bridge->isConfigured() => self::READY,
                    default => self::AVAILABLE,
                },
                'status_note' => $bridge?->auth_mode === ProjectBridge::MODE_SERVICE_ACCOUNT
                    && $bridge->isConfigured()
                        ? $bridge->statusLabel()
                        : 'غير مُهيّأ',
                'needs' => [
                    'حساب في المشروع بنطاقَي قراءة فقط (ai:read و health:read)',
                    'عنوان الواجهة متبوعًا ببادئته (مثل /api)',
                ],
                'endpoints' => [
                    'POST /auth/login — لتجديد الرمز',
                    'GET /ai/settings · /ai/usage-analytics · /ai/health · /ai/user-quotas',
                ],
                'where' => 'جسر المشروع ← إعداد الاتصال',
                'route' => '/bridge',
            ],
            [
                'key' => 'outbound_bearer',
                'icon' => 'ticket',
                'title' => 'رمز جاهز — صادر',
                'direction' => 'هاي روت ← المشروع',
                'summary' => 'رمز يُصدَر يدويًا من المشروع ويُلصق في هاي روت. أسرع طريق، وينتهي فجأة يومًا بلا تجديد.',
                'status' => $bridge?->auth_mode === ProjectBridge::MODE_BEARER
                    && $bridge->isConfigured() ? self::READY : self::AVAILABLE,
                'status_note' => $bridge?->auth_mode === ProjectBridge::MODE_BEARER
                    && $bridge->isConfigured()
                        ? $bridge->statusLabel()
                        : 'غير مُهيّأ',
                'needs' => [
                    'رمز طويل الأمد من المشروع بصلاحية قراءة',
                    'متابعة انتهائه يدويًا — لا يُجدَّد تلقائيًا',
                ],
                'endpoints' => ['نفس نقاط حساب الخدمة، بلا تسجيل دخول'],
                'where' => 'جسر المشروع ← إعداد الاتصال',
                'route' => '/bridge',
            ],
            [
                'key' => 'inbound_webhook',
                'icon' => 'webhook',
                'title' => 'Webhooks — وارد لحظي',
                'direction' => 'المشروع ← هاي روت',
                'summary' => 'المشروع يدفع الحدث لحظة وقوعه بدل أن ينتظر هاي روت سؤاله. يفيد للتصعيد والأعطال.',
                'status' => self::PLANNED,
                'status_note' => 'لم يُبنَ بعد',
                'needs' => [
                    'سرّ توقيع مشترك وتحقق من HMAC والطابع الزمني',
                    'قائمة أحداث متفق عليها بين الطرفين',
                ],
                'endpoints' => ['POST /api/v1/webhooks/{event} — مخطَّط'],
                'where' => 'لم يُبنَ',
                'route' => null,
            ],
            [
                'key' => 'outbound_machine_key',
                'icon' => 'shield',
                'title' => 'مفتاح آلة في المشروع — صادر',
                'direction' => 'هاي روت ← المشروع',
                'summary' => 'بديل حساب الخدمة: مفتاح لا يخصّ بشرًا، بنطاقات محدودة، لا ينتهي بانتهاء جلسة.',
                'status' => self::PLANNED,
                'status_note' => 'يتطلب تعديلًا في المشروع الخارجي',
                'needs' => [
                    'أن يدعم المشروع مفاتيح آلة — موازين اليوم يقبل رمز أدمن فقط',
                ],
                'endpoints' => ['نفس نقاط القراءة، بمصادقة مفتاح'],
                'where' => 'لم يُبنَ',
                'route' => null,
            ],
        ];
    }
}
