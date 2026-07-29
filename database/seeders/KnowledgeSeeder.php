<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Enums\SourceKind;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Knowledge\Models\KnowledgeSource;
use App\Domains\Knowledge\Models\KnowledgeTag;
use App\Domains\Knowledge\Models\KnowledgeVersion;
use App\Domains\Projects\Models\Project;
use App\Support\Text\Slug;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * معرفة تجريبية — **لا تُشغَّل في الإنتاج**.
 *
 * التغطية متفاوتة عمدًا: بعض الأقسام مكتملة وبعضها لم يبدأ، فتُظهر شاشة قاعدة
 * المعرفة تدرّجها الحقيقي بدل صفٍّ متطابق يخفي معنى «نسبة الاكتمال».
 */
class KnowledgeSeeder extends Seeder
{
    /** @var array<string, list<array{title: string, kind: KnowledgeKind, status: KnowledgeStatus, summary: string, body: string, tags: list<string>}>> */
    private const ITEMS = [
        'المحفظة' => [
            [
                'title' => 'مواعيد صرف الأرباح',
                'kind' => KnowledgeKind::Policy,
                'status' => KnowledgeStatus::Published,
                'summary' => 'الصرف في اليوم الخامس من كل شهر للأرصدة المؤهلة.',
                'body' => "تُصرف الأرباح في اليوم الخامس من كل شهر ميلادي.\n\nالشروط:\n- تجاوز الرصيد الحد الأدنى (200 ر.س).\n- اكتمال توثيق الحساب البنكي.\n- عدم وجود اعتراض مفتوح على المشاركة.\n\nإن صادف الخامس عطلة رسمية يُرحَّل إلى أول يوم عمل بعده.",
                'tags' => ['أرباح', 'صرف'],
            ],
            [
                'title' => 'لماذا تأخر طلب السحب؟',
                'kind' => KnowledgeKind::Faq,
                'status' => KnowledgeStatus::Published,
                'summary' => 'الأسباب الأربعة الأكثر تكرارًا لتأخر السحب.',
                'body' => "1. مراجعة مالية دورية — تستغرق يومي عمل.\n2. بيانات بنكية غير مطابقة لاسم صاحب الحساب.\n3. طلب أُنشئ بعد موعد القطع اليومي (الساعة 2 ظهرًا).\n4. اعتراض مفتوح على إحدى المشاركات المرتبطة.",
                'tags' => ['سحب'],
            ],
            [
                'title' => 'خطوات تعديل الحساب البنكي',
                'kind' => KnowledgeKind::Procedure,
                'status' => KnowledgeStatus::Review,
                'summary' => 'إجراء يحتاج تحققًا إضافيًا قبل اعتماده.',
                'body' => "1. افتح المحفظة ← الإعدادات البنكية.\n2. اضغط «تعديل الحساب».\n3. أرفق صورة الآيبان باسمك.\n4. انتظر التحقق (يوم عمل واحد).\n\nملاحظة: تُجمَّد السحوبات حتى اكتمال التحقق.",
                'tags' => ['سحب', 'توثيق'],
            ],
        ],
        'الحملات' => [
            [
                'title' => 'شروط الانضمام لحملة',
                'kind' => KnowledgeKind::Policy,
                'status' => KnowledgeStatus::Published,
                'summary' => 'حساب موثّق و500 متابع كحد أدنى.',
                'body' => "للانضمام إلى أي حملة:\n- حساب موثّق.\n- 500 متابع على الأقل.\n- عدم وجود مخالفة نشطة خلال 90 يومًا.\n\nبعض الحملات تضيف شروطًا خاصة تظهر في صفحتها.",
                'tags' => ['حملات', 'انضمام'],
            ],
            [
                'title' => 'أسباب رفض رابط المشاركة',
                'kind' => KnowledgeKind::Faq,
                'status' => KnowledgeStatus::Published,
                'summary' => 'الرابط لا يطابق الحملة أو الحساب.',
                'body' => "يُرفض الرابط إذا:\n- لم يكن من الحساب المسجَّل في المنصة.\n- لم يتضمن وسم الحملة.\n- كان منشورًا قبل تاريخ بدء الحملة.\n- حُذف المنشور أو صار خاصًّا.",
                'tags' => ['حملات', 'رفض'],
            ],
        ],
        'القسائم' => [
            [
                'title' => 'التحقق من صلاحية قسيمة',
                'kind' => KnowledgeKind::Procedure,
                'status' => KnowledgeStatus::Published,
                'summary' => 'خطوات التحقق ومدة الصلاحية.',
                'body' => "1. افتح القسائم ← التحقق.\n2. أدخل رمز القسيمة كاملًا.\n3. تظهر الحالة فورًا: سارية أو مستخدمة أو منتهية.\n\nالقسيمة تُستخدم مرة واحدة وتنتهي بنهاية الشهر ما لم يُذكر غير ذلك.",
                'tags' => ['قسائم'],
            ],
        ],
        'المشاركات' => [
            [
                'title' => 'مراحل توثيق المشاركة',
                'kind' => KnowledgeKind::Article,
                'status' => KnowledgeStatus::Draft,
                'summary' => 'مسوّدة — تحتاج مراجعة فريق المحتوى.',
                'body' => "المشاركة تمرّ بثلاث مراحل: الاستلام، ثم التحقق الآلي، ثم الاعتماد.\n\nمدة التحقق المعتادة 24 ساعة.",
                'tags' => ['مشاركات'],
            ],
        ],
    ];

    /** @var array<string, list<array{name: string, path: string, description: string, elements: list<string>, actions: list<string>, states: list<string>}>> */
    private const SCREENS = [
        'المحفظة' => [
            [
                'name' => 'المحفظة / سجل العمليات',
                'path' => '/wallet/transactions',
                'description' => 'كل حركة دخلت الرصيد أو خرجت منه، مرتبة بالأحدث.',
                'elements' => ['الرصيد الحالي', 'فلتر النوع', 'فلتر التاريخ', 'جدول الحركات'],
                'actions' => ['تصفية', 'تصدير كشف', 'فتح تفاصيل حركة'],
                'states' => ['رصيد صفر', 'لا حركات في المدى', 'تحميل', 'تعذّر جلب البيانات'],
            ],
            [
                'name' => 'المحفظة / طلب سحب',
                'path' => '/wallet/withdraw',
                'description' => 'إنشاء طلب سحب إلى الحساب البنكي الموثّق.',
                'elements' => ['المبلغ', 'الحساب البنكي', 'ملخص الرسوم'],
                'actions' => ['إرسال الطلب', 'إلغاء طلب قائم'],
                'states' => ['أقل من الحد الأدنى', 'حساب غير موثّق', 'طلب قيد المراجعة'],
            ],
        ],
        'الحملات' => [
            [
                'name' => 'الحملات / تفاصيل الحملة',
                'path' => '/campaigns/{id}',
                'description' => 'شروط الحملة ومكافآتها وحالة انضمام المستخدم.',
                'elements' => ['وصف الحملة', 'الشروط', 'المكافأة', 'المدة المتبقية'],
                'actions' => ['انضمام', 'رفع رابط مشاركة', 'انسحاب'],
                'states' => ['مؤهل', 'غير مؤهل', 'منضمّ', 'انتهت الحملة'],
            ],
        ],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->warn('KnowledgeSeeder تُتخطى في الإنتاج.');

            return;
        }

        $project = Project::query()->where('slug', 'hi-share')->first();

        if ($project === null) {
            return;
        }

        $author = User::query()->where('email', 'knowledge_manager@hiroote.test')->first()
            ?? User::query()->first();

        $sections = ProjectSection::query()->forProject($project)->get()->keyBy('name');

        foreach (self::ITEMS as $sectionName => $items) {
            $section = $sections->get($sectionName);

            if ($section === null) {
                continue;
            }

            foreach ($items as $data) {
                $this->seedItem($project, (int) $section->id, $data, $author?->id);
            }
        }

        foreach (self::SCREENS as $sectionName => $screens) {
            $section = $sections->get($sectionName);

            if ($section === null) {
                continue;
            }

            foreach ($screens as $index => $screen) {
                KnowledgeScreen::query()->updateOrCreate(
                    ['project_id' => $project->id, 'section_id' => $section->id, 'name' => $screen['name']],
                    [...$screen, 'sort_order' => $index + 1, 'updated_by' => $author?->id],
                );
            }
        }

        $this->seedSources($project, $sections->get('المحفظة')?->id, $author?->id);
        $this->seedFeedback($project, $sections);
    }

    /** @param array{title: string, kind: KnowledgeKind, status: KnowledgeStatus, summary: string, body: string, tags: list<string>} $data */
    private function seedItem(Project $project, int $sectionId, array $data, ?int $authorId): void
    {
        $item = KnowledgeItem::query()->updateOrCreate(
            ['project_id' => $project->id, 'section_id' => $sectionId, 'title' => $data['title']],
            [
                'kind' => $data['kind'],
                'status' => $data['status'],
                'summary' => $data['summary'],
                'body' => $data['body'],
                'created_by' => $authorId,
                'updated_by' => $authorId,
                'published_at' => $data['status']->isLive() ? now()->subDays(3) : null,
            ],
        );

        $tagIds = [];

        foreach ($data['tags'] as $name) {
            $tagIds[] = KnowledgeTag::query()->firstOrCreate(
                ['project_id' => $project->id, 'slug' => Slug::make($name, 'tag')],
                ['name' => $name],
            )->id;
        }

        $item->tags()->sync($tagIds);

        KnowledgeVersion::query()->firstOrCreate(
            ['knowledge_item_id' => $item->id, 'version' => 1],
            [
                'title' => $item->title,
                'summary' => $item->summary,
                'body' => $item->body,
                'status' => $item->status,
                'changed_by' => $authorId,
                'change_note' => 'الإصدار الأول',
            ],
        );
    }

    private function seedSources(Project $project, ?int $sectionId, ?int $authorId): void
    {
        if ($sectionId === null) {
            return;
        }

        foreach ([
            ['kind' => SourceKind::Link, 'label' => 'سياسة الصرف المعتمدة', 'url' => 'https://policy.hishare.test/payouts'],
            ['kind' => SourceKind::File, 'label' => 'جدول الرسوم 2026.pdf', 'file_path' => 'knowledge/fees-2026.pdf', 'mime_type' => 'application/pdf', 'file_size' => 184320],
            ['kind' => SourceKind::Note, 'label' => 'ملاحظة الفريق المالي', 'note' => 'الحد الأدنى للسحب يُراجع سنويًا في يناير.'],
        ] as $source) {
            KnowledgeSource::query()->updateOrCreate(
                ['project_id' => $project->id, 'section_id' => $sectionId, 'label' => $source['label']],
                [...$source, 'created_by' => $authorId],
            );
        }
    }

    /** @param Collection<string, ProjectSection> $sections */
    private function seedFeedback(Project $project, $sections): void
    {
        $conversation = Conversation::query()->forProject($project)->first();

        foreach ([
            ['section' => 'المحفظة', 'kind' => FeedbackKind::Unanswered, 'body' => 'كم رسوم التحويل للبنوك خارج السعودية؟', 'occurrences' => 7],
            ['section' => 'المحفظة', 'kind' => FeedbackKind::Suggestion, 'body' => 'أضف مثالًا رقميًا لحساب الرسوم — يُسأل عنه كثيرًا.', 'occurrences' => 1],
            ['section' => 'الحملات', 'kind' => FeedbackKind::Feedback, 'body' => 'الإجابة عن شروط الانضمام واضحة ومفيدة.', 'occurrences' => 3, 'resolved' => true],
            ['section' => 'المشاركات', 'kind' => FeedbackKind::Unanswered, 'body' => 'ماذا لو حُذف المنشور بعد اعتماد المشاركة؟', 'occurrences' => 4],
            ['section' => 'القسائم', 'kind' => FeedbackKind::Suggestion, 'body' => 'وضّح ما يحدث عند استخدام قسيمة منتهية.', 'occurrences' => 2],
        ] as $entry) {
            $section = $sections->get($entry['section']);

            if ($section === null) {
                continue;
            }

            KnowledgeFeedback::query()->updateOrCreate(
                ['project_id' => $project->id, 'section_id' => $section->id, 'body' => $entry['body']],
                [
                    'kind' => $entry['kind'],
                    'occurrences' => $entry['occurrences'],
                    'conversation_id' => $conversation?->id,
                    'resolved_at' => ($entry['resolved'] ?? false) ? now()->subDay() : null,
                ],
            );
        }
    }
}
