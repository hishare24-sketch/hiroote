<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeVersion;
use App\Domains\Projects\Models\Project;

/**
 * الرجوع إلى إصدار سابق — وثيقة 06 §15.
 *
 * الرجوع إصدارٌ جديد لا حذفٌ لما بعده: تاريخ العنصر يبقى كاملًا، ويظهر أن
 * أحدهم عاد وإلى أي نسخة — وهو ما يفيد المراجعة لاحقًا.
 */
final readonly class RestoreKnowledgeVersion
{
    public function __construct(private RecordAuditEntry $audit, private SaveKnowledgeItem $save) {}

    public function handle(KnowledgeItem $item, Project $project, KnowledgeVersion $version): KnowledgeItem
    {
        $restored = $this->save->handle(
            project: $project,
            attributes: [
                'title' => $version->title,
                'summary' => $version->summary,
                'body' => $version->body,
                'status' => $item->status->value,
                'kind' => $item->kind->value,
                'section_id' => $item->section_id,
            ],
            tags: array_values($item->tags->pluck('name')->all()),
            item: $item,
            changeNote: "رجوع إلى الإصدار {$version->version}",
        );

        $this->audit->handle(new AuditEntry(
            action: 'knowledge.restore',
            auditable: $item,
            section: 'knowledge',
            oldValues: ['version' => $item->version],
            newValues: ['version' => $restored->version, 'restored_from' => $version->version],
        ));

        return $restored;
    }
}
