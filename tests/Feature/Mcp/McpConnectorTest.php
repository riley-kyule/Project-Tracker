<?php

namespace Tests\Feature\Mcp;

use App\Models\Employee;
use App\Models\McpToken;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class McpConnectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_oauth_section_is_hidden_until_a_client_is_configured(): void
    {
        Config::set('mcp_oauth.client_id', null);
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/admin/mcp')->assertInertia(fn ($page) => $page->where('oauth', null));
    }

    public function test_the_oauth_section_shows_copyable_setup_values_once_configured(): void
    {
        Config::set('mcp_oauth.client_id', 'ewms-abc123');
        Config::set('mcp_oauth.client_secret', 'topsecret');
        Config::set('mcp_oauth.redirect_uri', 'https://chatgpt.com/connector/oauth/xyz');
        $ceo = User::factory()->create()->assignRole('CEO');

        $this->actingAs($ceo)->get('/admin/mcp')->assertInertia(fn ($page) => $page
            ->where('oauth.clientId', 'ewms-abc123')
            ->where('oauth.clientSecret', 'topsecret')
            ->where('oauth.redirectUri', 'https://chatgpt.com/connector/oauth/xyz')
            ->where('oauth.authorizeUrl', url('/oauth/authorize'))
            ->where('oauth.tokenUrl', url('/api/oauth/token')));
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
}
