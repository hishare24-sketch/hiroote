<?php

declare(strict_types=1);

namespace App\Domains\Administration\Http;

use App\Domains\Administration\Models\AuditLog;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة سجل التشغيل والتدقيق — وثيقة التصميم §17.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'action' => $request->string('action')->trim()->value(),
            'section' => $request->string('section')->trim()->value(),
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];

        $logs = AuditLog::query()
            ->with('actor')
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
            ->when($filters['to'] !== null, fn (Builder $query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'ulid' => $log->ulid,
                'action' => $log->action,
                'section' => $log->section,
                'actor' => $log->actor_label ?? 'النظام',
                'actor_role' => $log->actor_role,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'reason' => $log->reason,
                'ip_address' => $log->ip_address,
                'request_id' => $log->request_id,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('Audit/Index', [
            'logs' => $logs,
            'filters' => $filters,
            // القوائم مبنية من البيانات الفعلية حتى لا تعرض فلاتر بلا نتائج.
            'availableActions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'availableSections' => AuditLog::query()->whereNotNull('section')->distinct()->orderBy('section')->pluck('section'),
        ]);
    }
}
