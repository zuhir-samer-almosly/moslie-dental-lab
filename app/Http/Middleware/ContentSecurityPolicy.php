<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Content-Security-Policy, plus the header hygiene Caddy cannot do per-request.
 *
 * The app renders no user-supplied HTML (React escapes everything; the one
 * `dangerouslySetInnerHTML` is Fortify's own server-built QR SVG), so this is
 * defence in depth rather than a patch for a known hole: if a dependency ever
 * ships an injection, a nonce-based policy stops it from executing.
 *
 * `'unsafe-inline'` is deliberately NOT used for scripts. Vite::useCspNonce()
 * mints a per-response nonce and stamps it onto the tags @vite emits; the one
 * hand-written inline script in app.blade.php reads the same nonce. Browsers
 * that honour CSP Level 3 ignore `'unsafe-inline'` entirely once a nonce is
 * present, so adding it would only weaken the policy for older ones.
 *
 * Styles do keep `'unsafe-inline'`: Headless UI positions its popovers with
 * inline `style` attributes, which a nonce cannot cover.
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        // Must run BEFORE the view renders — @vite reads the nonce as it emits
        // the script and style tags.
        Vite::useCspNonce();

        $response = $next($request);

        if (! $this->shouldApply($request)) {
            return $response;
        }

        // The invoice/statement PDFs are rendered by a headless Chromium that
        // loads the page from BROWSERSHOT_INTERNAL_URL (127.0.0.1) while the
        // asset tags still point at APP_URL. `'self'` is the internal origin
        // there, so APP_URL has to be named explicitly or the PDF renders with
        // no JS and comes out blank.
        $app = rtrim((string) config('app.url'), '/');

        $policy = [
            "default-src 'self' {$app}",
            "script-src 'self' {$app} 'nonce-".Vite::cspNonce()."'",
            "style-src 'self' {$app} 'unsafe-inline' https://fonts.bunny.net",
            "font-src 'self' {$app} https://fonts.bunny.net data:",
            "img-src 'self' {$app} data: blob:",
            "connect-src 'self' {$app}",
            // Nothing in the app embeds or is embedded. `frame-ancestors`
            // supersedes X-Frame-Options on every browser that reads it.
            "frame-ancestors 'none'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            // Login and every mutation post to this origin only.
            "form-action 'self'",
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $policy));

        // Caddy sets the static headers; these two are request-scoped or
        // PHP-generated, so they belong here.
        //
        // X-Powered-By advertises the exact PHP patch level (it was leaking
        // "PHP/8.3.33"), which is a free shortlist of applicable CVEs for
        // anyone scanning. PHP emits it from expose_php in php.ini; stripping
        // it here covers the case where that ini is not ours to edit.
        $response->headers->remove('X-Powered-By');

        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');

        return $response;
    }

    /**
     * Skipped only for local development with Vite's dev server running: HMR
     * pulls its client and its websocket from the Vite origin, which no
     * production policy allows.
     *
     * Both halves of that condition are required. `public/hot` is written by
     * the dev-server container and removed when it stops cleanly — but a
     * crashed container leaves it behind, and a stale file must never be able
     * to silently switch the policy off on a production box. Deployments run
     * APP_ENV=production, so the environment check is the real gate and the
     * hot file only relaxes it locally.
     *
     * CSP_ENABLED=false (read through config('app.csp_enabled')) is the
     * escape hatch if a policy change ever breaks production before a fix can
     * ship.
     */
    private function shouldApply(Request $request): bool
    {
        if (! config('app.csp_enabled')) {
            return false;
        }

        return ! (app()->isLocal() && Vite::isRunningHot());
    }
}
