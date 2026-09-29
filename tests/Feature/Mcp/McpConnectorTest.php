<?php

namespace Tests\Feature\Mcp;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\McpOAuthClient;
use App\Models\McpToken;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_oauth_urls_are_always_shown_and_the_client_list_starts_empty(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/admin/mcp')->assertInertia(fn ($page) => $page
            ->where('oauthUrls.authorize', url('/oauth/authorize'))
            ->where('oauthUrls.token', url('/api/oauth/token'))
            ->where('oauthClients', []));
    }

    public function test_only_the_current_users_own_oauth_clients_are_listed(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $otherAdmin = User::factory()->create()->assignRole('Administrator');
        McpOAuthClient::issue($ceo, 'My ChatGPT', 'https://chatgpt.com/connector/oauth/mine');
        McpOAuthClient::issue($otherAdmin, 'Their ChatGPT', 'https://chatgpt.com/connector/oauth/theirs');

        $this->actingAs($ceo)->get('/admin/mcp')->assertInertia(fn ($page) => $page
            ->has('oauthClients', 1)
            ->where('oauthClients.0.name', 'My ChatGPT'));
    }

    public function test_registering_an_oauth_client_flashes_the_secret_once_and_validates_the_redirect_uri(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->post('/admin/mcp/oauth-clients', ['name' => '', 'redirect_uri' => 'not-a-url'])
            ->assertSessionHasErrors(['name', 'redirect_uri']);

        $response = $this->actingAs($ceo)->post('/admin/mcp/oauth-clients', [
            'name' => 'ChatGPT',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc',
        ]);

        $response->assertSessionHas('newOAuthClient');
        $this->assertSame(1, $ceo->mcpOAuthClients()->count());
    }

    public function test_two_registrations_cannot_share_the_same_redirect_uri(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        McpOAuthClient::issue($ceo, 'ChatGPT', 'https://chatgpt.com/connector/oauth/abc');

        $this->actingAs($ceo)->post('/admin/mcp/oauth-clients', [
            'name' => 'ChatGPT again',
            'redirect_uri' => 'https://chatgpt.com/connector/oauth/abc',
        ])->assertSessionHasErrors(['redirect_uri']);
    }

    public function test_only_the_owner_can_revoke_their_oauth_client_and_doing_so_revokes_its_tokens(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [$client] = McpOAuthClient::issue($ceo, 'ChatGPT', 'https://chatgpt.com/connector/oauth/abc');
        [$token] = McpToken::issue($ceo, 'ChatGPT (OAuth)', $client->id);

        $otherCeo = User::factory()->create()->assignRole('CEO');
        $this->actingAs($otherCeo)->delete("/admin/mcp/oauth-clients/{$client->id}")->assertForbidden();

        $this->actingAs($ceo)->delete("/admin/mcp/oauth-clients/{$client->id}")->assertRedirect();
        $this->assertNull($client->fresh());
        $this->assertNull($token->fresh());
    }

    public function test_only_mcp_manage_holders_can_issue_tokens(): void
    {
        $employee = User::factory()->create()->assignRole('Employee');
        $this->actingAs($employee)->post('/admin/mcp', ['name' => 'Claude'])->assertForbidden();

        $ceo = User::factory()->create()->assignRole('CEO');
        $response = $this->actingAs($ceo)->post('/admin/mcp', ['name' => 'Claude']);

        $response->assertSessionHas('newToken');
        $this->assertSame(1, $ceo->mcpTokens()->count());
    }

    public function test_only_the_owner_can_revoke_their_token(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [$token] = McpToken::issue($ceo, 'Claude');

        $otherCeo = User::factory()->create()->assignRole('CEO');
        $this->actingAs($otherCeo)->delete("/admin/mcp/{$token->id}")->assertForbidden();
        $this->assertNotNull($token->fresh());

        $this->actingAs($ceo)->delete("/admin/mcp/{$token->id}")->assertRedirect();
        $this->assertNull($token->fresh());
    }

    public function test_the_mcp_endpoint_rejects_a_missing_or_invalid_token(): void
    {
        $this->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
            ->assertStatus(401);
    }

    public function test_initialize_and_tools_list_over_a_valid_token(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpToken::issue($ceo, 'Claude');

        $init = $this->withHeader('Authorization', "Bearer {$plaintext}")
            ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize']);
        $init->assertOk()->assertJsonPath('result.serverInfo.name', 'ewms');

        $list = $this->withHeader('Authorization', "Bearer {$plaintext}")
            ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);
        $list->assertOk();
        $names = collect($list->json('result.tools'))->pluck('name');
        $this->assertTrue($names->contains('company_overview'));
        $this->assertTrue($names->contains('payroll_summary'));
    }

    public function test_a_tool_call_returns_real_aggregate_data(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpToken::issue($ceo, 'Claude');
        Employee::factory()->count(3)->create(['employment_status' => Employee::STATUS_ACTIVE]);

        $response = $this->withHeader('Authorization', "Bearer {$plaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call',
            'params' => ['name' => 'company_overview', 'arguments' => []],
        ]);

        $response->assertOk();
        $payload = json_decode($response->json('result.content.0.text'), true);
        $this->assertSame(3, $payload['active_employees']);
        $this->assertNull($response->json('result.isError'));
    }

    public function test_a_tool_call_is_scoped_to_the_tokens_owners_own_permissions(): void
    {
        $limited = User::factory()->create();
        $limited->givePermissionTo('mcp.manage'); // no hr.payroll.view
        [, $plaintext] = McpToken::issue($limited, 'Restricted');

        $response = $this->withHeader('Authorization', "Bearer {$plaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call',
            'params' => ['name' => 'payroll_summary', 'arguments' => []],
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('result.isError'));
        $this->assertStringContainsString('hr.payroll.view', $response->json('result.content.0.text'));
    }

    public function test_payroll_summary_is_a_company_wide_total_not_a_per_employee_row(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpToken::issue($ceo, 'Claude');

        $period = PayrollPeriod::create([
            'year' => 2026, 'month' => 8, 'label' => '2026-08',
            'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'pay_date' => '2026-08-28',
            'status' => PayrollPeriod::STATUS_PAID,
        ]);
        foreach ([100000, 60000] as $gross) {
            Payslip::create([
                'payroll_period_id' => $period->id,
                'employee_id' => Employee::factory()->create()->id,
                'gross_pay' => $gross, 'paye' => $gross * 0.2, 'net_pay' => $gross * 0.75,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$plaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call',
            'params' => ['name' => 'payroll_summary', 'arguments' => []],
        ]);

        $payload = json_decode($response->json('result.content.0.text'), true);
        $this->assertSame(2, $payload['employee_count']);
        // A whole-number float round-trips through JSON as an int (160000, not
        // 160000.0) — assertEquals rather than assertSame so that's not a failure.
        $this->assertEquals(160000, $payload['total_gross_pay']);
        $this->assertArrayNotHasKey('employees', $payload);
    }

    public function test_payroll_summary_includes_a_full_deduction_and_per_department_breakdown_but_still_no_per_employee_row(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpToken::issue($ceo, 'Claude');

        // Distinct from DepartmentSeeder's own names (e.g. "Sales") — the base
        // TestCase seeds those, so a literal collision would fail on the
        // departments.name unique constraint. Three people each: the smallest
        // group payroll_summary reports on its own.
        $engineering = Department::factory()->create(['name' => 'MCP Test Engineering']);
        $sales = Department::factory()->create(['name' => 'MCP Test Sales']);
        $period = $this->paidPeriod();
        foreach (range(1, 3) as $i) {
            $this->payslip($period, $engineering, 100000);
            $this->payslip($period, $sales, 60000);
        }

        $payload = $this->callPayrollSummary($plaintext);
        $this->assertEquals(480000, $payload['total_gross_pay']);
        $this->assertEquals(3 * 22600 + 3 * 11100, $payload['paye_breakdown']['paye_after_relief']);
        $this->assertEquals(6 * 2160, $payload['statutory_deductions']['nssf_employee']);
        $this->assertArrayNotHasKey('employees', $payload);

        $byDepartment = collect($payload['by_department'])->keyBy('department');
        $this->assertEquals(300000, $byDepartment['MCP Test Engineering']['total_gross_pay']);
        $this->assertEquals(180000, $byDepartment['MCP Test Sales']['total_gross_pay']);
    }

    public function test_payroll_summary_never_reports_a_department_small_enough_to_expose_one_persons_pay(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpToken::issue($ceo, 'Claude');

        $solo = Department::factory()->create(['name' => 'MCP Test Solo']);
        $engineering = Department::factory()->create(['name' => 'MCP Test Engineering']);
        $sales = Department::factory()->create(['name' => 'MCP Test Sales']);
        $period = $this->paidPeriod();
        $this->payslip($period, $solo, 250000);
        foreach (range(1, 3) as $i) {
            $this->payslip($period, $engineering, 100000);
        }
        foreach (range(1, 4) as $i) {
            $this->payslip($period, $sales, 60000);
        }

        $rows = collect($this->callPayrollSummary($plaintext)['by_department']);

        // The one-person department is never named; and since "Other" alone
        // would still be one person (recoverable from the company total), the
        // next-smallest department is folded in with it.
        $this->assertNotContains('MCP Test Solo', $rows->pluck('department'));
        $this->assertNotContains('MCP Test Engineering', $rows->pluck('department'));
        $this->assertTrue($rows->every(fn (array $row) => $row['employee_count'] >= 3));
        $other = $rows->firstWhere('department', 'Other departments (combined for privacy)');
        $this->assertSame(4, $other['employee_count']);
        $this->assertEquals(550000, $other['total_gross_pay']);
        $this->assertEquals(240000, $rows->firstWhere('department', 'MCP Test Sales')['total_gross_pay']);
    }

    public function test_a_token_stops_working_while_its_owner_is_inactive_or_suspended(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpToken::issue($ceo, 'Claude');
        $list = fn () => $this->withHeader('Authorization', "Bearer {$plaintext}")
            ->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

        $list()->assertOk();

        foreach ([User::STATUS_SUSPENDED, User::STATUS_INACTIVE] as $status) {
            $ceo->update(['status' => $status]);
            $list()->assertStatus(401)->assertJsonPath('error.message', "Unauthorized — this token's EWMS account is no longer active.");
        }

        // Reactivating the account restores the connection — nothing was deleted.
        $ceo->update(['status' => User::STATUS_ACTIVE]);
        $list()->assertOk();
    }

    public function test_every_tool_call_is_audited_against_the_token_and_its_owner(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [$token, $plaintext] = McpToken::issue($ceo, 'Claude');

        $this->withHeader('Authorization', "Bearer {$plaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'company_overview', 'arguments' => []],
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $ceo->id,
            'auditable_type' => $token->getMorphClass(),
            'auditable_id' => $token->id,
            'event' => 'mcp_tool_called',
        ]);
        $log = AuditLog::query()->where('event', 'mcp_tool_called')->latest('id')->first();
        $this->assertSame('company_overview', $log->new_values['tool']);
        $this->assertSame('ok', $log->new_values['outcome']);

        // A refused call is recorded too, with why.
        $limited = User::factory()->create()->assignRole('Employee');
        [, $limitedPlaintext] = McpToken::issue($limited, 'Restricted');
        $this->withHeader('Authorization', "Bearer {$limitedPlaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call',
            'params' => ['name' => 'payroll_summary', 'arguments' => []],
        ]);
        $refused = AuditLog::query()->where('event', 'mcp_tool_called')->where('actor_id', $limited->id)->firstOrFail();
        $this->assertSame('error', $refused->new_values['outcome']);
        $this->assertStringContainsString('hr.payroll.view', $refused->new_values['error']);
    }

    private function paidPeriod(): PayrollPeriod
    {
        return PayrollPeriod::create([
            'year' => 2026, 'month' => 8, 'label' => '2026-08',
            'start_date' => '2026-08-01', 'end_date' => '2026-08-31', 'pay_date' => '2026-08-28',
            'status' => PayrollPeriod::STATUS_PAID,
        ]);
    }

    /** A payslip with fixed deductions, scaled only by gross — enough to check sums. */
    private function payslip(PayrollPeriod $period, Department $department, int $gross): void
    {
        $high = $gross >= 100000;
        Payslip::create([
            'payroll_period_id' => $period->id,
            'employee_id' => Employee::factory()->create(['department_id' => $department->id])->id,
            'gross_pay' => $gross, 'paye_before_relief' => $high ? 25000 : 13500, 'personal_relief' => 2400, 'insurance_relief' => 0,
            'paye' => $high ? 22600 : 11100, 'nssf_employee' => 2160, 'nssf_employer' => 2160, 'shif_employee' => 2750,
            'housing_levy_employee' => 1500, 'housing_levy_employer' => 1500, 'nita_employer' => 50,
            'net_pay' => $gross - 30000, 'employer_cost' => $gross + 3710,
        ]);
    }

    private function callPayrollSummary(string $plaintext): array
    {
        $response = $this->withHeader('Authorization', "Bearer {$plaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call',
            'params' => ['name' => 'payroll_summary', 'arguments' => []],
        ]);

        return json_decode($response->json('result.content.0.text'), true);
    }

    public function test_payroll_trend_returns_recent_periods_oldest_first_company_wide_only(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        [, $plaintext] = McpToken::issue($ceo, 'Claude');

        foreach ([['2026-06', 6, 50000], ['2026-07', 7, 55000], ['2026-08', 8, 60000]] as [$label, $month, $gross]) {
            $period = PayrollPeriod::create([
                'year' => 2026, 'month' => $month, 'label' => $label,
                'start_date' => "2026-{$month}-01", 'end_date' => "2026-{$month}-28", 'pay_date' => "2026-{$month}-28",
                'status' => PayrollPeriod::STATUS_PAID,
            ]);
            Payslip::create([
                'payroll_period_id' => $period->id,
                'employee_id' => Employee::factory()->create()->id,
                'gross_pay' => $gross, 'paye' => $gross * 0.2, 'net_pay' => $gross * 0.75,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$plaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/call',
            'params' => ['name' => 'payroll_trend', 'arguments' => ['periods' => 2]],
        ]);

        $payload = json_decode($response->json('result.content.0.text'), true);
        $this->assertCount(2, $payload);
        $this->assertSame('2026-07', $payload[0]['period']);
        $this->assertSame('2026-08', $payload[1]['period']);
        $this->assertArrayNotHasKey('employees', $payload[0]);
    }
}
