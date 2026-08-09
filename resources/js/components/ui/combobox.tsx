import {
    Combobox as HeadlessCombobox,
    ComboboxButton,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
} from '@headlessui/react';
import { ChevronDownIcon, XIcon } from 'lucide-react';
import { useState } from 'react';

import { cn } from '@/lib/utils';

export type ComboboxOptionItem<T> = {
    value: T;
    label: string;
};

type ComboboxProps<T> = {
    value: T | null;
    onChange: (value: T | null) => void;
    options: ComboboxOptionItem<T>[];
    placeholder?: string;
    emptyMessage?: string;
    /** Shows an X inside the field that resets the value to null. */
    clearable?: boolean;
    /** Keeps whatever was typed as the value when nothing matches. */
    allowCustom?: boolean;
    /** Rendered under the option list when allowCustom is on and nothing matched. */
    customHint?: (query: string) => string;
    disabled?: boolean;
    id?: string;
    className?: string;
    'aria-invalid'?: boolean;
};

const TASHKEEL = /[ً-ْـ]/g;

/**
 * Folds the Arabic spellings that differ only by hamza/alef form so typing
 * "احمد" still finds a dentist stored as "أحمد". Latin text is lowercased so
 * the same helper covers account codes and mixed-script names.
 */
function normalizeArabic(text: string): string {
    return text
        .replace(TASHKEEL, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ؤ/g, 'و')
        .replace(/ئ/g, 'ي')
        .replace(/ة/g, 'ه')
        .toLowerCase();
}

/**
 * The app's only dropdown. Searchable, RTL-aware and keyboard navigable —
 * arrow keys, Enter and Escape come from Headless UI's Combobox.
 */
export function Combobox<T extends string | number>({
    value,
    onChange,
    options,
    placeholder,
    emptyMessage = 'لا توجد نتائج',
    clearable = false,
    allowCustom = false,
    customHint,
    disabled = false,
    id,
    className,
    'aria-invalid': ariaInvalid,
}: ComboboxProps<T>) {
    // Filter by what was typed since the list opened, not the stored value, so
    // clicking an already-filled field still shows every option.
    const [query, setQuery] = useState('');
    const trimmed = query.trim();
    const needle = normalizeArabic(trimmed);
    const filtered = needle
        ? options.filter((option) =>
              normalizeArabic(option.label).includes(needle),
          )
        : options;

    const selected = options.find((option) => option.value === value) ?? null;

    // Without a matching option, a custom field still has to show what the
    // form is actually holding; a strict field shows nothing.
    const displayValue = (current: T | null) => {
        if (current === null || current === '') {
            return '';
        }

        const match = options.find((option) => option.value === current);

        if (match) {
            return match.label;
        }

        return allowCustom ? String(current) : '';
    };

    const showClear = clearable && !disabled && value !== null && value !== '';

    return (
        <HeadlessCombobox
            value={value}
            onChange={(next: T | null) => {
                // Headless UI reports null when Enter is pressed on a query
                // that matched nothing. Only a clearable field may act on it —
                // elsewhere a stray Enter must not wipe the current selection,
                // and a custom field already stored the text as it was typed.
                if (next === null) {
                    if (clearable) {
                        onChange(null);
                    }

                    return;
                }

                onChange(next);
            }}
            onClose={() => setQuery('')}
            disabled={disabled}
            immediate
        >
            <div className="relative">
                <ComboboxInput
                    id={id}
                    aria-invalid={ariaInvalid}
                    className={cn(
                        'flex h-9 w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground md:text-sm',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
                        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                        showClear ? 'pe-14' : 'pe-8',
                        className,
                    )}
                    displayValue={displayValue}
                    onChange={(e) => {
                        setQuery(e.target.value);

                        if (allowCustom) {
                            onChange(e.target.value as T);
                        }
                    }}
                    placeholder={placeholder}
                />
                {showClear && (
                    <button
                        type="button"
                        tabIndex={-1}
                        aria-label="مسح"
                        onClick={() => {
                            setQuery('');
                            onChange(null);
                        }}
                        className="absolute inset-y-0 end-8 flex items-center px-1 text-muted-foreground hover:text-foreground"
                    >
                        <XIcon className="size-4" />
                    </button>
                )}
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
                        key={String(option.value)}
                        value={option.value}
                        className={cn(
                            'flex w-full cursor-default items-center rounded-sm px-2 py-1.5 text-sm select-none',
                            'data-focus:bg-accent data-focus:text-accent-foreground',
                            selected?.value === option.value && 'font-medium',
                        )}
                    >
                        {option.label}
                    </ComboboxOption>
                ))}
                {filtered.length === 0 && (
                    <div className="px-2 py-1.5 text-sm text-muted-foreground">
                        {allowCustom && trimmed !== '' && customHint
                            ? customHint(trimmed)
                            : emptyMessage}
                    </div>
                )}
            </ComboboxOptions>
        </HeadlessCombobox>
    );
}
