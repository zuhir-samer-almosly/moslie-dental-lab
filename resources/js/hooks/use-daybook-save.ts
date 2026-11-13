import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

const newToken = (): string =>
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(36).slice(2)}`;

/**
 * Saving one daybook line over a slow connection.
 *
 * Three outcomes, kept apart because the user does something different after
 * each: saved (the form clears), rejected by validation (`errors`, fix and
 * resend), and never answered (`failed` — the form keeps what was typed and
 * the same save can simply be sent again).
 *
 * Resending is safe: every save carries an `entry_token` that only changes
 * once a save is confirmed, and the server records a token once (see
 * SaveDaybookEntryOnce). So a save that did land, but whose answer was lost,
 * is not recorded a second time by the retry.
 */
export function useDaybookSave() {
    const [processing, setProcessing] = useState(false);
    const [failed, setFailed] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const token = useRef(newToken());

    const save = (
        method: 'post' | 'put',
        url: string,
        payload: Record<string, unknown>,
        onSaved: () => void,
    ) => {
        let saved = false;
        let rejected = false;

        setProcessing(true);
        setFailed(false);
        setErrors({});

        router.visit(url, {
            method,
            // The page serialises this; its values are plain JSON.
            data: { ...payload, entry_token: token.current } as never,
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                saved = true;
                token.current = newToken();
                onSaved();
            },
            onError: (validation) => {
                rejected = true;
                setErrors(validation);
            },
            onFinish: () => {
                setProcessing(false);
                if (!saved && !rejected) {
                    setFailed(true);
                }
            },
        });
    };

    const reset = () => {
        setFailed(false);
        setErrors({});
    };

    return { save, processing, failed, errors, reset };
}
