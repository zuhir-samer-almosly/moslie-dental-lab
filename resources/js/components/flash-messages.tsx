import { usePage } from '@inertiajs/react';
import { CheckCircle2, CircleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Toast for the session flash shared by HandleInertiaRequests: green for
 * `success`, red for `error` (e.g. a blocked dentist/employee delete).
 * Auto-hides — errors linger longer — and can be dismissed manually.
 *
 * Visibility is derived: the toast shows while the current `flash` object
 * isn't the one that was dismissed. Each server response produces a new
 * object, so a repeated action re-shows the same message.
 */
export default function FlashMessages() {
    const { flash } = usePage().props;
    const [dismissed, setDismissed] = useState<typeof flash | null>(null);

    const message = flash?.error || flash?.success || '';
    const isError = Boolean(flash?.error);
    const visible = Boolean(message) && dismissed !== flash;

    useEffect(() => {
        if (!message) return;
        const timer = setTimeout(
            () => setDismissed(flash),
            isError ? 8000 : 4000,
        );
        return () => clearTimeout(timer);
    }, [flash, message, isError]);

    if (!visible) return null;

    return (
        <div className="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 print:hidden">
            <div
                role={isError ? 'alert' : 'status'}
                className={cn(
                    'flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-medium text-white shadow-lg',
                    isError ? 'bg-red-600' : 'bg-emerald-600',
                )}
            >
                {isError ? (
                    <CircleAlert className="size-4 shrink-0" />
                ) : (
                    <CheckCircle2 className="size-4 shrink-0" />
                )}
                <span>{message}</span>
                <button
                    type="button"
                    onClick={() => setDismissed(flash)}
                    className="ms-1 rounded p-0.5 transition-colors hover:bg-white/20"
                    aria-label="إغلاق"
                >
                    <X className="size-3.5" />
                </button>
            </div>
        </div>
    );
}
