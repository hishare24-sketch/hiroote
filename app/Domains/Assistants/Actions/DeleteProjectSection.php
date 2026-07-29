<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Models\ProjectSection;

/**
 * حذف قسم من مصفوفة المشروع.
 *
 * المحادثات تحفظ اسم القسم نصًّا لا مفتاحًا خارجيًا، فحذف القسم من الإعداد لا
 * يمحو تاريخه: تقارير الفترة السابقة تبقى كما كانت.
 */
final readonly class DeleteProjectSection
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(ProjectSection $section): void
    {
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
}
