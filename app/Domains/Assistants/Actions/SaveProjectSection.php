<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Projects\Models\Project;
use App\Support\Text\Slug;

/**
 * إنشاء قسم أو تعديله — وثيقة 06 §14.
 *
 * الأقسام تُحرَّر لكل مشروع ولا تُملى عليه: «لكل مشروع احتياجاته وأدواته»
 * (ADR-0003)، فقائمةٌ ثابتة في الكود تجعل كل مشروع جديد يبدأ بأقسام غيره.
 */
final readonly class SaveProjectSection
{
    public function __construct(private RecordAuditEntry $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Project $project, array $attributes, ?ProjectSection $section = null): ProjectSection
    {
        $creating = $section === null;
        $section ??= new ProjectSection(['project_id' => $project->id]);

        $before = $creating ? [] : $section->only(array_keys($attributes));

        if ($creating && ! isset($attributes['slug'])) {
            $attributes['slug'] = $this->uniqueSlug($project, (string) $attributes['name']);
        }

        if ($creating && ! isset($attributes['sort_order'])) {
            $attributes['sort_order'] = (int) ProjectSection::query()
                ->forProject($project)->max('sort_order') + 1;
        }

        $section->forceFill([...$attributes, 'updated_by' => auth()->id()])->save();

        $after = $section->only(array_keys($attributes));

        if (! $creating && $before == $after) {
            return $section;
        }

        $this->audit->handle(new AuditEntry(
            action: $creating ? 'integrations.section_create' : 'integrations.section_update',
            auditable: $section,
            section: 'integrations',
            oldValues: $creating ? null : $before,
            newValues: $after,
            reason: "القسم: {$section->name}",
        ));

        return $section;
    }

    /**
     * معرّف فريد داخل المشروع.
     *
     * التفرّد على مستوى المشروع لا المنصة: قسم «المحفظة» قد يوجد في أكثر من
     * مشروع، ولا سبب لمنع الثاني لأن الأول سبقه.
     */
    private function uniqueSlug(Project $project, string $name): string
    {
        $base = Slug::make($name, 'section');
        $slug = $base;
        $suffix = 2;

        while (ProjectSection::query()->forProject($project)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
