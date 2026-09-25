<?php

namespace App\Http\Controllers\Cs;

use App\Http\Controllers\Controller;
use App\Models\Board;
use App\Services\Cs\CsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "My CS Cards" (/cs-board) no longer renders its own page: the weighted
 * scoring system lives as the "Score Based" tab on the Customer Service
 * department's existing Kanban board (resources/js/pages/boards/show.tsx),
 * one page with two tabs. This route stays as a redirect so bookmarks and the
 * sidebar link keep working. Mirrors App\Http\Controllers\Seo\SeoBoardController.
 */
class CsBoardController extends Controller
{
    public function mine(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isCsEmployee() || CsAccess::leadsBoard($user), 404, 'The Customer Service Board is only available to the Customer Service team and its managers.');

        $department = CsAccess::department();
        abort_if($department === null, 404, 'No Customer Service department is set up yet.');

        $kanbanBoard = Board::query()->where('department_id', $department->id)->where('is_active', true)->orderBy('id')->get()
            ->first(fn (Board $b) => Gate::forUser($user)->allows('view', $b));

        abort_if($kanbanBoard === null, 404, 'No Customer Service board is set up for your department yet.');

        return redirect("/boards/{$kanbanBoard->id}");
    }
}
