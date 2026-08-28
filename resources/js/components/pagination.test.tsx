import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Pagination, type Paginated } from './pagination';

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    router: { get: getMock },
}));

function meta(overrides: Partial<Paginated<unknown>> = {}): Pick<Paginated<unknown>, 'links' | 'current_page' | 'last_page' | 'total'> {
    return {
        current_page: 2,
        last_page: 3,
        total: 55,
        links: [
            { url: '/reports/tasks?page=1', label: '&laquo; Previous', active: false },
            { url: '/reports/tasks?page=1', label: '1', active: false },
            { url: '/reports/tasks?page=2', label: '2', active: true },
            { url: '/reports/tasks?page=3', label: '3', active: false },
            { url: '/reports/tasks?page=3', label: 'Next &raquo;', active: false },
        ],
        ...overrides,
    };
}

describe('Pagination', () => {
    it('renders nothing when there is only one page', () => {
        const { container } = render(<Pagination meta={meta({ last_page: 1 })} />);
        expect(container).toBeEmptyDOMElement();
    });

    it('renders a button per page and marks the active one', () => {
        render(<Pagination meta={meta()} />);
        expect(screen.getByRole('button', { name: '2' })).toHaveAttribute('aria-current', 'page');
        expect(screen.getByRole('button', { name: '1' })).not.toHaveAttribute('aria-current');
        expect(screen.getByText(/Page 2 of 3/)).toBeInTheDocument();
        expect(screen.getByText(/55 total/)).toBeInTheDocument();
    });

    it('navigates via router.get with the paginator-provided URL when a page is clicked', async () => {
        render(<Pagination meta={meta()} />);
        await userEvent.click(screen.getByRole('button', { name: '3' }));
        expect(getMock).toHaveBeenCalledWith('/reports/tasks?page=3', {}, { preserveState: true, preserveScroll: true });
    });

    it('disables prev/next at the boundaries instead of rendering a dead link', () => {
        render(<Pagination meta={meta({ current_page: 1, links: meta().links.map((link, i) => (i === 0 ? { ...link, url: null } : link)) })} />);
        expect(screen.getByRole('button', { name: 'Previous page' })).toBeDisabled();
        expect(screen.getByRole('button', { name: 'Next page' })).not.toBeDisabled();
    });

    it('renders an ellipsis as plain text, not a clickable button', () => {
        render(
            <Pagination
                meta={meta({
                    links: [
                        { url: '/x?page=1', label: '&laquo; Previous', active: false },
                        { url: '/x?page=1', label: '1', active: false },
                        { url: null, label: '...', active: false },
                        { url: '/x?page=9', label: '9', active: false },
                        { url: '/x?page=9', label: 'Next &raquo;', active: false },
                    ],
                })}
            />,
        );
        expect(screen.getByText('...')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: '...' })).not.toBeInTheDocument();
    });
});
