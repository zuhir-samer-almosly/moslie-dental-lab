import { Keyboard } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

const SHORTCUTS: { keys: string; label: string; where: string }[] = [
    { keys: 'Ctrl K', label: 'بحث عن طبيب أو طلب', where: 'في أي صفحة' },
    { keys: 'Enter', label: 'سطر جديد', where: 'نموذج الطلب' },
    { keys: 'Ctrl Enter', label: 'حفظ الطلب', where: 'نموذج الطلب' },
];

/**
 * The one place keyboard shortcuts are listed, so the pages themselves stay
 * free of key hints. Add a row here when a new shortcut is introduced.
 */
export function ShortcutsHelp() {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    title="اختصارات لوحة المفاتيح"
                    aria-label="اختصارات لوحة المفاتيح"
                    className="size-9 shrink-0 text-muted-foreground"
                >
                    <Keyboard className="size-[18px]" />
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md [&>button:last-child]:right-auto [&>button:last-child]:left-4">
                <DialogHeader className="text-right sm:text-right">
                    <DialogTitle>اختصارات لوحة المفاتيح</DialogTitle>
                    <DialogDescription>
                        طرق أسرع للعمل دون استخدام الفأرة.
                    </DialogDescription>
                </DialogHeader>
                <ul className="divide-y">
                    {SHORTCUTS.map((shortcut) => (
                        <li
                            key={shortcut.keys}
                            className="flex items-center justify-between gap-4 py-3"
                        >
                            <span className="grid gap-0.5">
                                <span className="text-sm font-medium">
                                    {shortcut.label}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {shortcut.where}
                                </span>
                            </span>
                            <kbd
                                dir="ltr"
                                className="rounded-md border bg-muted px-2 py-1 font-sans text-xs"
                            >
                                {shortcut.keys}
                            </kbd>
                        </li>
                    ))}
                </ul>
            </DialogContent>
        </Dialog>
    );
}
