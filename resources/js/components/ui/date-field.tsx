import { cn, parseDmy, toDmy } from '@/lib/utils';
import { Calendar } from 'lucide-react';
import * as React from 'react';

/**
 * Drop-in replacement for <Input type="date">. Always shows/accepts DD/MM/YYYY
 * regardless of browser locale; the calendar button opens the OS date picker.
 * Value contract is unchanged: a 'YYYY-MM-DD' string, or '' when unset.
 */
export type DateFieldProps = {
    value: string;
    onChange: (isoDate: string) => void;
    id?: string;
    name?: string;
    disabled?: boolean;
    required?: boolean;
    min?: string;
    max?: string;
    className?: string;
    placeholder?: string;
    'aria-label'?: string;
    'aria-labelledby'?: string;
};

function maskDmy(raw: string): string {
    const digits = raw.replace(/\D/g, '').slice(0, 8);
    const parts = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter((part) => part.length > 0);
    return parts.join('/');
}

export function DateField({
    value,
    onChange,
    id,
    name,
    disabled,
    required,
    min,
    max,
    className,
    placeholder = 'dd/mm/yyyy',
    ...aria
}: DateFieldProps) {
    const [draft, setDraft] = React.useState(() => toDmy(value));
    const [focused, setFocused] = React.useState(false);
    const pickerRef = React.useRef<HTMLInputElement>(null);

    // Resync only when `value` changes to something the current text doesn't
    // already represent (form reset, server round-trip, the calendar picker) —
    // never while the person is mid-type, and never clobber an invalid draft
    // they haven't corrected yet.
    React.useEffect(() => {
        if (!focused && parseDmy(draft) !== value && !(draft.trim() === '' && value === '')) {
            setDraft(toDmy(value));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    const commit = (text: string) => {
        const trimmed = text.trim();
        if (trimmed === '') {
            onChange('');
            return;
        }
        const iso = parseDmy(trimmed);
        if (iso !== null && iso !== value) onChange(iso);
    };

    const invalid = draft.trim() !== '' && parseDmy(draft) === null;

    const openPicker = () => {
        const el = pickerRef.current;
        if (!el) return;
        if (typeof el.showPicker === 'function') {
            try {
                el.showPicker();
                return;
            } catch {
                /* not allowed in this context — fall through */
            }
        }
        el.focus();
        el.click();
    };

    return (
        <div
            className={cn(
                'flex h-10 w-full items-center rounded-md border border-input bg-background text-base ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 md:text-sm',
                invalid && 'border-destructive focus-within:ring-destructive',
                disabled && 'cursor-not-allowed opacity-50',
                className,
            )}
        >
            <input
                id={id}
                name={name}
                type="text"
                inputMode="numeric"
                autoComplete="off"
                disabled={disabled}
                required={required}
                placeholder={placeholder}
                aria-invalid={invalid || undefined}
                {...aria}
                value={draft}
                onChange={(e) => setDraft(maskDmy(e.target.value))}
                onFocus={() => setFocused(true)}
                onBlur={() => {
                    setFocused(false);
                    commit(draft);
                }}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') commit(draft);
                }}
                className="h-full w-full bg-transparent px-3 py-2 placeholder:text-muted-foreground focus-visible:outline-hidden disabled:cursor-not-allowed"
            />
            <button
                type="button"
                tabIndex={-1}
                disabled={disabled}
                onClick={openPicker}
                aria-label="Open calendar"
                className="text-muted-foreground hover:text-foreground flex h-full shrink-0 items-center px-2.5 disabled:cursor-not-allowed"
            >
                <Calendar className="size-4" />
            </button>
            <input
                ref={pickerRef}
                type="date"
                tabIndex={-1}
                aria-hidden="true"
                disabled={disabled}
                min={min}
                max={max}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="sr-only"
            />
        </div>
    );
}
