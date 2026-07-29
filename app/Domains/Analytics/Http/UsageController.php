<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Http;

use App\Domains\Analytics\Services\UsageReport;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Controllers\Controller;
use App\Support\Http\Period;
use App\Support\Http\SystemStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة الاستهلاك والتكلفة — وثيقة 06 §7.
 */
class UsageController extends Controller
{
    public function __construct(private readonly CurrentProject $current) {}

    public function __invoke(Request $request): Response
    {
        $period = Period::fromRequest($request);
        $report = new UsageReport($period, $this->current->require());

        return Inertia::render('Usage/Index', [
            'systemStatus' => SystemStatus::current(),
            'period' => $period->toArray(),
            'periodOptions' => array_map(
                fn (string $key, string $label): array => ['value' => $key, 'label' => $label],
                array_keys(Period::OPTIONS),
                array_values(Period::OPTIONS),
            ),
            'totals' => $report->totals(),
            'tokenBreakdown' => $report->tokenBreakdown(),
            'series' => $report->series(),
            'comparison' => $report->comparison(),
            'byProvider' => $report->byProvider(),
            'bySection' => $report->bySection(),
            'byModel' => $report->byModel(),
            'averages' => $report->averages(),
            'costlyOperations' => $report->costlyOperations(),
            'budget' => $report->budget(),
        ]);
    }
}
