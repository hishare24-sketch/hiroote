<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Enums\SectionCapability;
use App\Domains\Assistants\Models\ProjectSection;

/**
 * تبديل خلية في مصفوفة التكامل — وثيقة 06 §14.
 */
final readonly class ToggleSectionCapability
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(ProjectSection $section, SectionCapability $capability, bool $enabled): void
    {
        $previous = (bool) $section->getAttribute($capability->value);

        if ($previous === $enabled) {
            return;
        }

        $section->forceFill([
            $capability->value => $enabled,
            'updated_by' => auth()->id(),
        ])->save();

        $this->audit->handle(new AuditEntry(
            action: 'integrations.capability_toggle',
            auditable: $section,
            section: 'integrations',
            oldValues: [$capability->label() => $previous],
            newValues: [$capability->label() => $enabled],
            reason: "القسم: {$section->name}",
        ));

        if ($enabled) {
            return;
        }

        // قدرةٌ تعتمد على مطفأة تبقى معلّمة مفعّلة وهي لا تعمل — تُطفأ معها.
        foreach (SectionCapability::cases() as $dependent) {
            if ($dependent->dependsOn() === $capability) {
                $this->handle($section, $dependent, false);
            }
        }
    }
}
