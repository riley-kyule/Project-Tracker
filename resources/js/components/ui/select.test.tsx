import { fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from './select';

// jsdom has no layout engine, so Radix's pointer-capture and scroll-into-view
// calls (used while opening/navigating the popover) throw without these.
beforeAll(() => {
    Element.prototype.hasPointerCapture = vi.fn(() => false);
    Element.prototype.setPointerCapture = vi.fn();
    Element.prototype.releasePointerCapture = vi.fn();
    Element.prototype.scrollIntoView = vi.fn();
});

function Harness({ onChange = vi.fn() }: { onChange?: (value: string) => void }) {
    return (
        <Select onValueChange={onChange} defaultValue="kenya">
            <SelectTrigger aria-label="Country">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="kenya">Kenya</SelectItem>
                <SelectItem value="uganda">Uganda</SelectItem>
                <SelectItem value="tanzania">Tanzania</SelectItem>
                <SelectItem value="senegal">Senegal</SelectItem>
            </SelectContent>
        </Select>
    );
}

describe('Select', () => {
    it('narrows options to the typed search term', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await user.click(screen.getByRole('combobox', { name: 'Country' }));
        expect(screen.getByRole('option', { name: 'Senegal' })).toBeInTheDocument();

        await user.type(screen.getByRole('textbox', { name: /filter options/i }), 'ken');

        expect(screen.getByRole('option', { name: 'Kenya' })).toBeInTheDocument();
        expect(screen.queryByRole('option', { name: 'Senegal' })).not.toBeInTheDocument();
    });

    it('shows a no-matches message when nothing matches', async () => {
        const user = userEvent.setup();
        render(<Harness />);

        await user.click(screen.getByRole('combobox', { name: 'Country' }));
        // A single fireEvent (rather than per-keystroke typing) sets the
        // whole term in one render pass — the collection dropping to zero
        // items mid-sequence otherwise races Radix's own focus-management
        // effect for the (now nonexistent) item list under jsdom.
        fireEvent.change(screen.getByRole('textbox', { name: /filter options/i }), { target: { value: 'zzz' } });

        expect(screen.getByText('No matches')).toBeInTheDocument();
    });

    it('still selects a filtered-to item and reports its value', async () => {
        const onChange = vi.fn();
        const user = userEvent.setup();
        render(<Harness onChange={onChange} />);

        await user.click(screen.getByRole('combobox', { name: 'Country' }));
        await user.type(screen.getByRole('textbox', { name: /filter options/i }), 'ugan');
        await user.click(screen.getByRole('option', { name: 'Uganda' }));

        expect(onChange).toHaveBeenCalledWith('uganda');
    });
});
