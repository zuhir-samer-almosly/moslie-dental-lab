import { Head, router, useForm } from '@inertiajs/react';
import { FileDown, Printer } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { InvoiceReport, type InvoiceData } from '@/components/invoice-report';
import { Button } from '@/components/ui/button';
import { DateInput } from '@/components/ui/date-input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الفواتير',
        href: '/invoices',
    },
];

export default function InvoicesIndex(props: InvoiceData) {
    const { orders, filters } = props;

    const { data, setData } = useForm({
        from: filters.from || '',
        to: filters.to || '',
        dentist_id: filters.dentist_id || '',
    });

    const [downloading, setDownloading] = useState(false);
    const [downloadError, setDownloadError] = useState<string | null>(null);

    const handleView = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/invoices', data as Record<string, string>);
    };

    const handlePrint = () => {
        window.print();
    };

    /**
     * The browser's own print dialog decides the paper orientation, and CSS
     * can't set it — printing straight from the page comes out sideways on a
     * portrait sheet. This asks the server to render the same report through
     * headless Chromium with landscape forced, so the file is always right.
     *
     * Fetched as a blob rather than navigated to, so the button can show real
     * progress (the render takes a few seconds) and surface failures instead
     * of dumping an error page over the report.
     */
    const handleDownloadPdf = async () => {
        setDownloading(true);
        setDownloadError(null);

        try {
            const params = new URLSearchParams(
                Object.entries(data).filter(([, value]) => value !== ''),
            );
            const response = await fetch(`/invoices/pdf?${params}`, {
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const blob = await response.blob();
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `فاتورة-${data.from}-${data.to}.pdf`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        } catch {
            setDownloadError('تعذّر إنشاء ملف PDF. حاول مرة أخرى.');
        } finally {
            setDownloading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الفواتير" />

            <div className="print-invoice flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4 md:p-6 print:overflow-visible">
                {/* Header */}
                <div className="space-y-1 print:hidden">
                    <h1 className="text-2xl font-bold tracking-tight">
                        الفواتير
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        إنشاء تقارير الطلبات والمدفوعات حسب الفترة والطبيب
                    </p>
                </div>

                {/* Filter Form - Hidden on print */}
                <form
                    onSubmit={handleView}
                    className="space-y-4 rounded-xl border bg-card p-5 text-card-foreground shadow-sm print:hidden"
                >
                    <Heading variant="small" title="تصفية البيانات" />

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="from">من تاريخ</Label>
                            <DateInput
                                id="from"
                                value={data.from}
                                onChange={(value) => setData('from', value)}
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="to">إلى تاريخ</Label>
                            <DateInput
                                id="to"
                                value={data.to}
                                onChange={(value) => setData('to', value)}
                                required
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="dentist_id">الطبيب (اختياري)</Label>
                            <div className="flex gap-2">
                                <Select
                                    value={data.dentist_id || undefined}
                                    onValueChange={(value) =>
                                        setData('dentist_id', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="الكل" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {props.dentists.map((dentist) => (
                                            <SelectItem
                                                key={dentist.id}
                                                value={dentist.id.toString()}
                                            >
                                                {dentist.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {data.dentist_id && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setData('dentist_id', '')
                                        }
                                    >
                                        مسح
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit">عرض</Button>
                        {orders && (
                            <>
                                <Button
                                    type="button"
                                    onClick={handleDownloadPdf}
                                    disabled={downloading}
                                    variant="outline"
                                >
                                    <FileDown className="h-4 w-4" />
                                    {downloading
                                        ? 'جارٍ الإنشاء…'
                                        : 'تحميل PDF'}
                                </Button>
                                <Button
                                    type="button"
                                    onClick={handlePrint}
                                    variant="outline"
                                >
                                    <Printer className="h-4 w-4" />
                                    طباعة
                                </Button>
                            </>
                        )}
                        {downloadError && (
                            <span className="text-sm text-destructive">
                                {downloadError}
                            </span>
                        )}
                    </div>
                </form>

                <InvoiceReport {...props} />
            </div>
        </AppLayout>
    );
}
