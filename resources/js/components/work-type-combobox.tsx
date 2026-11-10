import { Combobox } from '@/components/ui/combobox';

export type WorkTypeOption = {
    name: string;
    /** The dentist's price for it, already formatted — absent if he has none. */
    price?: string;
};

type WorkTypeComboboxProps = {
    value: string;
    onChange: (value: string) => void;
    options: WorkTypeOption[];
    placeholder?: string;
};

/**
 * Free-text autocomplete for the order item work type: typing filters the
 * known names (the dentist's priced ones first), but any typed word is kept
 * as-is when nothing matches — no "other" escape option needed.
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
                value: option.name,
                label: option.name,
                hint: option.price,
            }))}
            placeholder={placeholder}
            allowCustom
            customHint={(query) => `سيتم استخدام «${query}» كنوع جديد`}
        />
    );
}
