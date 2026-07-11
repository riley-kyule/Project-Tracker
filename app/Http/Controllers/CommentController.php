<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
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
        ]);

        CommentService::createForTask(
            $task,
            $request->user(),
            $validated['body'],
            $validated['parent_id'] ?? null,
            $validated['mention_ids'] ?? [],
        );

        return back();
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->hasRole('Administrator'),
            403,
        );

        $comment->delete();

        return back();
    }
}
