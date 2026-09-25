<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Board;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "My SEO Board" (/seo-board) no longer renders its own page — the weighted
 * scoring system lives as the "Score Based" tab on the SEO department's
 * existing Kanban board (resources/js/pages/boards/show.tsx) instead, per
 * the "one page, two tabs, not two linked pages" request. This route stays
 * as a redirect so old bookmarks/sidebar links keep working.
 */
class SeoBoardController extends Controller
{
    public function mine(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSeoEmployee(), 404, 'The SEO Board is only available to SEO team members.');

        $kanbanBoard = Board::query()->where('department_id', $request->user()->department_id)->where('is_active', true)->orderBy('id')->get()
            ->first(fn (Board $b) => Gate::forUser($request->user())->allows('view', $b));

        abort_if($kanbanBoard === null, 404, 'No SEO Board is set up for your department yet.');

        return redirect("/boards/{$kanbanBoard->id}");
    }
}
