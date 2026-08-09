import { Head } from '@inertiajs/react';
import { useEffect } from 'react';
import { InvoiceReport, type InvoiceData } from '@/components/invoice-report';

/**
 * The bare invoice document, with no app chrome around it — this is the page
 * headless Chromium loads to produce the PDF (see InvoiceController::pdf).
 *
 * It deliberately skips AppLayout: the sidebar and header are noise on paper,
 * and the layout reads `auth.user`, which is null on the signed, unauthenticated
 * route this page is served from.
 *
 * Wrapped in <main> so the existing `@media print` rules in app.css (page
 * padding, table borders, centered headings) apply exactly as they do when
 * printing from the browser — the PDF and the print preview stay in sync.
 */
export default function InvoicesPrint(props: InvoiceData) {
    // No appearance cookie is sent by the headless browser, so the page would
    // otherwise follow the renderer's system preference. Paper is always light.
    useEffect(() => {
        document.documentElement.classList.remove('dark');
    }, []);

    return (
        <>
            <Head title="تقرير الفواتير" />
            <main className="print-invoice p-6">
                <InvoiceReport {...props} />
            </main>
        </>
    );
}
