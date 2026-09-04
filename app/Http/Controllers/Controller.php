<?php

namespace App\Http\Controllers;

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
}
