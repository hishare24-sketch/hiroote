<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * إنشاء شاشة قسم أو تعديلها مع صورتها — وثيقة 06 §15.
 *
 * الصورة توثيقٌ للمشغّل يكتب أمامها الوصف؛ ما يصل المساعد هو النص. صورةٌ بلا
 * وصف تعني قسمًا يبدو موصوفًا وهو ليس كذلك، ولذلك يقيس تقرير التغطية الوصف
 * لا الصورة.
 */
final readonly class SaveKnowledgeScreen
{
    private const DISK = 'public';

    public function __construct(private RecordAuditEntry $audit) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Project $project,
        ProjectSection $section,
        array $attributes,
        ?KnowledgeScreen $screen = null,
        ?UploadedFile $image = null,
        bool $removeImage = false,
    ): KnowledgeScreen {
        $creating = $screen === null;

        $screen ??= new KnowledgeScreen([
            'project_id' => $project->id,
            'section_id' => $section->id,
        ]);

        $before = $creating ? null : $screen->only(['name', 'key', 'path', 'description']);
        $previousImage = $screen->image_path;

        if ($creating && ! isset($attributes['sort_order'])) {
            $attributes['sort_order'] = (int) KnowledgeScreen::query()
                ->forProject($project)
                ->where('section_id', $section->id)
                ->max('sort_order') + 1;
        }

        if ($image !== null) {
            $attributes = [
                ...$attributes,
                'image_path' => $image->store("screens/{$project->id}", self::DISK),
                'image_mime' => $image->getClientMimeType(),
                'image_size' => $image->getSize(),
            ];
        } elseif ($removeImage) {
            $attributes = [
                ...$attributes,
                'image_path' => null,
                'image_mime' => null,
                'image_size' => null,
            ];
        }

        $screen->forceFill([...$attributes, 'updated_by' => auth()->id()])->save();

        // الملف القديم يُحذف بعد نجاح الحفظ لا قبله: لو أخفق الحفظ لبقي السجل
        // يشير إلى ملف محذوف.
        if ($previousImage !== null && $previousImage !== $screen->image_path) {
            Storage::disk(self::DISK)->delete($previousImage);
        }

        $this->audit->handle(new AuditEntry(
            action: $creating ? 'knowledge.screen_create' : 'knowledge.screen_update',
            auditable: $screen,
            section: 'knowledge',
            oldValues: $before,
            newValues: [
                'الشاشة' => $screen->name,
                'المفتاح' => $screen->key ?? 'بلا مفتاح',
                'صورة' => $screen->image_path === null ? 'لا' : 'نعم',
            ],
            reason: "القسم: {$section->name}",
        ));

        return $screen;
    }

    public function delete(KnowledgeScreen $screen): void
    {
        $image = $screen->image_path;

        $this->audit->handle(new AuditEntry(
            action: 'knowledge.screen_delete',
            auditable: $screen,
            section: 'knowledge',
            oldValues: ['الشاشة' => $screen->name],
        ));

        $screen->delete();

        if ($image !== null) {
            Storage::disk(self::DISK)->delete($image);
        }
    }
}
