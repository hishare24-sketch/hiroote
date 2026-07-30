<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Http;

use App\Domains\Analytics\Services\PulseReport;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Controllers\Controller;
use App\Support\Http\Period;
use App\Support\Http\SystemStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة نبض المشروع — ما يرسله المشروع يوميًّا عن نفسه.
 *
 * ولا تُعرض هنا نتيجةٌ بلا تغطيتها: كل رقم مصحوبٌ بعدد الأيام التي قيس فيها،
 * لأن متوسّطًا على ثلاثين يومًا وصل منها ثلاثة ليس متوسّطًا شهريًّا.
 */
class PulseController extends Controller
{
    public function __construct(private readonly CurrentProject $current) {}

    public function __invoke(Request $request): Response
    {
        $period = Period::fromRequest($request);
        $report = new PulseReport($period, $this->current->require());

        return Inertia::render('Pulse/Index', [
            'systemStatus' => SystemStatus::current(),
            'period' => $period->toArray(),
            'periodOptions' => array_map(
                fn (string $key, string $label): array => ['value' => $key, 'label' => $label],
                array_keys(Period::OPTIONS),
                array_values(Period::OPTIONS),
            ),
            'coverage' => $report->coverage(),
            'metrics' => $report->metrics(),
            'ratios' => $report->ratios(),
            'activeSeries' => $report->series('active_users'),
            'sessionSeries' => $report->series('sessions'),
            'screens' => $report->screens(),
            'sections' => $report->sections(),
            'peakHour' => $report->peakHour(),
            'snapshot' => $report->snapshot(),
        ]);
    }
}
