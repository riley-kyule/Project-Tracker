<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Ceiling for list pages that ship the whole set to the client for
     * instant search/sort/filter (People, Assets, Users, …). Past this the
     * page shows "the first N — narrow with search" instead of an unbounded
     * query and a frozen render. Bump it, or move that page to server-side
     * pagination, if a real dataset ever gets close.
     */
    protected const LIST_CAP = 500;

    /**
     * Matches the frontend's <ListSizePicker> options (20/50/100/200/All) for
     * every server-paginated ("real" LengthAwarePaginator) list page. "All"
     * still paginates — at ALL_SIZE_CEILING — rather than truly loading
     * everything, so a mistaken click on a huge table can't turn one request
     * into an unbounded query.
     */
    private const ALLOWED_PAGE_SIZES = [20, 50, 100, 200];

    private const ALL_SIZE_CEILING = 5000;

    protected function perPage(Request $request, int $default = 20): int
    {
        $raw = $request->query('per_page');

        if ($raw === 'All' || $raw === 'all') {
            return self::ALL_SIZE_CEILING;
        }

        $value = (int) $raw;

        return in_array($value, self::ALLOWED_PAGE_SIZES, true) ? $value : $default;
    }
}
