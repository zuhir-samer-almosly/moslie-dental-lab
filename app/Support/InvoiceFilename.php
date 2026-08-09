<?php

namespace App\Support;

use App\Models\Dentist;

/**
 * The name the invoice PDF downloads as.
 *
 * Filtered to a single dentist, the file names itself after them — these get
 * forwarded straight to the doctor, so a name you would fix by hand is a name
 * you would fix every month.
 *
 * `resources/js/pages/invoices/index.tsx` mirrors this format: it sets
 * `link.download` on the fetched blob, and that is the name the user actually
 * sees. This copy covers a direct hit on /invoices/pdf, and the two must stay
 * in step.
 */
class InvoiceFilename
{
    public static function for(?Dentist $dentist, string $from, string $to): string
    {
        $generic = "فاتورة-{$from}-{$to}.pdf";

        if (! $dentist) {
            return $generic;
        }

        $name = self::sanitize($dentist->name);

        if ($name === '') {
            return $generic;
        }

        [$title, $respect] = $dentist->gender === 'female'
            ? ['الدكتورة', 'المحترمة']
            : ['الدكتور', 'المحترم'];

        return "{$title} {$name} {$respect} {$from} - {$to}.pdf";
    }

    /**
     * Drop the characters that break a filename on the platforms these
     * invoices land on, then collapse the whitespace that removing them
     * leaves behind.
     */
    private static function sanitize(string $name): string
    {
        $stripped = preg_replace('#[/\\\\:*?"<>|\x00-\x1F]+#u', ' ', $name) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');
    }
}
