import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import TicketsIndex, { type TicketStatus } from './index';

vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { get: vi.fn() },
}));

vi.mock('@/layouts/app-layout', () => ({
    default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

function baseTicket(overrides: Partial<Parameters<typeof TicketsIndex>[0]['tickets']['data'][number]> = {}) {
    return {
        id: 1,
        ticket_number: 42,
        title: 'Printer is on fire',
        status: 'new' as TicketStatus,
        priority: 'high' as const,
        requester: { id: 1, name: 'Ada Lovelace' },
        assignee: null,
        category: { id: 1, name: 'Hardware' },
        due_at: null,
        created_at: '2026-08-28T00:00:00Z',
        ...overrides,
    };
}

const defaultProps = {
    categories: [{ id: 1, name: 'Hardware' }],
    isManager: true,
    canCreateForOthers: false,
    users: [],
    filters: {},
    sort: null,
    direction: 'asc' as const,
};

describe('TicketsIndex', () => {
    // Regression: a ticket whose category row no longer exists (real prod
    // data, category_id has no nullOnDelete and there's no destroy route —
    // it can only happen via a direct DB operation outside the app) used to
    // crash the whole page with "Cannot read properties of null (reading
    // 'name')" because the row rendered `ticket.category.name` unguarded.
    it('renders a ticket with no category instead of crashing', () => {
        const tickets = {
            data: [baseTicket({ category: null })],
            links: [],
            current_page: 1,
            last_page: 1,
            total: 1,
        };

        expect(() => render(<TicketsIndex tickets={tickets} {...defaultProps} />)).not.toThrow();
        expect(screen.getByText('Printer is on fire')).toBeInTheDocument();
        // Both the assignee and category cells render '—' for this ticket (assignee: null, category: null).
        expect(screen.getAllByText('—')).toHaveLength(2);
    });

    it('still shows the category name when one exists', () => {
        const tickets = {
            data: [baseTicket()],
            links: [],
            current_page: 1,
            last_page: 1,
            total: 1,
        };

        render(<TicketsIndex tickets={tickets} {...defaultProps} />);
        expect(screen.getByText('Hardware')).toBeInTheDocument();
    });
});
