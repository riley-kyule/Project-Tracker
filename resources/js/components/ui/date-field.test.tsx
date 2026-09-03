import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DateField } from './date-field';

function Harness({ onChange, initial = '' }: { onChange: (v: string) => void; initial?: string }) {
    const [value, setValue] = useState(initial);
    return (
        <DateField
            aria-label="Due date"
            value={value}
            onChange={(v) => {
                setValue(v);
                onChange(v);
            }}
        />
    );
}

describe('DateField', () => {
    it('emits YYYY-MM-DD after typing dd/mm/yyyy and blurring', async () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);
        const input = screen.getByLabelText('Due date');

        await userEvent.type(input, '05092026');
        expect(input).toHaveValue('05/09/2026'); // slashes auto-inserted
        await userEvent.tab();

        expect(onChange).toHaveBeenLastCalledWith('2026-09-05');
    });

    it('emits an empty string when cleared', async () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} initial="2026-09-05" />);
        const input = screen.getByLabelText('Due date');
        expect(input).toHaveValue('05/09/2026');

        await userEvent.clear(input);
        await userEvent.tab();

        expect(onChange).toHaveBeenLastCalledWith('');
    });

    it('does not emit for an unparseable date and marks itself invalid', async () => {
        const onChange = vi.fn();
        render(<Harness onChange={onChange} />);
        const input = screen.getByLabelText('Due date');

        await userEvent.type(input, '12/34/2026');
        await userEvent.tab();

        expect(onChange).not.toHaveBeenCalled();
        expect(input).toHaveAttribute('aria-invalid', 'true');
    });

    it('reflects an externally changed value', async () => {
        function External() {
            const [value, setValue] = useState('2026-01-01');
            return (
                <>
                    <button onClick={() => setValue('2026-12-25')}>set</button>
                    <DateField aria-label="Due date" value={value} onChange={() => {}} />
                </>
            );
        }
        render(<External />);
        expect(screen.getByLabelText('Due date')).toHaveValue('01/01/2026');

        await userEvent.click(screen.getByText('set'));
        expect(screen.getByLabelText('Due date')).toHaveValue('25/12/2026');
    });
});
