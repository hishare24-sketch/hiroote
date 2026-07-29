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
     * @var list<array{name: string, caps?: array<string, bool>}>
     */
    private const HI_SHARE = [
        ['name' => 'الحساب والملف الشخصي', 'caps' => ['execute_action' => true]],
        ['name' => 'العضويات'],
        ['name' => 'الحملات', 'caps' => ['read_files' => true]],
        ['name' => 'المساحات الإعلانية'],
        ['name' => 'المشاركات', 'caps' => ['read_files' => true]],
        ['name' => 'المحتوى والتوثيق', 'caps' => ['read_files' => true]],
        ['name' => 'المشاهدات', 'caps' => ['create_ticket' => false]],
        ['name' => 'القسائم'],
        // المال لا يُنفَّذ فيه إجراء تلقائي — يُحوَّل بشريًا (وثيقة 06 §10).
        ['name' => 'المحفظة', 'caps' => ['execute_action' => false, 'suggest_action' => false]],
        ['name' => 'السحب والتحويلات', 'caps' => ['execute_action' => false, 'suggest_action' => false]],
        ['name' => 'الاعتراضات', 'caps' => ['execute_action' => false]],
        ['name' => 'الإشعارات', 'caps' => ['human_handoff' => false]],
        ['name' => 'الدعم الفني', 'caps' => ['read_files' => true]],
        ['name' => 'الوكلاء والمسوقون'],
        ['name' => 'الإعدادات', 'caps' => ['execute_action' => true]],
        // السياسات معرفة تُقرأ ولا بيانات تُستدعى.
        ['name' => 'السياسات والشروط', 'caps' => ['api_call' => false, 'show_data' => false, 'create_ticket' => false]],
    ];

    /**
     * أقسام موازين — طبيعة مختلفة تمامًا: محاسبة لا مشاركات.
     *
     * @var list<array{name: string, caps?: array<string, bool>}>
     */
    private const MAWAZIN = [
        ['name' => 'المشاريع'],
        ['name' => 'الدفتر والقيود', 'caps' => ['execute_action' => false, 'suggest_action' => false]],
        ['name' => 'المحافظ', 'caps' => ['execute_action' => false]],
        ['name' => 'الفواتير', 'caps' => ['read_files' => true]],
        ['name' => 'الاشتراكات'],
        ['name' => 'التقارير المالية', 'caps' => ['create_ticket' => false]],
        ['name' => 'الأعضاء والفرق'],
        ['name' => 'الدعم', 'caps' => ['read_files' => true]],
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
                ProjectSection::query()->updateOrCreate(
                    ['project_id' => $project->id, 'slug' => Slug::make($section['name'], 'section')],
                    [
                        ...$this->capabilities($section['caps'] ?? []),
                        'name' => $section['name'],
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
