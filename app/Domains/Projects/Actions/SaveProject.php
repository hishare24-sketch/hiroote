<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Actions\ProvisionAssistantDefaults;
use App\Domains\Projects\Models\Project;
use App\Support\Text\Slug;

/**
 * إنشاء مشروع أو تعديله — ADR-0003.
 *
 * المشروع الجديد يُجهَّز بسلوك مساعد كامل فور إنشائه: مشروعٌ يُنشأ ثم تُفتح
 * شاشاته فارغة يبدو معطوبًا، والافتراضات نقطة انطلاق تُحرَّر لا قالب ثابت.
 */
final readonly class SaveProject
{
    public function __construct(
        private RecordAuditEntry $audit,
        private ProvisionAssistantDefaults $provision,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(array $attributes, ?Project $project = null): Project
    {
        $creating = $project === null;
        $project ??= new Project;

        if (array_key_exists('api_base_url', $attributes) && $attributes['api_base_url'] === '') {
            $attributes['api_base_url'] = null;
        }

        $before = $creating ? [] : $project->only(array_keys($attributes));

        if ($creating) {
            $attributes['slug'] ??= $this->uniqueSlug((string) $attributes['name']);
            $attributes['sort_order'] ??= (int) Project::query()->max('sort_order') + 1;
        }

        $project->forceFill($attributes)->save();

        if ($creating) {
            $this->provision->handle($project);
        }

        $after = $project->only(array_keys($attributes));

        if (! $creating && $before == $after) {
            return $project;
        }

        $this->audit->handle(new AuditEntry(
            action: $creating ? 'project.create' : 'project.update',
            auditable: $project,
            section: 'project',
            oldValues: $creating ? null : $before,
            newValues: $after,
            reason: "المشروع: {$project->name}",
        ));

        return $project;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Slug::make($name, 'project');
        $slug = $base;
        $suffix = 2;

        while (Project::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
