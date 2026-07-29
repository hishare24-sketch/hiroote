<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Assistants\Actions\ProvisionAssistantDefaults;
use App\Domains\Assistants\Enums\SectionCapability;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Projects\Models\Project;
use App\Support\Text\Slug;
use Illuminate\Database\Seeder;

/**
 * أقسام كل مشروع وسلوك مساعده — وثيقة 06 §12 و§13 و§14.
 *
 * القائمتان مختلفتان عمدًا: أقسام Hi-Share ليست أقسام موازين، وهذا جوهر
 * ADR-0003. الأقسام هنا نقطة انطلاق قابلة للتحرير من الشاشة، لا قائمة مغلقة.
 */
class SectionsSeeder extends Seeder
{
    /**
     * أقسام Hi-Share الستة عشر — وثيقة 06 §14.
     *
     * @var list<array{name: string, about: string, caps?: array<string, bool>}>
     */
    private const HI_SHARE = [
        ['name' => 'الحساب والملف الشخصي', 'about' => 'البيانات الشخصية والتوثيق وإعدادات الحساب.', 'caps' => ['execute_action' => true]],
        ['name' => 'العضويات', 'about' => 'خطط العضوية ومزاياها وتجديدها.'],
        ['name' => 'الحملات', 'about' => 'شروط الحملات والانضمام إليها ومكافآتها.', 'caps' => ['read_files' => true]],
        ['name' => 'المساحات الإعلانية', 'about' => 'عرض المساحات وحجزها وأسعارها.'],
        ['name' => 'المشاركات', 'about' => 'رفع المشاركات وتوثيقها ومتابعة حالتها.', 'caps' => ['read_files' => true]],
        ['name' => 'المحتوى والتوثيق', 'about' => 'معايير المحتوى وأسباب القبول والرفض.', 'caps' => ['read_files' => true]],
        ['name' => 'المشاهدات', 'about' => 'احتساب المشاهدات ومصادرها ودقّتها.', 'caps' => ['create_ticket' => false]],
        ['name' => 'القسائم', 'about' => 'إصدار القسائم والتحقق منها وصلاحيتها.'],
        // المال لا يُنفَّذ فيه إجراء تلقائي — يُحوَّل بشريًا (وثيقة 06 §10).
        ['name' => 'المحفظة', 'about' => 'الرصيد وحركاته والأرباح ومواعيد صرفها.', 'caps' => ['execute_action' => false, 'suggest_action' => false]],
        ['name' => 'السحب والتحويلات', 'about' => 'طلبات السحب والحسابات البنكية ومددها.', 'caps' => ['execute_action' => false, 'suggest_action' => false]],
        ['name' => 'الاعتراضات', 'about' => 'تقديم الاعتراض ومساره ومدة البتّ فيه.', 'caps' => ['execute_action' => false]],
        ['name' => 'الإشعارات', 'about' => 'أنواع الإشعارات وضبط تفضيلاتها.', 'caps' => ['human_handoff' => false]],
        ['name' => 'الدعم الفني', 'about' => 'قنوات الدعم وأوقاته وأولويات التذاكر.', 'caps' => ['read_files' => true]],
        ['name' => 'الوكلاء والمسوقون', 'about' => 'برنامج الوكلاء وعمولاته وشروطه.'],
        ['name' => 'الإعدادات', 'about' => 'إعدادات التطبيق واللغة والخصوصية.', 'caps' => ['execute_action' => true]],
        // السياسات معرفة تُقرأ ولا بيانات تُستدعى.
        ['name' => 'السياسات والشروط', 'about' => 'شروط الاستخدام وسياسة الخصوصية.', 'caps' => ['api_call' => false, 'show_data' => false, 'create_ticket' => false]],
    ];

    /**
     * أقسام موازين — طبيعة مختلفة تمامًا: محاسبة لا مشاركات.
     *
     * @var list<array{name: string, about: string, caps?: array<string, bool>}>
     */
    private const MAWAZIN = [
        ['name' => 'المشاريع', 'about' => 'إنشاء المشاريع المحاسبية وإدارتها.'],
        ['name' => 'الدفتر والقيود', 'about' => 'القيود المحاسبية وميزان المراجعة.', 'caps' => ['execute_action' => false, 'suggest_action' => false]],
        ['name' => 'المحافظ', 'about' => 'أرصدة المحافظ وحركاتها وتسوياتها.', 'caps' => ['execute_action' => false]],
        ['name' => 'الفواتير', 'about' => 'إصدار الفواتير وتحصيلها وأرشفتها.', 'caps' => ['read_files' => true]],
        ['name' => 'الاشتراكات', 'about' => 'خطط الاشتراك وتجديدها وإلغاؤها.'],
        ['name' => 'التقارير المالية', 'about' => 'التقارير الدورية وتصديرها وقراءتها.', 'caps' => ['create_ticket' => false]],
        ['name' => 'الأعضاء والفرق', 'about' => 'أعضاء المشروع وأدوارهم وصلاحياتهم.'],
        ['name' => 'الدعم', 'about' => 'قنوات الدعم وأوقات الاستجابة.', 'caps' => ['read_files' => true]],
    ];

    public function __construct(private readonly ProvisionAssistantDefaults $provision) {}

    public function run(): void
    {
        foreach (['hi-share' => self::HI_SHARE, 'mawazin' => self::MAWAZIN] as $slug => $sections) {
            $project = Project::query()->where('slug', $slug)->first();

            if ($project === null) {
                continue;
            }

            $this->provision->handle($project);

            foreach ($sections as $index => $section) {
                // المفتاح الاسم لا الرابط: الرابط اشتقاقٌ من الاسم، وتغيير دالة
                // الاشتقاق يجعل المزارع يفقد صفوفه فيزرع مجموعة ثانية كاملة.
                ProjectSection::query()->updateOrCreate(
                    ['project_id' => $project->id, 'name' => $section['name']],
                    [
                        ...$this->capabilities($section['caps'] ?? []),
                        'slug' => Slug::make($section['name'], 'section'),
                        'description' => $section['about'],
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }
    }

    /**
     * القدرات الافتراضية مع ما يخالفها في هذا القسم.
     *
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private function capabilities(array $overrides): array
    {
        $values = [];

        foreach (SectionCapability::cases() as $capability) {
            $values[$capability->value] = $overrides[$capability->value] ?? $capability->defaultEnabled();
        }

        return $values;
    }
}
