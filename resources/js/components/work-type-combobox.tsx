import { Combobox } from '@/components/ui/combobox';

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
    return (
        <Combobox
            value={value}
            onChange={(next) => onChange(next ?? '')}
            options={options.map((option) => ({
                value: option,
                label: option,
            }))}
            placeholder={placeholder}
            allowCustom
            customHint={(query) => `سيتم استخدام «${query}» كنوع مخصص`}
        />
    );
}
