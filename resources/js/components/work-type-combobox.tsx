import {
    Combobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';

import { cn } from '@/lib/utils';

type WorkTypeComboboxProps = {
    value: string;
    onChange: (value: string) => void;
    options: string[];
    placeholder?: string;
};

/**
 * Free-text autocomplete for the order item work type: typing filters the
 * dentist's price-list entries, but any typed word is kept as-is when
 * nothing matches — no "other" escape option needed.
 */
export default function WorkTypeCombobox({
    value,
    onChange,
    options,
    placeholder,
}: WorkTypeComboboxProps) {
    // Filter by what was typed since the list opened, not the stored value,
    // so clicking an already-filled field still shows every option.
    const [query, setQuery] = useState('');
    const trimmed = query.trim();
    const filtered = trimmed
        ? options.filter((option) => option.includes(trimmed))
        : options;

    return (
        <Combobox
            value={value}
            onChange={(selected: string | null) => {
                if (selected !== null) {
                    onChange(selected);
                }
            }}
            onClose={() => setQuery('')}
            immediate
        >
            <div className="relative">
                <ComboboxInput
                    className={cn(
                        'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 pe-8 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground md:text-sm',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                    )}
                    displayValue={(current: string) => current}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        onChange(e.target.value);
                    }}
                    placeholder={placeholder}
                />
                <ComboboxButton className="absolute inset-y-0 end-0 flex items-center pe-3">
                    <ChevronDownIcon className="size-4 text-muted-foreground opacity-50" />
                </ComboboxButton>
            </div>
            <ComboboxOptions
                anchor="bottom"
                className="z-50 max-h-60 w-[var(--input-width)] overflow-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md [--anchor-gap:4px] empty:invisible"
            >
                {filtered.map((option) => (
                    <ComboboxOption
                        key={option}
                        value={option}
                        className="flex w-full cursor-default items-center rounded-sm px-2 py-1.5 text-sm select-none data-focus:bg-accent data-focus:text-accent-foreground"
                    >
                        {option}
                    </ComboboxOption>
                ))}
                {filtered.length === 0 && trimmed !== '' && (
                    <div className="px-2 py-1.5 text-sm text-muted-foreground">
                        سيتم استخدام &laquo;{trimmed}&raquo; كنوع مخصص
                    </div>
                )}
            </ComboboxOptions>
        </Combobox>
    );
}
