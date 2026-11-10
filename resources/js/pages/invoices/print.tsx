import { Head } from '@inertiajs/react';
import { InvoiceReport, type InvoiceData } from '@/components/invoice-report';
import { usePrintReady } from '@/hooks/use-print-ready';

/**
 * The bare invoice document, with no app chrome around it — this is the page
 * headless Chromium loads to produce the PDF (see InvoiceController::pdf).
 *
 * It deliberately skips AppLayout: the sidebar and header are noise on paper,
 * and the layout reads `auth.user`, which is null on the signed, unauthenticated
 * route this page is served from.
 *
 * Wrapped in <main> so the existing `@media print` rules in app.css (table
 * borders, centered headings) apply exactly as they do when printing from the
 * browser. `pdf-invoice` swaps the one rule that must NOT carry over: the
 * page margin. In the browser it is faked with padding on <main>, which only
 * exists at the very top and very bottom of the document — every page break
 * in between ran edge to edge. Nobody's print dialog is involved here, so
 * this page binds to a named `@page` with a real margin that repeats on every
 * sheet (see app.css).
 */
export default function InvoicesPrint(props: InvoiceData) {
    usePrintReady();

    return (
        <>
            <Head title="تقرير الفواتير" />
            <main className="pdf-invoice p-6">
                <InvoiceReport {...props} />
            </main>
        </>
    );
}
