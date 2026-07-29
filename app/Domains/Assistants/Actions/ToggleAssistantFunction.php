<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Enums\AssistantFunction;
use App\Domains\Assistants\Models\AssistantFunctionSetting;
use App\Domains\Projects\Models\Project;

/**
 * تبديل وظيفة مساعد داخل مشروع — وثيقة 06 §13.
 *
 * كل تبديل تغيير حساس يُسجَّل (وثيقة 05 §7): إطفاء «التحويل البشري» يغيّر ما
 * يصل المستخدم فعلًا، ويجب أن يُعرف من فعله ومتى.
 */
final readonly class ToggleAssistantFunction
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(Project $project, AssistantFunction $function, bool $enabled): void
    {
        $setting = AssistantFunctionSetting::query()->firstOrNew([
            'project_id' => $project->id,
            'key' => $function->value,
        ]);

        $previous = $setting->exists ? $setting->is_enabled : $function->defaultEnabled();

        if ($previous === $enabled) {
            return;
        }

        $setting->forceFill([
            'is_enabled' => $enabled,
            'updated_by' => auth()->id(),
        ])->save();

        $this->audit->handle(new AuditEntry(
            action: 'assistants.function_toggle',
            auditable: $setting,
            section: 'assistants',
            oldValues: [$function->label() => $previous],
            newValues: [$function->label() => $enabled],
        ));

        // إطفاء أصلٍ يُطفئ ما يعتمد عليه: وظيفةٌ تعتمد على مطفأة تعِد بما لا يحدث.
        if ($enabled) {
            return;
        }

        foreach (AssistantFunction::cases() as $dependent) {
            if ($dependent->dependsOn() === $function) {
                $this->handle($project, $dependent, false);
            }
        }
    }
}
