<?php

namespace App\Http\Controllers;

use App\Http\Requests\Boards\BoardColumnRequest;
use App\Models\Board;
use App\Models\BoardColumn;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BoardColumnController extends Controller
{
    public function store(BoardColumnRequest $request, Board $board): RedirectResponse
    {
        Gate::authorize('manage', $board);

        $validated = $request->validated();

        $column = $board->columns()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($board, $validated['name']),
            'is_completion_column' => $validated['semantic_status'] === 'completed',
            'is_archive_column' => $validated['semantic_status'] === 'archived',
            'position' => (int) $board->columns()->max('position') + 1,
        ]);

        AuditLogger::log($board, 'column_created', [], ['column_id' => $column->id, 'name' => $column->name]);

        return back();
    }

    public function update(BoardColumnRequest $request, BoardColumn $column): RedirectResponse
    {
        Gate::authorize('manage', $column->board);

        $validated = $request->validated();
        $old = $column->only(['name', 'semantic_status', 'wip_limit']);

        $column->update([
            ...$validated,
            'slug' => $validated['name'] === $column->name ? $column->slug : $this->uniqueSlug($column->board, $validated['name'], $column),
            'is_completion_column' => $validated['semantic_status'] === 'completed',
            'is_archive_column' => $validated['semantic_status'] === 'archived',
        ]);

        AuditLogger::log($column->board, 'column_renamed', $old, $column->only(['name', 'semantic_status', 'wip_limit']));

        return back();
    }

    public function destroy(BoardColumn $column): RedirectResponse
    {
        Gate::authorize('manage', $column->board);

        if ($column->tasks()->exists()) {
            throw ValidationException::withMessages([
                'column' => 'Move or remove every task out of this column before deleting it.',
            ]);
        }

        AuditLogger::log($column->board, 'column_deleted', ['column_id' => $column->id, 'name' => $column->name], []);
        $column->delete();

        return back();
    }

    public function reorder(Request $request, Board $board): RedirectResponse
    {
        Gate::authorize('manage', $board);

        $validated = $request->validate([
            'column_ids' => ['required', 'array'],
            'column_ids.*' => ['integer', 'exists:board_columns,id'],
        ]);

        $columns = $board->columns()->whereIn('id', $validated['column_ids'])->pluck('id');

        if ($columns->count() !== count($validated['column_ids']) || $columns->count() !== $board->columns()->count()) {
            throw ValidationException::withMessages(['column_ids' => 'The column list must match every column on this board exactly once.']);
        }

        foreach (array_values($validated['column_ids']) as $index => $columnId) {
            BoardColumn::query()->whereKey($columnId)->update(['position' => $index + 1]);
        }

        AuditLogger::log($board, 'columns_reordered', [], ['column_ids' => $validated['column_ids']]);

        return back();
    }

    private function uniqueSlug(Board $board, string $name, ?BoardColumn $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while ($board->columns()->where('slug', $slug)->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
