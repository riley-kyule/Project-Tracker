import { DndContext, PointerSensor, useSensor, useSensors } from '@dnd-kit/core';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { TaskCard, type BoardTask } from './task-card';

const baseTask: BoardTask = {
    id: 1,
    task_number: 42,
    title: 'Ship the thing',
    description: null,
    priority: 'medium',
    start_date: null,
    due_at: null,
    progress_percentage: 0,
    ceo_priority: false,
    work_location: 'unspecified',
    board_column_id: 1,
    position: 1,
    assignee: null,
    labels: [],
};

// useSortable() reads dnd-kit's DndContext; every render needs one. The card's
// drag listeners now live on the whole card, so the wrapper mirrors the real
// board's PointerSensor (6px activation distance) — without it dnd-kit's default
// pointer activator fires on pointerdown and swallows the click that opens the task.
function Wrapper({ children }: { children: React.ReactNode }) {
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));
    return <DndContext sensors={sensors}>{children}</DndContext>;
}

function renderCard(props: Partial<React.ComponentProps<typeof TaskCard>> = {}) {
    return render(
        <Wrapper>
            <TaskCard task={baseTask} {...props} />
        </Wrapper>,
    );
}

describe('TaskCard', () => {
    it('opens the task on click', async () => {
        const onOpen = vi.fn();
        renderCard({ onOpen });

        await userEvent.click(screen.getByText('Ship the thing'));

        expect(onOpen).toHaveBeenCalledWith(baseTask);
    });

    it('opens the task on Enter when the card itself is focused', async () => {
        const onOpen = vi.fn();
        const { container } = renderCard({ onOpen });

        // The whole card is the drag surface (no separate handle); the explicit
        // role attribute is unique to the card root.
        const card = container.querySelector<HTMLElement>('[role="button"]')!;
        card.focus();
        await userEvent.keyboard('{Enter}');

        expect(onOpen).toHaveBeenCalledWith(baseTask);
    });

    it('does not open the task on Space — Space is handed to dnd-kit to pick up', async () => {
        const onOpen = vi.fn();
        const { container } = renderCard({ onOpen });

        const card = container.querySelector<HTMLElement>('[role="button"]')!;
        card.focus();
        await userEvent.keyboard(' ');

        expect(onOpen).not.toHaveBeenCalled();
    });

    it('marks the card root as a dnd-kit sortable so the whole card is draggable', () => {
        const { container } = renderCard();

        const card = container.querySelector<HTMLElement>('[role="button"]')!;
        expect(card).toHaveAttribute('aria-roledescription', 'sortable');
    });

    it('the overlay copy is not interactive as a sortable', () => {
        const { container } = renderCard({ onOpen: vi.fn(), overlay: true });

        const card = container.querySelector<HTMLElement>('[role="button"]')!;
        expect(card).not.toHaveAttribute('aria-roledescription');
    });

    it('renders no selection checkbox when onToggleSelect is not provided', () => {
        renderCard();

        expect(screen.queryByRole('checkbox')).not.toBeInTheDocument();
    });

    it('reports selection without opening the task', async () => {
        const onOpen = vi.fn();
        const onToggleSelect = vi.fn();
        renderCard({ onOpen, onToggleSelect, selected: false });

        await userEvent.click(screen.getByRole('checkbox', { name: /select ship the thing/i }));

        expect(onToggleSelect).toHaveBeenCalledWith(1, true);
        expect(onOpen).not.toHaveBeenCalled();
    });

    it('reflects the selected prop on the checkbox', () => {
        renderCard({ onToggleSelect: vi.fn(), selected: true });

        expect(screen.getByRole('checkbox', { name: /select ship the thing/i })).toBeChecked();
    });

    it('uses the first label as a cover color strip', () => {
        const { container } = renderCard({
            task: { ...baseTask, labels: [{ id: 1, name: 'Urgent', color: '#ff0000' }] },
        });

        const card = container.querySelector<HTMLElement>('[role="button"]')!;
        expect(card.style.borderTopColor).toBe('rgb(255, 0, 0)');
        expect(card.style.borderTopWidth).toBe('4px');
    });

    it('has no cover strip when the task has no labels', () => {
        const { container } = renderCard();

        const card = container.querySelector<HTMLElement>('[role="button"]')!;
        expect(card.style.borderTopColor).toBe('');
    });
});
