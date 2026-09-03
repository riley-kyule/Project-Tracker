import { cn } from '@/lib/utils';
import {
    Combobox as HuiCombobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useMemo, useState } from 'react';

export type ComboboxItem = { value: string; label: string; hint?: string };

type ComboboxProps = {
    value: string;
    onChange: (value: string) => void;
    options: ComboboxItem[];
    id?: string;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    emptyText?: string;
    'aria-label'?: string;
    'aria-labelledby'?: string;
};

/**
 * A single-select dropdown that filters as you type — for long lists (people,
 * anything with more than a handful of entries) where the plain <Select>'s
 * first-letter cycling isn't enough. Same value contract as <Select>: a string
 * `value` and `onChange(value)`. Options render inline (not portalled) so it
 * behaves inside a <Dialog>.
 */
export function Combobox({
    value,
    onChange,
    options,
    id,
    placeholder = 'Select…',
    disabled,
    className,
    emptyText = 'No matches',
    ...aria
}: ComboboxProps) {
    const [query, setQuery] = useState('');

    const selected = options.find((o) => o.value === value) ?? null;

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return options;
        return options.filter((o) => o.label.toLowerCase().includes(q) || (o.hint ?? '').toLowerCase().includes(q));
    }, [options, query]);

    return (
        <HuiCombobox
            value={value}
            onChange={(v: string | null) => onChange(v ?? '')}
            onClose={() => setQuery('')}
            disabled={disabled}
            immediate
        >
            <div className={cn('relative', className)}>
                <ComboboxInput
                    id={id}
                    aria-label={aria['aria-label']}
                    aria-labelledby={aria['aria-labelledby']}
                    autoComplete="off"
                    spellCheck={false}
                    className="border-input bg-background ring-offset-background placeholder:text-muted-foreground focus:ring-ring flex h-10 w-full items-center rounded-md border px-3 py-2 pr-9 text-sm focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:cursor-not-allowed disabled:opacity-50"
                    displayValue={() => selected?.label ?? ''}
                    placeholder={placeholder}
                    onChange={(e) => setQuery(e.target.value)}
                />
                <ComboboxButton className="absolute inset-y-0 right-0 flex items-center pr-2.5" aria-label="Toggle options">
                    <ChevronsUpDown className="h-4 w-4 opacity-50" />
                </ComboboxButton>
                <ComboboxOptions className="bg-popover text-popover-foreground absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border p-1 shadow-md">
                    {filtered.length === 0 ? (
                        <div className="text-muted-foreground px-2 py-1.5 text-sm">{emptyText}</div>
                    ) : (
                        filtered.map((o) => (
                            <ComboboxOption
                                key={o.value}
                                value={o.value}
                                className="data-[focus]:bg-accent data-[focus]:text-accent-foreground flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm"
                            >
                                {({ selected: isSelected }) => (
                                    <>
                                        <Check className={cn('h-4 w-4 shrink-0', isSelected ? 'opacity-100' : 'opacity-0')} />
                                        <span className="truncate">{o.label}</span>
                                        {o.hint && <span className="text-muted-foreground ml-auto truncate text-xs">{o.hint}</span>}
                                    </>
                                )}
                            </ComboboxOption>
                        ))
                    )}
                </ComboboxOptions>
            </div>
        </HuiCombobox>
    );
}
