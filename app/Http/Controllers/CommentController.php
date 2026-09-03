<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use App\Models\Ticket;
use App\Services\AuditLogger;
use App\Services\CommentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        Gate::authorize('view', $task);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')
                    ->where('commentable_type', Task::class)
                    ->where('commentable_id', $task->id),
            ],
            'mention_ids' => ['sometimes', 'array', 'max:20'],
            'mention_ids.*' => ['integer', 'exists:users,id'],
            'note_type' => ['nullable', 'string', Rule::in(Comment::NOTE_TYPES)],
            'structured_fields' => ['nullable', 'array'],
        ]);

        CommentService::createForTask(
            $task,
            $request->user(),
            $validated['body'],
            $validated['parent_id'] ?? null,
            $validated['mention_ids'] ?? [],
            $validated['note_type'] ?? null,
            $validated['structured_fields'] ?? null,
        );

        return back();
    }

    /** Author-only edit of a free-text comment, allowed for Comment::EDIT_WINDOW_MINUTES after posting. Serves both task and ticket comments. */
    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $parent = $comment->commentable;
        abort_unless($parent instanceof Task || $parent instanceof Ticket, 404);
        Gate::authorize('view', $parent);

        abort_unless($comment->isEditableBy($request->user()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $comment->forceFill([
            'body' => $validated['body'],
            'edited_at' => now(),
        ])->save();

        AuditLogger::log($parent, 'comment_edited', [], ['comment_id' => $comment->id]);

        return back();
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $parent = $comment->commentable;
        abort_unless($parent instanceof Task, 404);
        Gate::authorize('view', $parent);

        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->can('boards.manage'),
            403,
        );

        $comment->delete();
        AuditLogger::log($parent, 'comment_removed', ['comment_id' => $comment->id], []);

        return back();
    }
}
