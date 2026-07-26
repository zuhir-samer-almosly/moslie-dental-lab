<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Headless Chromium
    |--------------------------------------------------------------------------
    |
    | Browsershot drives a headless Chromium to render the invoice PDF. The
    | binary ships with the Docker image via `apk add chromium`; the package
    | name for the executable has moved between Alpine releases
    | (/usr/bin/chromium-browser on older ones, /usr/bin/chromium on newer),
    | so the path stays env-configurable rather than hardcoded.
    |
    | Leave `chrome_path` null outside Docker and Browsershot will fall back to
    | puppeteer's own bundled Chromium.
    |
    */

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),

    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),

    /*
    |--------------------------------------------------------------------------
    | Internal render URL
    |--------------------------------------------------------------------------
    |
    | Chromium runs inside the app container, where nginx and php-fpm sit
    | behind the same supervisor. Pointing it at the public APP_URL would send
    | the request back out through Caddy (and out to DNS) for no reason, so the
    | signed print URL is rewritten onto this host before rendering.
    |
    */

    'internal_url' => env('BROWSERSHOT_INTERNAL_URL', 'http://127.0.0.1'),

    /*
    | How long the signed print-view URL stays valid. It only has to survive
    | the round trip from PHP to Chromium and back.
    */

    'signed_url_ttl' => 2, // minutes

];
