<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Carbon;

/**
 * إصدار مفتاح وصول لمشروع، وإبطاله.
 *
 * النص الصريح يُعاد من هنا مرة واحدة ولا يُحفظ في أي مكان — لا في الجدول ولا
 * في سجل التدقيق. السجل يحفظ اسم المفتاح وبادئته فقط: سجلٌّ يحفظ المفتاح يجعل
 * قراءة سجل التدقيق كافيةً لانتحال المشروع.
 */
final readonly class IssueProjectApiKey
{
    public function __construct(private RecordAuditEntry $audit) {}

    /**
     * @return array{key: ProjectApiKey, token: string}
     */
    public function issue(Project $project, string $name, ?Carbon $expiresAt = null): array
    {
        $minted = ProjectApiKey::mint();

        $key = ProjectApiKey::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'prefix' => $minted['prefix'],
            'hash' => $minted['hash'],
            'expires_at' => $expiresAt,
            'created_by' => auth()->id(),
        ]);

        $this->audit->handle(new AuditEntry(
            action: 'integrations.api_key_issue',
            auditable: $key,
            section: 'integrations',
            newValues: ['الاسم' => $name, 'البادئة' => $minted['prefix']],
            reason: "المشروع: {$project->name}",
        ));

        return ['key' => $key, 'token' => $minted['token']];
    }

    public function revoke(ProjectApiKey $key): void
    {
        if ($key->revoked_at !== null) {
            return;
        }

        $key->forceFill(['revoked_at' => now()])->save();

        $this->audit->handle(new AuditEntry(
            action: 'integrations.api_key_revoke',
            auditable: $key,
            section: 'integrations',
            oldValues: ['الحالة' => 'فعّال'],
            newValues: ['الحالة' => 'مُبطَل', 'البادئة' => $key->prefix],
        ));
    }
}
