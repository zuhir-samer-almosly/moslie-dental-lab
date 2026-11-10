import { useEffect } from 'react';

const WEIGHTS = [400, 500, 600, 700];

/**
 * Tell headless Chromium when a print page is safe to turn into a PDF.
 *
 * Web fonts load lazily — only once layout finds text that needs them — so
 * "the network went quiet" is not the same as "the fonts are in". A PDF taken
 * in that gap prints in the fallback face. This asks for every weight the
 * documents use, in both scripts (each is a separate file), and only then
 * sets `data-print-ready` on <html>, which is what the controllers'
 * Browsershot `waitForFunction` polls for.
 *
 * The flag is set even if a font fails: a PDF in a fallback face beats a
 * request that hangs until it times out.
 *
 * Also forces the light theme — no appearance cookie is sent by the headless
 * browser, so the page would otherwise follow the renderer's system
 * preference. Paper is always light.
 */
export function usePrintReady() {
    useEffect(() => {
        document.documentElement.classList.remove('dark');

        Promise.all(
            WEIGHTS.map((weight) =>
                document.fonts.load(
                    `${weight} 16px "IBM Plex Sans Arabic"`,
                    'ا a',
                ),
            ),
        )
            .then(() => document.fonts.ready)
            .catch(() => undefined)
            .finally(() => {
                document.documentElement.dataset.printReady = 'true';
            });
    }, []);
}
