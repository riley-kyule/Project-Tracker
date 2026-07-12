<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditLogImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_entries_cannot_be_updated_or_deleted(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::create([
            'actor_id' => $user->id,
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'event' => 'created',
            'created_at' => now(),
        ]);

        // DB::transaction() opens a SAVEPOINT here (RefreshDatabase already
        // has the test in an outer transaction). On PostgreSQL, a trigger
        // exception aborts the whole surrounding transaction, not just the
        // statement, so without a savepoint every later query would fail
        // with "current transaction is aborted" instead of exercising the
        // trigger we're actually testing.
        try {
            DB::transaction(fn () => AuditLog::query()->whereKey($log->id)->update(['event' => 'tampered']));
            $this->fail('The database allowed an audit entry to be updated.');
        } catch (QueryException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'event' => 'created']);
        }

        try {
            DB::transaction(fn () => AuditLog::query()->whereKey($log->id)->delete());
            $this->fail('The database allowed an audit entry to be deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
        }
    }
}
