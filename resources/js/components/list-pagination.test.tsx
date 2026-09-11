import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { usePagedList } from './list-pagination';

describe('usePagedList', () => {
    const rows = Array.from({ length: 45 }, (_, i) => ({ id: i + 1 }));

    it('defaults to 20 rows on page 1', () => {
        const { result } = renderHook(() => usePagedList(rows));
        expect(result.current.size).toBe(20);
        expect(result.current.page).toBe(1);
        expect(result.current.pageRows).toHaveLength(20);
        expect(result.current.pageRows[0].id).toBe(1);
        expect(result.current.totalPages).toBe(3);
        expect(result.current.total).toBe(45);
    });

    it('pages forward within the current size', () => {
        const { result } = renderHook(() => usePagedList(rows));
        act(() => result.current.setPage(2));
        expect(result.current.pageRows).toHaveLength(20);
        expect(result.current.pageRows[0].id).toBe(21);
        act(() => result.current.setPage(3));
        expect(result.current.pageRows).toHaveLength(5);
        expect(result.current.pageRows[0].id).toBe(41);
    });

    it('"All" returns every row on a single page', () => {
        const { result } = renderHook(() => usePagedList(rows));
        act(() => result.current.setSize('All'));
        expect(result.current.pageRows).toHaveLength(45);
        expect(result.current.totalPages).toBe(1);
    });

    it('changing size snaps back to page 1', () => {
        const { result } = renderHook(() => usePagedList(rows));
        act(() => result.current.setPage(3));
        act(() => result.current.setSize(50));
        expect(result.current.page).toBe(1);
        expect(result.current.pageRows).toHaveLength(45);
    });

    it('a shrinking row set (e.g. a filter) clamps a stranded page back into range', () => {
        const { result, rerender } = renderHook(({ rows }) => usePagedList(rows), { initialProps: { rows } });
        act(() => result.current.setPage(3));
        rerender({ rows: rows.slice(0, 10) });
        expect(result.current.page).toBe(1);
        expect(result.current.pageRows).toHaveLength(10);
    });
});
