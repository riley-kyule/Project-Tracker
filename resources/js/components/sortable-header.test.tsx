import { render, renderHook, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { act } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { SortableHeader, toggleSort, useClientSort } from './sortable-header';

describe('toggleSort', () => {
    it('starts a new column ascending', () => {
        expect(toggleSort({ column: null, direction: 'asc' }, 'name')).toEqual({ column: 'name', direction: 'asc' });
    });

    it('flips the same column from ascending to descending', () => {
        expect(toggleSort({ column: 'name', direction: 'asc' }, 'name')).toEqual({ column: 'name', direction: 'desc' });
    });

    it('switching to a different column resets to ascending', () => {
        expect(toggleSort({ column: 'name', direction: 'desc' }, 'email')).toEqual({ column: 'email', direction: 'asc' });
    });
});

describe('SortableHeader', () => {
    it('calls onSort with its own column when clicked', async () => {
        const onSort = vi.fn();
        render(
            <table>
                <thead>
                    <tr>
                        <SortableHeader column="name" sort={{ column: null, direction: 'asc' }} onSort={onSort}>
                            Name
                        </SortableHeader>
                    </tr>
                </thead>
            </table>,
        );

        await userEvent.click(screen.getByRole('button', { name: /sort by name/i }));
        expect(onSort).toHaveBeenCalledWith('name');
    });

    it('announces current direction in its accessible name once active', () => {
        render(
            <table>
                <thead>
                    <tr>
                        <SortableHeader column="name" sort={{ column: 'name', direction: 'desc' }} onSort={() => {}}>
                            Name
                        </SortableHeader>
                    </tr>
                </thead>
            </table>,
        );

        expect(screen.getByRole('button', { name: /currently descending/i })).toBeInTheDocument();
    });
});

describe('useClientSort', () => {
    const rows = [
        { id: 1, name: 'Charlie', age: 40 },
        { id: 2, name: 'Alice', age: null },
        { id: 3, name: 'Bob', age: 25 },
    ];
    const getValue = (row: (typeof rows)[number], column: string) => (column === 'name' ? row.name : row.age);

    it('returns rows unsorted until a column is chosen', () => {
        const { result } = renderHook(() => useClientSort(rows, getValue));
        expect(result.current.sorted.map((r) => r.id)).toEqual([1, 2, 3]);
    });

    it('sorts strings ascending then descending on repeated clicks', () => {
        const { result } = renderHook(() => useClientSort(rows, getValue));

        act(() => result.current.onSort('name'));
        expect(result.current.sorted.map((r) => r.name)).toEqual(['Alice', 'Bob', 'Charlie']);

        act(() => result.current.onSort('name'));
        expect(result.current.sorted.map((r) => r.name)).toEqual(['Charlie', 'Bob', 'Alice']);
    });

    it('sorts nulls to the end regardless of direction', () => {
        const { result } = renderHook(() => useClientSort(rows, getValue));

        act(() => result.current.onSort('age'));
        expect(result.current.sorted.map((r) => r.id)).toEqual([3, 1, 2]);

        act(() => result.current.onSort('age'));
        expect(result.current.sorted.map((r) => r.id)).toEqual([1, 3, 2]);
    });
});
