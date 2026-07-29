<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function entry_snapshots_actor_identity(): void
    {
        $user = User::factory()->create();

        $log = $this->actingAs($user)->app->make(RecordAuditEntry::class)->handle(
            new AuditEntry(
                action: 'providers.update',
                section: 'providers',
                oldValues: ['priority' => 1],
                newValues: ['priority' => 2],
                reason: 'إعادة ترتيب الأولوية',
            ),
        );

        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame($user->email, $log->actor_label);
        $this->assertSame($user->role->value, $log->actor_role);
        $this->assertSame(['priority' => 1], $log->old_values);
        $this->assertSame(['priority' => 2], $log->new_values);
        $this->assertNotNull($log->request_id);
    }

    #[Test]
    public function eloquent_update_is_rejected(): void
    {
        $log = AuditLog::query()->create(['action' => 'test.entry']);

        $this->expectException(LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    #[Test]
    public function eloquent_delete_is_rejected(): void
    {
        $log = AuditLog::query()->create(['action' => 'test.entry']);

        $this->expectException(LogicException::class);
        $log->delete();
    }

    #[Test]
    public function database_level_update_is_rejected_by_trigger(): void
    {
        $log = AuditLog::query()->create(['action' => 'test.entry']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']);
    }

    #[Test]
    public function database_level_delete_is_rejected_by_trigger(): void
    {
        $log = AuditLog::query()->create(['action' => 'test.entry']);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('audit_logs')->where('id', $log->id)->delete();
    }
}
