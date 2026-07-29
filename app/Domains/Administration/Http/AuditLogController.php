<?php

declare(strict_types=1);

namespace App\Domains\Administration\Http;

use App\Domains\Administration\Enums\AuditCategory;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Controllers\Controller;
use App\Support\Http\SystemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة سجل التشغيل والتدقيق — وثيقة التصميم §17.
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly CurrentProject $current) {}

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'action' => $request->string('action')->trim()->value(),
            'section' => $request->string('section')->trim()->value(),
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];

        $logs = $this->baseQuery($filters)
            ->with('actor')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (AuditLog $log): array {
                $category = AuditCategory::fromAction($log->action);

                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'section' => $log->section,
                    'category' => $category->label(),
                    'category_tone' => $category->tone(),
                    'actor' => $log->actor_label ?? 'النظام',
                    'actor_role' => $log->actor_role,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'reason' => $log->reason,
                    'ip_address' => $log->ip_address,
                    'request_id' => $log->request_id,
                    'created_at' => $log->created_at->toIso8601String(),
                ];
            });

        return Inertia::render('Audit/Index', [
            'systemStatus' => SystemStatus::current(),
            'logs' => $logs,
            'filters' => $filters,
            'stats' => $this->stats(),
            'availableActions' => $this->baseQuery($filters)->distinct()->orderBy('action')->pluck('action'),
            'availableSections' => $this->baseQuery($filters)
                ->whereNotNull('section')
                ->distinct()
                ->orderBy('section')
                ->pluck('section'),
        ]);
    }

    /**
     * @param  array{search: string, action: string, section: string, from: ?string, to: ?string}  $filters
     * @return Builder<AuditLog>
     */
    private function baseQuery(array $filters): Builder
    {
        return AuditLog::query()
            ->visibleInProject($this->current->require())
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('action', 'ilike', $term)
                        ->orWhere('actor_label', 'ilike', $term)
                        ->orWhere('reason', 'ilike', $term);
                });
            })
            ->when($filters['action'] !== '', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['section'] !== '', fn (Builder $query) => $query->where('section', $filters['section']))
            ->when($filters['from'] !== null, fn (Builder $query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']));
    }

    /**
     * البطاقات الإحصائية الأربع — وثيقة التصميم §17.
     *
     * @return array{today: int, settingsChanges: int, failovers: int, failures: int}
     */
    private function stats(): array
    {
        return [
            'today' => $this->scoped()->whereDate('created_at', today())->count(),
            'settingsChanges' => $this->scoped()->where('action', 'like', 'settings.%')->count(),
            'failovers' => $this->scoped()->where('action', 'like', '%failover%')->count(),
            'failures' => $this->scoped()
                ->where(fn (Builder $query) => $query
                    ->where('action', 'like', '%failed%')
                    ->orWhere('action', 'like', '%error%'))
                ->count(),
        ];
    }

    /** @return Builder<AuditLog> */
    private function scoped(): Builder
    {
        return AuditLog::query()->visibleInProject($this->current->require());
    }
}
