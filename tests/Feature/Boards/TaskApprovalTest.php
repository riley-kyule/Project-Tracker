<?php

namespace Tests\Feature\Boards;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskApprovalDecided;
use App\Notifications\TaskApprovalRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function boardWithColumns(): Board
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'In Progress', 'slug' => 'in-progress', 'position' => 1, 'semantic_status' => 'active']);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Review', 'slug' => 'review', 'position' => 2, 'semantic_status' => 'review']);
        BoardColumn::factory()->create(['board_id' => $board->id, 'name' => 'Done', 'slug' => 'done', 'position' => 3, 'semantic_status' => 'completed', 'is_completion_column' => true]);

        return $board;
    }

    public function test_requesting_approval_notifies_reviewer()
    {
        Notification::fake();

        $author = User::factory()->create()->assignRole('Employee');
        $reviewer = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $board->columns()->first()->id, 'created_by' => $author->id]);

        $this->actingAs($author)
            ->post("/tasks/{$task->id}/request-approval", ['reviewer_id' => $reviewer->id])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('pending', $task->approval_status);
        $this->assertSame($reviewer->id, $task->approver_id);

        Notification::assertSentTo($reviewer, TaskApprovalRequested::class);
    }

    public function test_pending_approval_blocks_completion_move()
    {
        $author = User::factory()->create()->assignRole('Employee');
        $reviewer = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        [$inProgress, , $done] = $board->columns()->get()->all();

        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $inProgress->id, 'created_by' => $author->id]);
        $this->actingAs($author)->post("/tasks/{$task->id}/request-approval", ['reviewer_id' => $reviewer->id]);

        $this->actingAs($author)
            ->from('/boards/'.$board->id)
            ->post("/tasks/{$task->id}/move", ['board_column_id' => $done->id, 'position' => 1])
            ->assertSessionHasErrors('approval');

        $this->assertSame($inProgress->id, $task->refresh()->board_column_id);
    }

    public function test_only_the_assigned_reviewer_or_a_manager_can_decide()
    {
        $author = User::factory()->create()->assignRole('Employee');
        $reviewer = User::factory()->create()->assignRole('Employee');
        $bystander = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $board->columns()->first()->id, 'created_by' => $author->id]);
        $this->actingAs($author)->post("/tasks/{$task->id}/request-approval", ['reviewer_id' => $reviewer->id]);

        $this->actingAs($bystander)->post("/tasks/{$task->id}/approve-review")->assertForbidden();
        $this->actingAs($reviewer)->post("/tasks/{$task->id}/approve-review")->assertRedirect();

        $this->assertSame('approved', $task->refresh()->approval_status);
        $this->assertNotNull($task->approved_at);
    }

    public function test_approved_task_can_move_into_completion_column()
    {
        $author = User::factory()->create()->assignRole('Employee');
        $reviewer = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        [$inProgress, , $done] = $board->columns()->get()->all();
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $inProgress->id, 'created_by' => $author->id]);

        $this->actingAs($author)->post("/tasks/{$task->id}/request-approval", ['reviewer_id' => $reviewer->id]);
        $this->actingAs($reviewer)->post("/tasks/{$task->id}/approve-review");

        $this->actingAs($author)
            ->post("/tasks/{$task->id}/move", ['board_column_id' => $done->id, 'position' => 1])
            ->assertRedirect();

        $this->assertSame($done->id, $task->refresh()->board_column_id);
    }

    public function test_rejecting_sends_the_task_back_to_in_progress_and_notifies_the_author()
    {
        Notification::fake();

        $author = User::factory()->create()->assignRole('Employee');
        $reviewer = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        [$inProgress, $review] = $board->columns()->get()->all();
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $review->id, 'created_by' => $author->id]);

        $this->actingAs($author)->post("/tasks/{$task->id}/request-approval", ['reviewer_id' => $reviewer->id]);

        $this->actingAs($reviewer)
            ->post("/tasks/{$task->id}/reject-review", ['reason' => 'Missing the client logo'])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame('rejected', $task->approval_status);
        $this->assertSame('Missing the client logo', $task->approval_note);
        $this->assertSame($inProgress->id, $task->board_column_id);

        Notification::assertSentTo($author, TaskApprovalDecided::class);
    }

    public function test_decision_requires_a_pending_request()
    {
        $reviewer = User::factory()->create()->assignRole('Employee');
        $board = $this->boardWithColumns();
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $board->columns()->first()->id, 'approver_id' => $reviewer->id]);

        $this->actingAs($reviewer)->post("/tasks/{$task->id}/approve-review")->assertStatus(422);
    }
}
