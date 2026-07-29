<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Seeder;

/**
 * شاشات موازين بمفاتيحها — ما يجيب به `GET /api/v1/context`.
 *
 * **المفتاح هو اسم مسار موازين** (`finance-page`) لا رابطه ولا عنوانه: الاسم
 * موجود في `front/src/router` و`NAV_ITEMS` أصلًا، ولا يتغيّر بإعادة تنظيم
 * التوجيه ولا بتحسين العرض — وكلاهما يقطع الصلة صامتًا (وثيقة 07 §2.4).
 *
 * والأسماء والأوصاف مقروءة من موازين نفسه: `NAV_ITEMS` و`MAZEEN_KNOWLEDGE` في
 * `front/src/modules/chat/aiContext.ts`. **لا يُكتب هنا وصفٌ لم يُقرأ من هناك**
 * — سياقٌ مخترَع يجعل المساعد يشرح ميزة لا وجود لها بثقة تامة.
 */
class MawazinScreensSeeder extends Seeder
{
    /**
     * @var array<string, list<array{key: string, name: string, path: string, about: string, elements?: list<string>, actions?: list<string>, states?: list<string>}>>
     */
    private const SCREENS = [
        'الأساس' => [
            [
                'key' => 'dashboard-page',
                'name' => 'لوحة التحكم',
                'path' => '/',
                'about' => 'نظرة مجمَّعة على المشاريع والأرصدة والمستحقات القريبة.',
                'elements' => ['إجمالي الأرصدة', 'بطاقات المشاريع', 'المستحقات القريبة'],
            ],
            [
                'key' => 'projects-page',
                'name' => 'المشاريع',
                'path' => '/projects',
                'about' => 'كل مشروع محفظة مالية مستقلة برصيد وأعضاء وأدوار؛ كل عملية تُنسب لمشروع.',
                'elements' => ['قائمة المشاريع', 'الرصيد', 'الأعضاء والأدوار'],
                'actions' => ['إنشاء مشروع', 'دعوة عضو', 'أرشفة'],
            ],
            [
                'key' => 'finance-page',
                'name' => 'المالية',
                'path' => '/finance',
                'about' => 'هبٌّ موحّد بتبويبات: نظرة · إيرادات · مصروفات · تحويلات · محاسبة · ذمم · التزامات · توزيع التكاليف. الذمم والالتزامات تبويبات هنا لا شاشات منفصلة.',
                'elements' => ['التبويبات الثمانية', 'جدول العمليات', 'التصنيفات والمرفقات'],
                'actions' => ['تسجيل دخل', 'تسجيل مصروف', 'تحويل بين المشاريع'],
                'states' => ['ذمة مفتوحة', 'جزئية', 'مسدَّدة', 'متنازَع عليها'],
            ],
            [
                'key' => 'entities-page',
                'name' => 'مركز الجهات',
                'path' => '/entities',
                'about' => 'سجل موحَّد للأطراف (عملاء وموردون وشركاء) يربط ذممهم وفواتيرهم وعملياتهم في مكان واحد.',
            ],
            [
                'key' => 'calendar-page',
                'name' => 'التقويم',
                'path' => '/calendar',
                'about' => 'تجميع زمني للمستحقات: ذمم والتزامات وانتهاء متابعات، ومعها التذكيرات الشخصية.',
            ],
        ],
        'التحصيل والمال الداخل' => [
            [
                'key' => 'invoices-page',
                'name' => 'الفواتير الفورية',
                'path' => '/invoices',
                'about' => 'روابط دفع للعملاء؛ سدادها يُنشئ عملية دخل تلقائيًا. تدعم مبلغًا ثابتًا أو مفتوحًا وحدّ استخدامات وصلاحية ورمز QR.',
                'states' => ['نشطة', 'مدفوعة', 'منتهية'],
                // الدفع له وضعان في موازين، والصفحة نفسها تصرّح بأيّهما يعمل.
                'actions' => ['إنشاء رابط دفع', 'مشاركة الرابط'],
            ],
            [
                'key' => 'rosca-page',
                'name' => 'الجمعيات المالية',
                'path' => '/rosca',
                'about' => 'جمعيات دوّارة: كل عضو يساهم بمبلغ ثابت كل دورة، ويستلم المجموع عضو واحد بالترتيب أو بالسمعة. المنصة طبقة تسجيل وإثبات إجرائي فقط — لا تحفظ مالًا ولا تحوّله.',
                'elements' => ['الدورات', 'المساهمات', 'نظام الثقة والسمعة'],
            ],
            [
                'key' => 'zakat-page',
                'name' => 'حاسبة الزكاة',
                'path' => '/zakat',
                'about' => 'تحسب زكاة المال (النِّصاب ونسبة ٢٫٥٪) من الأصول القابلة للزكاة بعد خصم الخصوم.',
            ],
        ],
        'التشغيل والمتابعة' => [
            [
                'key' => 'tasks-page',
                'name' => 'الإجراءات المطلوبة',
                'path' => '/tasks',
                'about' => 'لوحة تجمع ما ينتظر تصرّف المستخدم عبر الأقسام كلها.',
            ],
            [
                'key' => 'taskboard-page',
                'name' => 'المهامّ',
                'path' => '/task-board',
                'about' => 'مهام المشروع بحالات وأولويات ومُكلَّفين واستحقاقات، مع تفريع وتبعيات وتكرار مجدوَل ومكافآت تُصرف كتسوية عضو عند الاعتماد.',
            ],
            [
                'key' => 'requests-page',
                'name' => 'الطلبات',
                'path' => '/requests',
                'about' => 'طلبات داخلية تنتظر اعتمادًا أو رفضًا ممّن يملك صلاحية الاعتماد.',
                'states' => ['بانتظار الاعتماد', 'معتمَد', 'مرفوض'],
            ],
            [
                'key' => 'assets-page',
                'name' => 'الأصول',
                'path' => '/assets',
                'about' => 'سجل الممتلكات والصيانة والضمانات.',
            ],
            [
                'key' => 'trackings-page',
                'name' => 'المتابعات',
                'path' => '/trackings',
                'about' => 'تنبيهات العقود والضمانات والتراخيص بمواعيدها.',
            ],
            [
                'key' => 'meetings-page',
                'name' => 'الاجتماعات',
                'path' => '/meetings',
                'about' => 'اجتماع داخل المشروع بلا مزوّد خارجي. الانضمام يسجّل الحضور تلقائيًا، ويمكن دعوة ضيوف بلا حساب برابط محدود الصلاحية — والدعوة وصولٌ للغرفة وحدها لا عضوية في المشروع.',
            ],
        ],
        'المستندات والمحتوى' => [
            [
                'key' => 'documents-page',
                'name' => 'المستندات',
                'path' => '/documents',
                'about' => 'توليد مستندات (عروض أسعار وفواتير واتفاقيات وأوامر دفع) من قوالب قابلة للتخصيص.',
            ],
            [
                'key' => 'archive-page',
                'name' => 'الأرشيف',
                'path' => '/archive',
                'about' => 'مكتبة مرفقات موحّدة بقراءة ذكية وبحث وربط بالكيانات، ونسخ احتياطي إلى Google Drive لمن فعّله. والمسح الضوئي الذكي محكوم بحصة شهرية في الباقة.',
            ],
            [
                'key' => 'templates-page',
                'name' => 'مولّد القوالب',
                'path' => '/templates',
                'about' => 'إنشاء القوالب التي تُبنى عليها المستندات وتعديلها.',
            ],
            [
                'key' => 'surveys-page',
                'name' => 'الاستبيانات',
                'path' => '/surveys',
                'about' => 'إنشاء استبيانات بثلاثة عشر نوع سؤال، وجمع الردود عبر رابط عام أو روابط فردية متتبَّعة، وتحليلها.',
                'states' => ['نشط', 'متوقّف مؤقتًا', 'مغلق', 'مؤرشف'],
            ],
            [
                'key' => 'esign-page',
                'name' => 'التوقيع الإلكتروني',
                'path' => '/esign',
                'about' => 'إرسال مستند لموقِّعين عبر روابط خاصة، بتحقق برمز بريدي (OTP) وسجل أحداث مسلسل البصمات وشهادة إتمام.',
            ],
            [
                'key' => 'marketing-page',
                'name' => 'التسويق والنشر',
                'path' => '/marketing',
                'about' => 'إعداد المحتوى ونشره على المنصات المربوطة بجدولة وأتمتة، مع تقارير أداء. قد يعمل بوضع محاكاة بلا نشر فعلي حتى يُربط المزوّد.',
            ],
        ],
        'الحوكمة والإعدادات' => [
            [
                'key' => 'notifications-page',
                'name' => 'الإشعارات',
                'path' => '/notifications',
                'about' => 'لكل مستخدم تفضيلات لكل قسم وقنوات (داخل التطبيق وبريد وواتساب). شكوى عدم وصول إشعار تبدأ من هنا.',
            ],
            [
                'key' => 'audit-page',
                'name' => 'سجل العمليات',
                'path' => '/audit',
                'about' => 'أثر تدقيق لمن فعل ماذا ومتى — للمراجعة والمساءلة.',
            ],
            [
                'key' => 'settings-page',
                'name' => 'الإعدادات',
                'path' => '/settings',
                'about' => 'الأدوار والصلاحيات لكل عضوية، والاشتراك والباقة التي تحدد الحدود (المشاريع والأعضاء والتخزين وحصص الذكاء).',
            ],
        ],
    ];

    public function run(): void
    {
        $project = Project::query()->where('slug', 'mawazin')->first();

        if ($project === null) {
            return;
        }

        $sections = ProjectSection::query()
            ->where('project_id', $project->id)
            ->get()
            ->keyBy('name');

        foreach (self::SCREENS as $sectionName => $screens) {
            $section = $sections->get($sectionName);

            if (! $section instanceof ProjectSection) {
                $this->command->warn("قسم «{$sectionName}» غير موجود — شغّل SectionsSeeder أولًا.");

                continue;
            }

            foreach ($screens as $index => $screen) {
                // المفتاح هو المُعرِّف لا الاسم: تحسين العرض يغيّر الاسم، وصفٌّ
                // ثانٍ بالمفتاح نفسه يكسر الفهرس الفريد بدل أن يُحدِّث.
                KnowledgeScreen::query()->updateOrCreate(
                    ['project_id' => $project->id, 'key' => $screen['key']],
                    [
                        'section_id' => $section->id,
                        'name' => $screen['name'],
                        'path' => $screen['path'],
                        'description' => $screen['about'],
                        'elements' => $screen['elements'] ?? null,
                        'actions' => $screen['actions'] ?? null,
                        'states' => $screen['states'] ?? null,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }
    }
}
