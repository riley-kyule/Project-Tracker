<?php

namespace App\Http\Controllers;

use App\Models\Board;
use App\Models\SavedFilter;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SavedFilterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) {
                    $isBoardScope = is_string($value) && preg_match('/^board\.\d+$/', $value) === 1;

                    if ($value !== SavedFilter::SCOPE_REPORTS_TASKS && ! $isBoardScope) {
                        $fail('Unknown filter scope.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:100'],
            'filters' => ['required', 'array'],
        ]);

        // The shape check above only validates the string format — a
        // board-scoped filter still needs the same view authorization as the
        // board itself, so a user can't stash a filter under a board they
        // can't see.
        if (str_starts_with($validated['scope'], 'board.')) {
            $board = Board::query()->find((int) substr($validated['scope'], strlen('board.')));

            if ($board === null || Gate::forUser($request->user())->denies('view', $board)) {
                throw ValidationException::withMessages(['scope' => 'You cannot save a filter for that board.']);
            }
        }

        SavedFilter::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'scope' => $validated['scope'], 'name' => $validated['name']],
            ['filters' => $validated['filters']],
        );

        return back()->with('success', 'Filter saved.');
    }

    public function destroy(Request $request, SavedFilter $savedFilter): RedirectResponse
    {
        abort_unless($savedFilter->user_id === $request->user()->id, 403);

        $savedFilter->delete();

        return back();
    }
}
