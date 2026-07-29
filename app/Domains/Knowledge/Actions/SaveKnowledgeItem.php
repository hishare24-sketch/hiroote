<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeTag;
use App\Domains\Knowledge\Models\KnowledgeVersion;
use App\Domains\Projects\Models\Project;
use App\Support\Text\Slug;

/**
 * إنشاء عنصر معرفة أو تعديله — وثيقة 06 §15.
 *
 * كل حفظ يترك لقطة في `knowledge_versions` **بعد** الكتابة لا قبلها.
 *
 * اللقطة قبل الكتابة تترك الإصدار الحالي بلا صفّ، فلا يظهر في سجل الإصدارات
 * ولا يمكن مقارنته ولا وسمه «الحالي». وبالأخذ بعد الكتابة يحمل كل رقم إصدار
 * صفًّا يطابق محتواه، ويبقى ما قبله محفوظًا في صفّه هو.
 */
final readonly class SaveKnowledgeItem
{
    public function __construct(private RecordAuditEntry $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $tags
     */
    public function handle(
        Project $project,
        array $attributes,
        array $tags = [],
        ?KnowledgeItem $item = null,
        ?string $changeNote = null,
    ): KnowledgeItem {
        $creating = $item === null;
        $before = $creating ? [] : $item->only(['title', 'status', 'kind', 'section_id']);

        $item ??= new KnowledgeItem(['project_id' => $project->id, 'created_by' => auth()->id()]);

        $status = KnowledgeStatus::from((string) $attributes['status']);

        $item->forceFill([
            ...$attributes,
            'updated_by' => auth()->id(),
            'version' => $creating ? 1 : $item->version + 1,
            // تاريخ النشر يُثبَّت عند أول نشر ولا يتغير بتعديل لاحق.
            'published_at' => $status->isLive() ? ($item->published_at ?? now()) : null,
        ])->save();

        $item->tags()->sync($this->resolveTags($project, $tags));

        $this->snapshot($item, $changeNote ?? ($creating ? 'الإصدار الأول' : null));

        $this->audit->handle(new AuditEntry(
            action: $creating ? 'knowledge.create' : 'knowledge.update',
            auditable: $item,
            section: 'knowledge',
            oldValues: $creating ? null : $before,
            newValues: $item->only(['title', 'status', 'kind', 'section_id']),
            reason: $changeNote,
        ));

        return $item;
    }

    /**
     * @param  list<string>  $names
     * @return list<int>
     */
    private function resolveTags(Project $project, array $names): array
    {
        $ids = [];

        foreach (array_filter(array_map('trim', $names)) as $name) {
            $ids[] = KnowledgeTag::query()->firstOrCreate(
                ['project_id' => $project->id, 'slug' => Slug::make($name, 'tag')],
                ['name' => $name],
            )->id;
        }

        return $ids;
    }

    private function snapshot(KnowledgeItem $item, ?string $note): void
    {
        KnowledgeVersion::query()->firstOrCreate(
            ['knowledge_item_id' => $item->id, 'version' => $item->version],
            [
                'title' => $item->title,
                'summary' => $item->summary,
                'body' => $item->body,
                'status' => $item->status,
                'changed_by' => auth()->id(),
                'change_note' => $note,
            ],
        );
    }
}
