<?php

declare(strict_types=1);

namespace App\Domains\Administration\Actions;

use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Models\User;
use App\Support\Http\RequestId;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\Request;

/**
 * The single writer for `audit_logs` (وثيقة 03 §2 — كل منطق أعمال له مكان واحد).
 *
 * Actor identity is snapshotted rather than resolved by join at read time: a
 * renamed or soft-deleted operator must not rewrite what the trail says about
 * who acted.
 */
final readonly class RecordAuditEntry
{
    public function __construct(
        private AuthFactory $auth,
        private Request $request,
    ) {}

    public function handle(AuditEntry $entry): AuditLog
    {
        $actor = $this->auth->guard()->user();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_label' => $actor instanceof User ? $actor->email : null,
            'actor_role' => $actor instanceof User ? $actor->role->value : null,
            'action' => $entry->action,
            'auditable_type' => $entry->auditable?->getMorphClass(),
            'auditable_id' => $entry->auditable?->getKey() === null
                ? null
                : (string) $entry->auditable->getKey(),
            'section' => $entry->section,
            'old_values' => $entry->oldValues,
            'new_values' => $entry->newValues,
            'reason' => $entry->reason,
            'request_id' => RequestId::current(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
