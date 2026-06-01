import { Monitor, Moon, Sun } from 'lucide-react';
import type { ComponentProps } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/lib/translations';
import { cn } from '@/lib/utils';

type AppearanceMenuProps = {
    className?: string;
    align?: ComponentProps<typeof DropdownMenuContent>['align'];
};

export default function AppearanceMenu({
    className,
    align = 'end',
}: AppearanceMenuProps) {
    const { t } = useTranslation();
    const { appearance, updateAppearance } = useAppearance();
    const ActiveIcon =
        appearance === 'dark' ? Moon : appearance === 'light' ? Sun : Monitor;

    const appearanceOptions: {
        value: Appearance;
        label: string;
        icon: typeof Sun;
    }[] = [
        { value: 'light', label: t('menu.light'), icon: Sun },
        { value: 'dark', label: t('menu.dark'), icon: Moon },
        { value: 'system', label: t('menu.system'), icon: Monitor },
    ];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn('h-9 w-9', className)}
                    aria-label="Toggle theme"
                >
                    <ActiveIcon className="size-5" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align={align} className="w-40">
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) =>
                        updateAppearance(value as Appearance)
                    }
                >
                    {appearanceOptions.map(({ value, label, icon: Icon }) => (
                        <DropdownMenuRadioItem key={value} value={value}>
                            <Icon className="size-4" />
                            <span>{label}</span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
