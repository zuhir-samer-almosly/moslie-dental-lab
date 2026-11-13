<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;
use Spatie\Browsershot\Browsershot;

/**
 * Turns one of the app's print pages into an A4 PDF with headless Chromium.
 *
 * Every PDF here is made the same way: the print page lives on a `signed`
 * route outside the auth group (the browser has no session), a short-lived
 * relative signed URL is minted for it, and Chromium is pointed at that URL on
 * the container-internal host — nginx and php-fpm share the container, so
 * there is no reason to go back out through the public domain.
 */
class PdfRenderer
{
    /**
     * @param  string  $route  name of the signed print-view route
     * @param  array<string, mixed>  $parameters  route and query parameters; nulls are dropped
     * @param  int  $margin  millimetres. Keep equal to the page's `@page pdf-*`
     *                       margin in app.css, which is the one that actually
     *                       takes effect — a CSS @page margin overrides this.
     */
    public static function render(string $route, array $parameters, bool $landscape = false, int $margin = 15): string
    {
        $path = URL::temporarySignedRoute(
            $route,
            now()->addMinutes(config('pdf.signed_url_ttl')),
            array_filter($parameters, fn ($value) => $value !== null),
            absolute: false,
        );

        $browsershot = Browsershot::url(rtrim(config('pdf.internal_url'), '/').$path)
            ->noSandbox() // the container runs as root
            // Chromium's default /dev/shm is tiny in Docker and it crashes
            // mid-render without this.
            ->addChromiumArguments(['disable-dev-shm-usage'])
            // Apply the tuned @media print rules from app.css, so the PDF and
            // the browser's own print preview render identically.
            ->emulateMedia('print')
            ->format('A4')
            ->margins($margin, $margin, $margin, $margin)
            ->showBackground()
            ->waitUntilNetworkIdle(strict: false)
            // Network-idle is not font-loaded: web fonts are only requested
            // once layout needs them. The print page raises this flag when
            // they are in (see resources/js/hooks/use-print-ready.ts).
            ->waitForFunction('document.documentElement.dataset.printReady === "true"')
            ->timeout(120);

        if ($landscape) {
            $browsershot->landscape();
        }

        if ($chromePath = config('pdf.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        if ($node = config('pdf.node_binary')) {
            $browsershot->setNodeBinary($node);
        }

        if ($npm = config('pdf.npm_binary')) {
            $browsershot->setNpmBinary($npm);
        }

        return $browsershot->pdf();
    }
}
