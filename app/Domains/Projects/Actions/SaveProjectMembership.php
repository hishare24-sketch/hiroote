<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Projects\Models\Project;

/**
 * إضافة عضو إلى مشروع أو تغيير دوره — ADR-0003 §3.
 *
 * العضوية هي الصلاحية: كل تغيير هنا يوسّع أو يضيّق ما يراه شخصٌ فعلًا، فيُسجَّل
 * كاملًا (وثيقة 05 §7).
 */
final readonly class SaveProjectMembership
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(Project $project, User $user, Role $role): void
    {
        $previous = $project->membershipRoleFor($user);

        if ($previous === $role) {
            return;
        }

        $project->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);

        $this->audit->handle(new AuditEntry(
            action: $previous === null ? 'project.member_add' : 'project.member_role_change',
            auditable: $project,
            section: 'project',
            oldValues: $previous === null ? null : [$user->email => $previous->label()],
            newValues: [$user->email => $role->label()],
            reason: "المشروع: {$project->name}",
        ));
    }
}
