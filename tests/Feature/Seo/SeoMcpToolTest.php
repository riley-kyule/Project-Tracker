<?php

namespace Tests\Feature\Seo;

use App\Models\Department;
use App\Models\Employee;
use App\Models\McpToken;
use App\Models\SeoCardItem;
use App\Models\SeoDailyCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SEO Board Requirements Specification v1.1 §12 — MCP employee-level SEO exposure, permission-gated, no HR/payroll leakage. */
class SeoMcpToolTest extends TestCase
{
    use RefreshDatabase;

    private function seoDepartment(): Department
    {
        return Department::query()->where('slug', 'seo')->firstOrFail();
    }

    /** @return array{isError: bool, text: ?string} */
    private function callTool(User $user, string $name, array $arguments): array
    {
        [, $plaintext] = McpToken::issue($user, 'Test client');

        $response = $this->withHeader('Authorization', "Bearer {$plaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ]);

        $response->assertOk();

        return ['isError' => $response->json('result.isError') ?? false, 'text' => $response->json('result.content.0.text')];
    }

    public function test_a_hod_can_retrieve_their_own_departments_performance_but_not_another_departments(): void
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $hod = User::factory()->create()->assignRole('Department Manager');
        $marketing->update(['manager_id' => $hod->id]);

        $result = $this->callTool($hod, 'seo_department_performance', ['department' => 'SEO']);
        $this->assertFalse($result['isError']);

        $it = Department::query()->where('slug', 'it')->firstOrFail();
        $result = $this->callTool($hod, 'seo_department_performance', ['department' => 'IT']);
        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('Not authorized', $result['text']);
    }

    public function test_an_employee_without_seo_cards_view_cannot_call_the_tool(): void
    {
        $viewer = User::factory()->create()->assignRole('Viewer');

        $result = $this->callTool($viewer, 'seo_department_performance', ['department' => 'SEO']);

        $this->assertTrue($result['isError']);
    }

    public function test_employee_performance_returns_scores_but_never_hr_or_payroll_fields(): void
    {
        $ceo = User::factory()->create()->assignRole('CEO');
        $department = $this->seoDepartment();
        $employeeUser = User::factory()->create()->assignRole('Employee');
        $employee = Employee::factory()->create(['user_id' => $employeeUser->id, 'department_id' => $department->id, 'first_name' => 'Jamie', 'last_name' => 'Otieno']);

        $card = SeoDailyCard::query()->create([
            'employee_id' => $employee->id, 'department_id' => $department->id,
            'work_date' => today(), 'status' => SeoDailyCard::STATUS_OPEN, 'planned_points' => 100,
        ]);
        $card->items()->create(['section' => 'monitoring', 'name' => 'A', 'weight' => 100, 'evidence_required' => false, 'employee_status' => SeoCardItem::STATUS_NOT_STARTED, 'position' => 1]);

        $result = $this->callTool($ceo, 'seo_employee_performance', ['employee' => 'Jamie Otieno']);
        $this->assertFalse($result['isError']);

        $payload = json_decode($result['text'], true);
        $this->assertSame('Jamie Otieno', $payload['employee_name']);
        $this->assertArrayHasKey('cards', $payload);
        foreach (['salary', 'gross_pay', 'net_pay', 'bank_account_number', 'national_id_number', 'kra_pin'] as $forbiddenField) {
            $this->assertArrayNotHasKey($forbiddenField, $payload);
        }
    }
}
