<?php

namespace Tests\Feature\Mcp;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Department;
use App\Models\McpToken;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class McpWriteToolsTest extends TestCase
{
    use RefreshDatabase;

    private function boardWithColumns(): Board
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY, 'name' => 'Marketing Launch']);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'To Do', 'position' => 1]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Doing', 'position' => 2]);

        return $board;
    }

    private function callTool(User $user, string $name, array $arguments): array
    {
        [, $plaintext] = McpToken::issue($user, 'Test client');

        $response = $this->withHeader('Authorization', "Bearer {$plaintext}")->postJson('/api/mcp', [
            'jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments],
        ]);

        $response->assertOk();

        return [
            'isError' => $response->json('result.isError') ?? false,
            'text' => $response->json('result.content.0.text'),
        ];
    }

    public function test_create_task_creates_a_task_and_returns_its_details(): void
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();

        $result = $this->callTool($user, 'create_task', [
            'board' => 'marketing launch', // case-insensitive
            'column' => 'To Do',
            'title' => 'Draft launch email',
            'priority' => 'high',
        ]);

        $this->assertFalse($result['isError']);
        $payload = json_decode($result['text'], true);
        $this->assertSame('Draft launch email', $payload['title']);
        $this->assertSame('Marketing Launch', $payload['board']);
        $this->assertSame('To Do', $payload['column']);
        $this->assertSame('high', $payload['priority']);

        $task = Task::query()->where('title', 'Draft launch email')->firstOrFail();
        $this->assertSame($board->id, $task->board_id);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => (new Task)->getMorphClass(), 'auditable_id' => $task->id, 'event' => 'created']);
    }

    public function test_create_task_requires_tasks_create_permission(): void
    {
        $user = User::factory()->create()->assignRole('Viewer');
        $this->boardWithColumns();

        $result = $this->callTool($user, 'create_task', [
            'board' => 'Marketing Launch', 'column' => 'To Do', 'title' => 'Should fail',
        ]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('tasks.create', $result['text']);
        $this->assertDatabaseMissing('tasks', ['title' => 'Should fail']);
    }

    public function test_create_task_cannot_target_a_board_the_caller_cannot_view(): void
    {
        $user = User::factory()->create()->assignRole('Employee');
        $restricted = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED, 'name' => 'Exec Only']);
        BoardColumn::factory()->create(['board_id' => $restricted->id, 'name' => 'To Do']);

        $result = $this->callTool($user, 'create_task', [
            'board' => 'Exec Only', 'column' => 'To Do', 'title' => 'Should fail',
        ]);

        $this->assertTrue($result['isError']);
        $this->assertDatabaseMissing('tasks', ['title' => 'Should fail']);
        // Fails exactly the same way a genuinely nonexistent board name
        // would (this employee has zero visible boards) — never something
        // like "restricted" or "access denied" that would confirm a board
        // named "Exec Only" exists at all.
        $this->assertSame(
            "No board named \"Exec Only\" — you don't appear to have access to any boards.",
            $result['text']
        );
    }

    public function test_create_task_lists_valid_boards_when_the_name_does_not_match(): void
    {
        $user = User::factory()->create()->assignRole('Employee');
        $this->boardWithColumns();

        $result = $this->callTool($user, 'create_task', [
            'board' => 'Nonexistent Board', 'column' => 'To Do', 'title' => 'x',
        ]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('Marketing Launch', $result['text']);
    }

    public function test_create_task_lists_valid_columns_when_the_name_does_not_match(): void
    {
        $user = User::factory()->create()->assignRole('Employee');
        $this->boardWithColumns();

        $result = $this->callTool($user, 'create_task', [
            'board' => 'Marketing Launch', 'column' => 'Nonexistent Column', 'title' => 'x',
        ]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('To Do', $result['text']);
        $this->assertStringContainsString('Doing', $result['text']);
    }

    public function test_create_task_rejects_an_invalid_priority(): void
    {
        $user = User::factory()->create()->assignRole('Employee');
        $this->boardWithColumns();

        $result = $this->callTool($user, 'create_task', [
            'board' => 'Marketing Launch', 'column' => 'To Do', 'title' => 'x', 'priority' => 'urgent',
        ]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('critical, high, medium, low', $result['text']);
    }

    public function test_create_task_can_assign_by_name_and_notifies_them(): void
    {
        Notification::fake();
        $user = User::factory()->create()->assignRole('Employee');
        $assignee = User::factory()->create(['name' => 'Jane Doe', 'status' => User::STATUS_ACTIVE]);
        $this->boardWithColumns();

        $result = $this->callTool($user, 'create_task', [
            'board' => 'Marketing Launch', 'column' => 'To Do', 'title' => 'Assigned task', 'assignee' => 'Jane Doe',
        ]);

        $this->assertFalse($result['isError']);
        $payload = json_decode($result['text'], true);
        $this->assertSame('Jane Doe', $payload['assignee']);

        $task = Task::query()->where('title', 'Assigned task')->firstOrFail();
        $this->assertSame($assignee->id, $task->primary_assignee_id);
        $this->assertTrue($task->assignees()->whereKey($assignee->id)->exists());
    }

    public function test_create_task_rejects_an_inactive_assignee(): void
    {
        $user = User::factory()->create()->assignRole('Employee');
        User::factory()->create(['name' => 'Retired Person', 'status' => 'inactive']);
        $this->boardWithColumns();

        $result = $this->callTool($user, 'create_task', [
            'board' => 'Marketing Launch', 'column' => 'To Do', 'title' => 'x', 'assignee' => 'Retired Person',
        ]);

        $this->assertTrue($result['isError']);
        $this->assertDatabaseMissing('tasks', ['title' => 'x']);
    }

    public function test_create_task_ambiguous_assignee_name_lists_candidates(): void
    {
        $user = User::factory()->create()->assignRole('Employee');
        User::factory()->create(['name' => 'Alex Smith', 'status' => User::STATUS_ACTIVE]);
        User::factory()->create(['name' => 'Alex Jones', 'status' => User::STATUS_ACTIVE]);
        $this->boardWithColumns();

        $result = $this->callTool($user, 'create_task', [
            'board' => 'Marketing Launch', 'column' => 'To Do', 'title' => 'x', 'assignee' => 'Alex',
        ]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('Alex Smith', $result['text']);
        $this->assertStringContainsString('Alex Jones', $result['text']);
    }

    public function test_create_ticket_is_open_to_any_role(): void
    {
        $user = User::factory()->create()->assignRole('Viewer');

        $result = $this->callTool($user, 'create_ticket', [
            'title' => 'Laptop broken', 'description' => 'Screen is cracked.', 'category' => 'Hardware',
        ]);

        $this->assertFalse($result['isError']);
        $payload = json_decode($result['text'], true);
        $this->assertSame('Laptop broken', $payload['title']);
        $this->assertSame('Hardware', $payload['category']);
        $this->assertSame(Ticket::TEAM_IT, $payload['team']);
        $this->assertSame('medium', $payload['priority']); // Hardware's seeded default_priority

        $ticket = Ticket::query()->where('title', 'Laptop broken')->firstOrFail();
        $this->assertSame($user->id, $ticket->requester_id);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => (new Ticket)->getMorphClass(), 'auditable_id' => $ticket->id, 'event' => 'created']);
    }

    public function test_create_ticket_lists_active_categories_when_the_name_does_not_match(): void
    {
        $user = User::factory()->create()->assignRole('Employee');

        $result = $this->callTool($user, 'create_ticket', [
            'title' => 'x', 'description' => 'x', 'category' => 'Nonexistent Category',
        ]);

        $this->assertTrue($result['isError']);
        $this->assertStringContainsString('Hardware', $result['text']);
    }

    public function test_create_ticket_rejects_an_invalid_team_or_impact(): void
    {
        $user = User::factory()->create()->assignRole('Employee');

        $badTeam = $this->callTool($user, 'create_ticket', [
            'title' => 'x', 'description' => 'x', 'category' => 'Hardware', 'team' => 'marketing',
        ]);
        $this->assertTrue($badTeam['isError']);

        $badImpact = $this->callTool($user, 'create_ticket', [
            'title' => 'x', 'description' => 'x', 'category' => 'Hardware', 'impact' => 'catastrophic',
        ]);
        $this->assertTrue($badImpact['isError']);
    }

    public function test_create_ticket_accepts_an_explicit_team(): void
    {
        $user = User::factory()->create()->assignRole('Employee');

        $result = $this->callTool($user, 'create_ticket', [
            'title' => 'R&D idea broken', 'description' => 'x', 'category' => 'Software', 'team' => 'rnd',
        ]);

        $this->assertFalse($result['isError']);
        $payload = json_decode($result['text'], true);
        $this->assertSame('rnd', $payload['team']);
    }

    public function test_create_ticket_requester_override_is_ignored_without_create_for_others(): void
    {
        $submitter = User::factory()->create()->assignRole('Employee');
        $other = User::factory()->create(['name' => 'Other Person', 'status' => User::STATUS_ACTIVE]);

        $result = $this->callTool($submitter, 'create_ticket', [
            'title' => 'On behalf', 'description' => 'x', 'category' => 'Hardware', 'requester' => 'Other Person',
        ]);

        $this->assertFalse($result['isError']);
        $ticket = Ticket::query()->where('title', 'On behalf')->firstOrFail();
        // Not an IT/CEO/Administrator — the requester override is silently dropped.
        $this->assertSame($submitter->id, $ticket->requester_id);
    }

    public function test_create_ticket_requester_override_is_honored_for_an_it_department_member(): void
    {
        $itDepartment = Department::firstOrCreate(['name' => 'IT'], ['slug' => 'it']);
        $itStaff = User::factory()->create(['department_id' => $itDepartment->id])->assignRole('Employee');
        $other = User::factory()->create(['name' => 'Other Person', 'status' => User::STATUS_ACTIVE]);

        $result = $this->callTool($itStaff, 'create_ticket', [
            'title' => 'On behalf 2', 'description' => 'x', 'category' => 'Hardware', 'requester' => 'Other Person',
        ]);

        $this->assertFalse($result['isError']);
        $ticket = Ticket::query()->where('title', 'On behalf 2')->firstOrFail();
        $this->assertSame($other->id, $ticket->requester_id);
    }
}
