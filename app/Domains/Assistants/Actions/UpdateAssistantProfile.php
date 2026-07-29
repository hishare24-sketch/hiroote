<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Models\AssistantProfile;
use App\Domains\Projects\Models\Project;

/**
 * إعداد تحكم المستخدم بالمستوى — وثيقة 06 §12.
 */
final readonly class UpdateAssistantProfile
{
    public function __construct(private RecordAuditEntry $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(Project $project, array $attributes): AssistantProfile
    {
        $profile = AssistantProfile::forProject($project);
        $before = $profile->only(array_keys($attributes));

        $profile->forceFill([...$attributes, 'updated_by' => auth()->id()])->save();

        $after = $profile->only(array_keys($attributes));

        if ($before == $after) {
            return $profile;
        }

        $this->audit->handle(new AuditEntry(
            action: 'assistants.profile_update',
            auditable: $profile,
            section: 'assistants',
            oldValues: $before,
            newValues: $after,
        ));

        return $profile;
    }
}
