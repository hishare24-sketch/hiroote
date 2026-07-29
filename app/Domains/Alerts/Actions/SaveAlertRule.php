<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Alerts\Enums\AlertChannel;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Projects\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء قاعدة تنبيه أو تعديلها مع مستلميها — وثيقة 06 §11.
 */
final readonly class SaveAlertRule
{
    public function __construct(private RecordAuditEntry $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{user_id?: int|null, email?: string|null, channel: string}>  $recipients
     */
    public function handle(
        Project $project,
        array $attributes,
        array $recipients,
        ?AlertRule $rule = null,
    ): AlertRule {
        return DB::transaction(function () use ($project, $attributes, $recipients, $rule): AlertRule {
            $metric = AlertMetric::from((string) $attributes['metric']);

            // المؤشر اللحظي لا نافذة له، وغير المقيَّد بأقسام لا يحفظ نطاق أقسام:
            // حقلٌ محفوظ لا يقرأه أحد يوهم لاحقًا بضبطٍ لم يحدث.
            $normalised = [
                ...$attributes,
                'window_minutes' => $metric->isWindowed() ? (int) $attributes['window_minutes'] : 0,
                'section_ids' => $metric->supportsSectionScope()
                    ? $this->ids($attributes['section_ids'] ?? [])
                    : [],
                'provider_ids' => $this->ids($attributes['provider_ids'] ?? []),
            ];

            $before = $rule?->only(['name', 'metric', 'comparison', 'threshold', 'is_enabled']);

            if ($rule === null) {
                $rule = AlertRule::query()->create([
                    ...$normalised,
                    'project_id' => $project->id,
                    'created_by' => auth()->id(),
                ]);
            } else {
                $rule->fill($normalised)->save();
            }

            $this->syncRecipients($rule, $recipients);

            $this->audit->handle(new AuditEntry(
                action: $before === null ? 'alerts.create' : 'alerts.update',
                auditable: $rule,
                section: 'alerts',
                oldValues: $before,
                newValues: [
                    'الاسم' => $rule->name,
                    'الشرط' => "{$rule->metric->label()} {$rule->comparison->label()} {$rule->threshold}",
                    'الحالة' => $rule->is_enabled ? 'مفعّلة' : 'موقوفة',
                ],
            ));

            return $rule->fresh(['recipients.user']) ?? $rule;
        });
    }

    /**
     * @param  list<array{user_id?: int|null, email?: string|null, channel: string}>  $recipients
     */
    private function syncRecipients(AlertRule $rule, array $recipients): void
    {
        $rule->recipients()->delete();

        foreach ($recipients as $recipient) {
            $userId = $recipient['user_id'] ?? null;
            $email = $recipient['email'] ?? null;

            if ($userId === null && ($email === null || $email === '')) {
                continue;
            }

            $rule->recipients()->create([
                'user_id' => $userId,
                'email' => $userId === null ? $email : null,
                'channel' => AlertChannel::from($recipient['channel']),
            ]);
        }
    }

    /**
     * @return list<int>
     */
    private function ids(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $ids[] = (int) $value;
            }
        }

        return array_values(array_unique($ids));
    }
}
