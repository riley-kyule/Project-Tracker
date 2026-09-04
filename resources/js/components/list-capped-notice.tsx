/**
 * Shown on the list pages that ship the whole set to the client (People,
 * Assets, Users) when the server hit its LIST_CAP — see App\Http\Controllers\Controller.
 */
export function ListCappedNotice({ capped }: { capped: boolean }) {
    if (!capped) return null;

    return (
        <p className="text-xs text-amber-600 dark:text-amber-500">Showing the first 500 — use search and the filters to narrow the list.</p>
    );
}
