<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Models\AssistantLevelSetting;

/**
 * تحرير بطاقة مستوى — وثيقة 06 §12.
 */
final readonly class UpdateAssistantLevel
{
    public function __construct(private RecordAuditEntry $audit) {}

    /** @param array<string, mixed> $attributes */
    public function handle(AssistantLevelSetting $level, array $attributes): void
    {
        $before = $level->only(array_keys($attributes));

        $level->forceFill([...$attributes, 'updated_by' => auth()->id()])->save();

        $changed = array_filter(
            $level->only(array_keys($attributes)),
            static fn (mixed $value, string $key): bool => $before[$key] != $value,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changed === []) {
            return;
        }

        $this->audit->handle(new AuditEntry(
            action: 'assistants.level_update',
            auditable: $level,
            section: 'assistants',
            oldValues: array_intersect_key($before, $changed),
            newValues: $changed,
            reason: "المستوى: {$level->label}",
        ));
    }
}
