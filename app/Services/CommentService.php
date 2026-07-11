<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CommentMention;
use App\Notifications\TaskCommented;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CommentService
{
    /**
     * Create a task comment, recording mentions and notifying mentioned
     * users plus the task's assignee and creator. Mentions are silently
     * limited to users who can actually view the task (US-030).
     */
    public static function createForTask(Task $task, User $author, string $body, ?int $parentId, array $mentionIds): Comment
    {
        $comment = DB::transaction(function () use ($task, $author, $body, $parentId, $mentionIds) {
            $comment = $task->comments()->create([
                'user_id' => $author->id,
                'body' => $body,
                'parent_id' => $parentId,
            ]);

            $mentioned = User::query()
                ->whereIn('id', array_unique($mentionIds))
                ->where('id', '!=', $author->id)
                ->get()
                ->filter(fn (User $user) => Gate::forUser($user)->allows('view', $task));

            foreach ($mentioned as $user) {
                $comment->mentions()->create([
                    'mentioned_user_id' => $user->id,
                    'notified_at' => now(),
                ]);
            }

            AuditLogger::log($task, 'commented', [], ['comment_id' => $comment->id]);

            return $comment;
        });

        $mentionedIds = $comment->mentions()->pluck('mentioned_user_id')->all();

        foreach (User::query()->whereIn('id', $mentionedIds)->get() as $user) {
            $user->notify(new CommentMention($comment));
        }

        $watchers = User::query()
            ->whereIn('id', array_filter([$task->primary_assignee_id, $task->created_by]))
            ->where('id', '!=', $author->id)
            ->whereNotIn('id', $mentionedIds)
            ->get()
            ->filter(fn (User $user) => Gate::forUser($user)->allows('view', $task));

        foreach ($watchers as $user) {
            $user->notify(new TaskCommented($comment));
        }

        return $comment;
    }
}
