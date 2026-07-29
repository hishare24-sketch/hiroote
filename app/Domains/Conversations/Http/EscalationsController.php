<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Http;

use App\Domains\Conversations\Services\EscalationReport;
use App\Http\Controllers\Controller;
use App\Support\Http\Period;
use App\Support\Http\SystemStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة التحويل والتصعيد — وثيقة 06 §10.
 */
class EscalationsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $period = Period::fromRequest($request);
        $report = new EscalationReport($period);

        return Inertia::render('Escalations/Index', [
            'systemStatus' => SystemStatus::current(),
            'period' => $period->toArray(),
            'periodOptions' => array_map(
                fn (string $key, string $label): array => ['value' => $key, 'label' => $label],
                array_keys(Period::OPTIONS),
                array_values(Period::OPTIONS),
            ),
            'totals' => $report->totals(),
            'paths' => $report->paths(),
            'journey' => $report->journey(),
            'reasons' => $report->reasons(),
            'rules' => $report->rules(),
            'openCases' => $report->openCases(),
        ]);
    }
}
