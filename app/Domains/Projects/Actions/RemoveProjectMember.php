<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Administration\Models\User;
use App\Domains\Projects\Models\Project;
use RuntimeException;

/**
 * سحب عضوية من مشروع — ADR-0003 §3.
 */
final readonly class RemoveProjectMember
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(Project $project, User $user): void
    {
        $role = $project->membershipRoleFor($user);

        if ($role === null) {
            return;
        }

        // مشروعٌ بلا مدير نظام لا يستطيع أحدٌ إعادة منح العضوية فيه إلا من قاعدة
        // البيانات — يُمنع سحب آخر مدير بدل أن يُكتشف الإغلاق بعد وقوعه.
        if ($this->isLastAdministrator($project, $user)) {
            throw new RuntimeException('لا يمكن سحب آخر مدير نظام من المشروع.');
        }

        $project->members()->detach($user->id);

        $this->audit->handle(new AuditEntry(
            action: 'project.member_remove',
            auditable: $project,
            section: 'project',
            oldValues: [$user->email => $role->label()],
            reason: "المشروع: {$project->name}",
        ));
    }

    private function isLastAdministrator(Project $project, User $user): bool
    {
        if ($project->membershipRoleFor($user)?->value !== 'system_admin') {
            return false;
        }

        return $project->members()
            ->wherePivot('role', 'system_admin')
            ->where('users.id', '!=', $user->id)
            ->doesntExist();
    }
}
