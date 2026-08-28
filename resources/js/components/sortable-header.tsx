import { cn } from '@/lib/utils';
import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';

export type SortDirection = 'asc' | 'desc';
export type SortState = { column: string | null; direction: SortDirection };

/** column === current.column: flip direction. A new column: start ascending. */
export function toggleSort(current: SortState, column: string): SortState {
    return { column, direction: current.column === column && current.direction === 'asc' ? 'desc' : 'asc' };
}

/**
 * A clickable `<th>` that reports its own sort state via an icon — asc/desc
 * arrow when active, a faint neutral icon otherwise. Takes no padding of its
 * own — hand-rolled tables in this app use a couple of different cell
 * padding scales (`p-3` vs `py-1.5`), and tailwind-merge can't safely
 * collapse `p-3` down to a caller's `py-1.5` (it keeps both, which isn't the
 * same as the caller's plain `py-1.5` cells) — so `className` must supply
 * whatever padding the surrounding `<tr>`'s other cells use.
 */
export function SortableHeader({
    column,
    sort,
    onSort,
    className,
    children,
}: {
    column: string;
    sort: SortState;
    onSort: (column: string) => void;
    className?: string;
    children: React.ReactNode;
}) {
    const active = sort.column === column;
    const Icon = active ? (sort.direction === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown;
    const label = typeof children === 'string' ? children : column;

    return (
        <th className={cn('font-medium', className)}>
            <button
                type="button"
                onClick={() => onSort(column)}
                className="-m-1 flex items-center gap-1 rounded p-1 font-medium"
                aria-label={`Sort by ${label}${active ? `, currently ${sort.direction === 'asc' ? 'ascending' : 'descending'}` : ''}`}
            >
                {children}
                <Icon className={cn('size-3.5', active ? 'text-foreground' : 'opacity-40')} />
            </button>
        </th>
    );
}

/**
 * Client-side sort for a small, fully-loaded (non-paginated) array — safe
 * exactly because every row is already in the browser, so re-sorting can't
 * silently hide rows the way it would on a server-paginated table (that
 * needs SortableHeader driven by a `sort`/`direction` query param instead,
 * composed into the page's existing router.get filter call).
 */
export function useClientSort<T>(rows: T[], getValue: (row: T, column: string) => string | number | null) {
    const [sort, setSort] = useState<SortState>({ column: null, direction: 'asc' });

    const sorted = useMemo(() => {
        if (sort.column === null) {
            return rows;
        }

        const column = sort.column;
        const factor = sort.direction === 'asc' ? 1 : -1;

        return [...rows].sort((a, b) => {
            const left = getValue(a, column);
            const right = getValue(b, column);

            if (left === null) return right === null ? 0 : 1;
            if (right === null) return -1;

            if (typeof left === 'string' && typeof right === 'string') {
                return left.localeCompare(right) * factor;
            }

            return ((left as number) - (right as number)) * factor;
        });
    }, [rows, sort, getValue]);

    const onSort = (column: string) => setSort((current) => toggleSort(current, column));

    return { sorted, sort, onSort };
}
