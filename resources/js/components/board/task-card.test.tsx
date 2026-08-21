import { DndContext } from '@dnd-kit/core';
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

// useSortable() reads dnd-kit's DndContext; every render needs one, even
// though these tests never actually start a drag.
function renderCard(props: Partial<React.ComponentProps<typeof TaskCard>> = {}) {
    return render(
        <DndContext>
            <TaskCard task={baseTask} {...props} />
        </DndContext>,
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

        // The card is a div[role=button] wrapping a real <button> drag handle,
        // so role-based queries alone can't disambiguate the two — the
        // explicit role attribute is unique to the card itself.
        const card = container.querySelector<HTMLElement>('[role="button"]')!;
        card.focus();
        await userEvent.keyboard('{Enter}');

        expect(onOpen).toHaveBeenCalledWith(baseTask);
    });

    it('opens the task on Space when the card itself is focused', async () => {
        const onOpen = vi.fn();
        const { container } = renderCard({ onOpen });

        const card = container.querySelector<HTMLElement>('[role="button"]')!;
        card.focus();
        await userEvent.keyboard(' ');

        expect(onOpen).toHaveBeenCalledWith(baseTask);
    });

    it('does not open the task when the drag handle is clicked', async () => {
        const onOpen = vi.fn();
        const { container } = renderCard({ onOpen });

        await userEvent.click(container.querySelector('button')!);

        expect(onOpen).not.toHaveBeenCalled();
    });

    it('the overlay copy has no interactive drag handle or open handler', () => {
        const onOpen = vi.fn();
        const { container } = renderCard({ onOpen, overlay: true });

        expect(container.querySelector('button')).not.toBeInTheDocument();
    });
});
