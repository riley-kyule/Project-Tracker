import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { useEffect, useMemo, useState } from 'react';

export const LIST_SIZES = [20, 50, 100, 200, 'All'] as const;
export type ListSize = (typeof LIST_SIZES)[number];

/**
 * Windows an already-sorted/filtered, fully-loaded array into pages — the
 * client-side counterpart to server pagination, for the (majority of)
 * EWMS list pages that ship their whole capped result set to the browser
 * rather than paginating server-side (see ListCappedNotice). Defaults to
 * 20 rows; changing the size or the underlying row set snaps back to page 1
 * so a picker change or a filter/sort change never strands you on a now
 * out-of-range page.
 */
export function usePagedList<T>(rows: T[], defaultSize: ListSize = 20) {
    const [size, setSize] = useState<ListSize>(defaultSize);
    const [page, setPage] = useState(1);

    const totalPages = size === 'All' ? 1 : Math.max(1, Math.ceil(rows.length / size));
    const currentPage = Math.min(page, totalPages);

    const pageRows = useMemo(() => {
        if (size === 'All') return rows;
        const start = (currentPage - 1) * size;
        return rows.slice(start, start + size);
    }, [rows, size, currentPage]);

    // rows.length, not `rows` itself — a same-length re-sort shouldn't reset
    // the page the way a filter narrowing the set (or the size picker) does.
    useEffect(() => setPage(1), [size, rows.length]);

    return { size, setSize, page: currentPage, setPage, pageRows, totalPages, total: rows.length };
}

export function ListSizePicker({
    value,
    onChange,
    className,
}: {
    value: ListSize;
    onChange: (value: ListSize) => void;
    className?: string;
}) {
    return (
        <Select value={String(value)} onValueChange={(v) => onChange(v === 'All' ? 'All' : (Number(v) as ListSize))}>
            <SelectTrigger className={cn('h-8 w-[4.5rem] text-xs', className)} aria-label="Rows per page">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {LIST_SIZES.map((n) => (
                    <SelectItem key={n} value={String(n)}>
                        {n}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

/**
 * Pairs the size picker with Previous/Next + "Page X of Y (N rows)" — the
 * whole footer bar for a `usePagedList` table. Renders nothing when
 * everything fits on one page at the smallest size and there's nothing to
 * page through, but the size picker itself still shows once there's more
 * than one row worth picking a size for.
 */
export function ListPagination({
    size,
    onSizeChange,
    page,
    totalPages,
    onPageChange,
    total,
    itemLabel = 'rows',
    className,
}: {
    size: ListSize;
    onSizeChange: (value: ListSize) => void;
    page: number;
    totalPages: number;
    onPageChange: (page: number) => void;
    total: number;
    itemLabel?: string;
    className?: string;
}) {
    if (total === 0) return null;

    return (
        <div className={cn('flex flex-wrap items-center justify-between gap-2 text-xs', className)}>
            <div className="text-muted-foreground flex items-center gap-2">
                <span>Show</span>
                <ListSizePicker value={size} onChange={onSizeChange} />
                <span>
                    of {total} {itemLabel}
                </span>
            </div>
            {totalPages > 1 && (
                <div className="flex items-center gap-2">
                    <span className="text-muted-foreground">
                        Page {page} of {totalPages}
                    </span>
                    <div className="flex gap-1">
                        <Button size="sm" variant="outline" className="h-7 px-2 text-xs" disabled={page === 1} onClick={() => onPageChange(page - 1)}>
                            Previous
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            className="h-7 px-2 text-xs"
                            disabled={page === totalPages}
                            onClick={() => onPageChange(page + 1)}
                        >
                            Next
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}
