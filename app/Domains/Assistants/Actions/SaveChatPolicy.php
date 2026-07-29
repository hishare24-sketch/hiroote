<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Enums\ChatChannelKind;
use App\Domains\Assistants\Enums\ChatScope;
use App\Domains\Assistants\Models\ProjectChatPolicy;
use App\Domains\Projects\Models\Project;
use RuntimeException;

/**
 * حفظ إذن الشات لمشروع — وثيقة 06 §13.
 *
 * توسيعُ دائرةٍ أو فتحُ قناةٍ بين بشرٍ وبشر تغييرٌ في من يصل إلى من، فيُسجَّل
 * كاملًا (وثيقة 05 §7).
 */
final readonly class SaveChatPolicy
{
    public function __construct(private RecordAuditEntry $audit) {}

    /**
     * @param  list<string>  $kinds
     * @param  list<string>  $scopes
     */
    public function handle(
        Project $project,
        bool $enabled,
        array $kinds,
        array $scopes,
        bool $assistantParticipates,
        bool $attachmentsAllowed,
        int $retentionDays,
    ): ProjectChatPolicy {
        $kinds = $this->valid($kinds, ChatChannelKind::cases());
        $scopes = $this->valid($scopes, ChatScope::cases());

        // قناةٌ بلا دائرة تفتح بابًا إلى لا مكان: تُحفظ «مفعّلة» ولا يعمل منها
        // شيء، فيُقرأ العطل عيبًا في المشروع لا نقصًا في الإذن.
        if ($enabled && ($kinds === [] || $scopes === [])) {
            throw new RuntimeException('لا يمكن تفعيل الشات بلا نوع قناة واحد ودائرة واحدة على الأقل.');
        }

        $policy = ProjectChatPolicy::query()->firstOrNew(['project_id' => $project->id]);
        $before = $policy->exists ? $this->snapshot($policy) : null;

        $policy->forceFill([
            'project_id' => $project->id,
            'is_enabled' => $enabled,
            'channel_kinds' => $kinds,
            'scopes' => $scopes,
            'assistant_participates' => $assistantParticipates,
            'attachments_allowed' => $attachmentsAllowed,
            'retention_days' => max(0, min(3650, $retentionDays)),
            'updated_by' => auth()->id(),
        ])->save();

        $this->audit->handle(new AuditEntry(
            action: 'assistants.chat_policy_save',
            auditable: $policy,
            section: 'assistants',
            oldValues: $before,
            newValues: $this->snapshot($policy),
            reason: "المشروع: {$project->name}",
        ));

        return $policy;
    }

    /**
     * @param  list<string>  $values
     * @param  list<ChatChannelKind>|list<ChatScope>  $cases
     * @return list<string>
     */
    private function valid(array $values, array $cases): array
    {
        $allowed = array_map(static fn (ChatChannelKind|ChatScope $case): string => $case->value, $cases);

        return array_values(array_unique(array_filter(
            $values,
            static fn (string $value): bool => in_array($value, $allowed, strict: true),
        )));
    }

    /** @return array<string, mixed> */
    private function snapshot(ProjectChatPolicy $policy): array
    {
        return [
            'مفعّل' => $policy->is_enabled ? 'نعم' : 'لا',
            'أنواع القنوات' => implode(' · ', $policy->channel_kinds),
            'الدوائر' => implode(' · ', $policy->scopes),
            'المساعد يشارك' => $policy->assistant_participates ? 'نعم' : 'لا',
            'المرفقات' => $policy->attachments_allowed ? 'مسموحة' : 'ممنوعة',
            'الحفظ' => $policy->keepsForever() ? 'بلا حدّ' : "{$policy->retention_days} يومًا",
        ];
    }
}
