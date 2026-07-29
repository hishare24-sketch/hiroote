<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use RuntimeException;

/**
 * حذف قسم من مصفوفة المشروع.
 *
 * المحادثات تحفظ اسم القسم نصًّا لا مفتاحًا خارجيًا، فحذف القسم من الإعداد لا
 * يمحو تاريخه: تقارير الفترة السابقة تبقى كما كانت.
 *
 * أما ما يُعلَّق على القسم فيمنع حذفه حتى يُفرَّغ. قبل هذا الشرط كان الحذف يمحو
 * شاشات القسم كلها بأوصافها — ساعاتِ توثيق — ويترك عناصر معرفته بلا قسم فتبقى
 * في الجدول ولا تظهر في أي شاشة: بقاءٌ في الصف يعادل الفقد في الأثر. والمنع
 * يُبقي القرار عند المشغّل بدل أن يتخذه الجدول نيابةً عنه بصمت.
 */
final readonly class DeleteProjectSection
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(ProjectSection $section): void
    {
        $this->guardAttachedContent($section);

        $snapshot = $section->only(['name', 'slug', 'sort_order']);

        $this->audit->handle(new AuditEntry(
            action: 'integrations.section_delete',
            auditable: $section,
            section: 'integrations',
            oldValues: $snapshot,
            reason: "القسم: {$section->name}",
        ));

        $section->delete();
    }

    private function guardAttachedContent(ProjectSection $section): void
    {
        $screens = KnowledgeScreen::query()->where('section_id', $section->id)->count();
        $items = KnowledgeItem::query()->where('section_id', $section->id)->count();

        if ($screens === 0 && $items === 0) {
            return;
        }

        $parts = [];

        if ($screens > 0) {
            $parts[] = "{$screens} شاشة";
        }

        if ($items > 0) {
            $parts[] = "{$items} عنصر معرفة";
        }

        throw new RuntimeException(
            'لا يمكن حذف القسم وفيه '.implode(' و', $parts).
            '. انقلها إلى قسم آخر أو احذفها أولًا.',
        );
    }
}
