import { ListSizePicker, type ListSize } from '@/components/list-pagination';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export type PaginationLink = { url: string | null; label: string; active: boolean };

/** Shape of a Laravel LengthAwarePaginator once it reaches the frontend via Inertia. */
export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};

function decodeLabel(label: string): string {
    return label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»');
}

/** The backend's "All" maps to a large-but-bounded per_page (see Controller::perPage), not a truly unpaginated response — anything past the largest real picker option reads back as "All". */
export function sizeFromPerPage(perPage: number): ListSize {
    return perPage > 200 ? 'All' : (perPage as ListSize);
}

/**
 * Renders a Laravel paginator's page links. Every `url` already carries the
 * current filter query string (controllers call ->withQueryString()), so
 * navigating is just a plain router.get to that URL — no filter state needs
 * to be merged back in here.
 */
export function Pagination({
    meta,
    sizePicker,
}: {
    meta: Pick<Paginated<unknown>, 'links' | 'current_page' | 'last_page' | 'total'>;
    /** A server-paginated list's page size, driven by the same `apply()`-style query-param pattern each page already uses for its filters. */
    sizePicker?: { value: ListSize; onChange: (value: ListSize) => void };
}) {
    if (meta.last_page <= 1 && !sizePicker) {
        return null;
    }

    const go = (url: string | null) => {
        if (url) {
            router.get(url, {}, { preserveState: true, preserveScroll: true });
        }
    };

    // Laravel's `links` array is always [prev, ...page numbers/ellipses, next].
    const prev = meta.links[0];
    const next = meta.links[meta.links.length - 1];
    const pages = meta.links.slice(1, -1);

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
            <div className="text-muted-foreground flex flex-wrap items-center gap-2">
                {sizePicker && (
                    <>
                        <span>Show</span>
                        <ListSizePicker value={sizePicker.value} onChange={sizePicker.onChange} />
                    </>
                )}
                <span>
                    Page {meta.current_page} of {meta.last_page} · {meta.total} total
                </span>
            </div>
            {meta.last_page > 1 && (
                <div className="flex flex-wrap items-center gap-1">
                    <Button size="sm" variant="outline" disabled={!prev.url} onClick={() => go(prev.url)} aria-label="Previous page">
                        <ChevronLeft className="size-4" />
                    </Button>
                    {pages.map((link, index) =>
                        link.url === null ? (
                            <span key={index} className="text-muted-foreground px-1.5 text-xs">
                                {decodeLabel(link.label)}
                            </span>
                        ) : (
                            <Button
                                key={index}
                                size="sm"
                                variant={link.active ? 'secondary' : 'outline'}
                                aria-current={link.active ? 'page' : undefined}
                                onClick={() => go(link.url)}
                            >
                                {decodeLabel(link.label)}
                            </Button>
                        ),
                    )}
                    <Button size="sm" variant="outline" disabled={!next.url} onClick={() => go(next.url)} aria-label="Next page">
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            )}
        </div>
    );
}
