import { usePage } from '@inertiajs/react';
import { CheckCircle2, CircleAlert, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Toast for the session flash shared by HandleInertiaRequests: green for
 * `success`, red for `error` (e.g. a blocked dentist/employee delete).
 * Auto-hides — errors linger longer — and can be dismissed manually.
 */
export default function FlashMessages() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(false);

    const message = flash?.error || flash?.success || '';
    const isError = Boolean(flash?.error);

    useEffect(() => {
        if (!message) return;
        setVisible(true);
        const timer = setTimeout(
            () => setVisible(false),
            isError ? 8000 : 4000,
        );
        return () => clearTimeout(timer);
        // `flash` is a new object on every server response, so the same
        // message re-shows when an action is repeated.
    }, [flash, message, isError]);

    if (!visible || !message) return null;

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
                    onClick={() => setVisible(false)}
                    className="ms-1 rounded p-0.5 transition-colors hover:bg-white/20"
                    aria-label="إغلاق"
                >
                    <X className="size-3.5" />
                </button>
            </div>
        </div>
    );
}
