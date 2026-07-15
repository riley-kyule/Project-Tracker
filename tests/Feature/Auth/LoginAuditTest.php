<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_audited()
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_succeeded',
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
        ]);
    }

    public function test_bad_password_on_a_known_account_is_audited_against_that_user()
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_failed',
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
        ]);
    }

    public function test_login_attempt_on_an_unknown_email_is_audited_with_no_subject()
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever']);

        $log = AuditLog::query()->where('event', 'login_failed')->firstOrFail();

        $this->assertNull($log->auditable_type);
        $this->assertNull($log->auditable_id);
        $this->assertSame('nobody@example.com', $log->new_values['email']);
    }

    public function test_inactive_account_login_is_audited_and_blocked()
    {
        $user = User::factory()->create(['status' => User::STATUS_INACTIVE]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login_blocked_inactive',
            'auditable_type' => $user->getMorphClass(),
            'auditable_id' => $user->id,
        ]);
    }
}
