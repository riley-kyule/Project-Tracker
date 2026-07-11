<?php

namespace Tests\Feature\Collaboration;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CommentMention;
use App\Notifications\TaskCommented;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(array $attributes = []): Task
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        return Task::factory()->create([
            'board_id' => $board->id,
            'board_column_id' => $column->id,
            ...$attributes,
        ]);
    }

    public function test_users_can_comment_and_reply()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/comments", ['body' => 'First comment'])
            ->assertRedirect();

        $comment = Comment::query()->where('body', 'First comment')->firstOrFail();

        $this->actingAs($user)
            ->post("/tasks/{$task->id}/comments", ['body' => 'A reply', 'parent_id' => $comment->id])
            ->assertRedirect();

        $this->assertSame(1, $comment->replies()->count());
    }

    public function test_mentions_notify_eligible_users_only()
    {
        Notification::fake();

        $author = User::factory()->create()->assignRole('Employee');
        $colleague = User::factory()->create()->assignRole('Employee');

        // Restricted board the outsider cannot see.
        $restricted = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $column = BoardColumn::factory()->create(['board_id' => $restricted->id]);
        $task = Task::factory()->create(['board_id' => $restricted->id, 'board_column_id' => $column->id]);
        $restricted->members()->attach([$author->id, $colleague->id]);

        $outsider = User::factory()->create()->assignRole('Employee');

        $this->actingAs($author)->post("/tasks/{$task->id}/comments", [
            'body' => 'Ping',
            'mention_ids' => [$colleague->id, $outsider->id],
        ]);

        Notification::assertSentTo($colleague, CommentMention::class);
        Notification::assertNotSentTo($outsider, CommentMention::class);
        $this->assertSame(1, Comment::query()->firstOrFail()->mentions()->count());
    }

    public function test_comment_notifies_assignee_and_creator()
    {
        Notification::fake();

        $creator = User::factory()->create()->assignRole('Employee');
        $assignee = User::factory()->create()->assignRole('Employee');
        $commenter = User::factory()->create()->assignRole('Employee');

        $task = $this->makeTask([
            'created_by' => $creator->id,
            'primary_assignee_id' => $assignee->id,
        ]);

        $this->actingAs($commenter)->post("/tasks/{$task->id}/comments", ['body' => 'Status?']);

        Notification::assertSentTo($creator, TaskCommented::class);
        Notification::assertSentTo($assignee, TaskCommented::class);
        Notification::assertNotSentTo($commenter, TaskCommented::class);
    }

    public function test_users_outside_a_restricted_board_cannot_comment()
    {
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $task = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id]);

        $outsider = User::factory()->create()->assignRole('Employee');

        $this->actingAs($outsider)
            ->post("/tasks/{$task->id}/comments", ['body' => 'Sneaky'])
            ->assertForbidden();
    }

    public function test_authors_can_delete_own_comments_but_not_others()
    {
        $author = User::factory()->create()->assignRole('Employee');
        $other = User::factory()->create()->assignRole('Employee');
        $task = $this->makeTask();

        $this->actingAs($author)->post("/tasks/{$task->id}/comments", ['body' => 'Mine']);
        $comment = Comment::query()->firstOrFail();

        $this->actingAs($other)->delete("/comments/{$comment->id}")->assertForbidden();
        $this->actingAs($author)->delete("/comments/{$comment->id}")->assertRedirect();
        $this->assertSoftDeleted('comments', ['id' => $comment->id]);
    }
}
