<?php

namespace Tests\Feature\ServiceDesk;

use App\Models\Board;
use App\Models\BoardColumn;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_can_convert_ticket_to_linked_task()
    {
        $tech = User::factory()->create()->assignRole('IT Technician');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        $ticket = Ticket::factory()->create([
            'category_id' => TicketCategory::query()->firstOrFail()->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
            'assigned_to' => $tech->id,
            'priority' => 'high',
        ]);

        $this->actingAs($tech)->post("/tickets/{$ticket->id}/convert-to-task", [
            'board_id' => $board->id,
            'board_column_id' => $column->id,
        ])->assertRedirect("/boards/{$board->id}");

        $ticket->refresh();
        $task = Task::query()->firstOrFail();

        $this->assertSame($task->id, $ticket->converted_task_id);
        $this->assertSame('high', $task->priority);
        $this->assertSame($tech->id, $task->primary_assignee_id);
        $this->assertSame($ticket->id, $task->metadata['source_ticket_id']);
        $this->assertSame(Ticket::STATUS_ESCALATED, $ticket->status);
        $this->assertStringContainsString("TK-{$ticket->ticket_number}", $task->description);
    }

    public function test_conversion_carries_over_ticket_attachments()
    {
        Storage::fake('local');

        $tech = User::factory()->create()->assignRole('IT Technician');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);

        $ticket = Ticket::factory()->create([
            'category_id' => TicketCategory::query()->firstOrFail()->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($tech)->post("/tickets/{$ticket->id}/attachments", [
            'file' => UploadedFile::fake()->createWithContent('screenshot.pdf', "%PDF-1.4\n".str_repeat('0', 200)),
        ])->assertRedirect();

        $originalAttachment = $ticket->attachments()->firstOrFail();

        $this->actingAs($tech)->post("/tickets/{$ticket->id}/convert-to-task", [
            'board_id' => $board->id,
            'board_column_id' => $column->id,
        ])->assertRedirect("/boards/{$board->id}");

        $task = Task::query()->firstOrFail();
        $copy = $task->attachments()->firstOrFail();

        $this->assertSame('screenshot.pdf', $copy->original_name);
        $this->assertNotSame($originalAttachment->path, $copy->path);
        Storage::disk('local')->assertExists($copy->path);
    }

    public function test_column_must_belong_to_board()
    {
        $tech = User::factory()->create()->assignRole('IT Technician');
        $board = Board::factory()->create();
        $otherColumn = BoardColumn::factory()->create(); // different board

        $ticket = Ticket::factory()->create([
            'category_id' => TicketCategory::query()->firstOrFail()->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($tech)->post("/tickets/{$ticket->id}/convert-to-task", [
            'board_id' => $board->id,
            'board_column_id' => $otherColumn->id,
        ])->assertStatus(422);
    }

    public function test_technician_cannot_convert_into_an_inaccessible_board()
    {
        $tech = User::factory()->create()->assignRole('IT Technician');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);
        $column = BoardColumn::factory()->create(['board_id' => $board->id]);
        $ticket = Ticket::factory()->create([
            'category_id' => TicketCategory::query()->firstOrFail()->id,
            'status' => Ticket::STATUS_IN_PROGRESS,
        ]);

        $this->actingAs($tech)->post("/tickets/{$ticket->id}/convert-to-task", [
            'board_id' => $board->id,
            'board_column_id' => $column->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('tasks', 0);
    }
}
