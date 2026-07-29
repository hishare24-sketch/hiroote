<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Services;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Knowledge\Models\KnowledgeSource;
use App\Domains\Projects\Models\Project;

/**
 * حالة معرفة كل قسم ونسبة اكتمالها — وثيقة 06 §15.
 */
final readonly class SectionKnowledgeReport
{
    /**
     * أربعة شروط متساوية الوزن تصف قسمًا «مكتمل المعرفة».
     *
     * النسبة تقيس التغطية لا الكمية: قسمٌ بخمسين عنصرًا كلها مسودات ليس أقرب
     * إلى الاكتمال من قسمٍ بعنصر واحد منشور. ولذلك كل شرط يُحتسب مرة واحدة.
     *
     * @var array<string, string>
     */
    public const CRITERIA = [
        'published' => 'عنصر معرفة منشور واحد على الأقل',
        'screens' => 'شاشة موصوفة واحدة على الأقل',
        'sources' => 'مصدر واحد على الأقل',
        'no_open_notes' => 'لا ملاحظات مفتوحة',
    ];

    /**
     * @return array<int, array{
     *     items: int, published: int, screens: int, sources: int, open_notes: int,
     *     completion: int, met: array<string, bool>, status: string, status_label: string, tone: string
     * }>
     */
    public function forProject(Project $project): array
    {
        $items = KnowledgeItem::query()->forProject($project)
            ->selectRaw('section_id, count(*) as total')
            ->selectRaw('count(*) filter (where status = ?) as published', [KnowledgeStatus::Published->value])
            ->groupBy('section_id')->toBase()->get()->keyBy('section_id');

        $screens = KnowledgeScreen::query()->forProject($project)
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')->toBase()->pluck('total', 'section_id');

        $sources = KnowledgeSource::query()->forProject($project)
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')->toBase()->pluck('total', 'section_id');

        $notes = KnowledgeFeedback::query()->forProject($project)->open()
            ->selectRaw('section_id, count(*) as total')
            ->groupBy('section_id')->toBase()->pluck('total', 'section_id');

        $report = [];

        foreach (ProjectSection::query()->forProject($project)->pluck('id') as $sectionId) {
            $row = $items->get($sectionId);

            $report[(int) $sectionId] = $this->summarise(
                total: (int) ($row->total ?? 0),
                published: (int) ($row->published ?? 0),
                screens: (int) ($screens[$sectionId] ?? 0),
                sources: (int) ($sources[$sectionId] ?? 0),
                openNotes: (int) ($notes[$sectionId] ?? 0),
            );
        }

        return $report;
    }

    /**
     * @return array{
     *     items: int, published: int, screens: int, sources: int, open_notes: int,
     *     completion: int, met: array<string, bool>, status: string, status_label: string, tone: string
     * }
     */
    private function summarise(
        int $total,
        int $published,
        int $screens,
        int $sources,
        int $openNotes,
    ): array {
        $met = [
            'published' => $published > 0,
            'screens' => $screens > 0,
            'sources' => $sources > 0,
            'no_open_notes' => $openNotes === 0,
        ];

        $completion = (int) round(count(array_filter($met)) / count($met) * 100);

        // القسم الفارغ تمامًا ليس «مكتملًا بـ 25%» لأنه بلا ملاحظات مفتوحة —
        // غياب الشكوى في قسمٍ لا معرفة فيه ليس إنجازًا.
        if ($total === 0 && $screens === 0 && $sources === 0) {
            $completion = 0;
        }

        [$status, $label, $tone] = match (true) {
            $completion === 0 => ['empty', 'لم تبدأ', 'danger'],
            $completion < 50 => ['partial', 'ناقصة', 'warning'],
            $completion < 100 => ['good', 'جيدة', 'info'],
            default => ['complete', 'مكتملة', 'success'],
        };

        return [
            'items' => $total,
            'published' => $published,
            'screens' => $screens,
            'sources' => $sources,
            'open_notes' => $openNotes,
            'completion' => $completion,
            'met' => $met,
            'status' => $status,
            'status_label' => $label,
            'tone' => $tone,
        ];
    }
}
