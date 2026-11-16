<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes a daybook save safe to retry on a slow connection.
 *
 * The lab's link is slow enough that a save can land on the server while the
 * tablet gives up waiting for the answer. Tapping save again would then record
 * the same line twice. The form sends an `entry_token` it keeps until a save
 * is confirmed; the first request carrying a token does the work, and any
 * later one with the same token is answered as saved without doing it again.
 *
 * A token is `saving` while its first request runs and `saved` only once that
 * request succeeded. A retry that arrives in between waits for the outcome
 * rather than promising one: it is answered as saved if the first request
 * made it, does the work itself if the first one failed, and is told to try
 * again (409, the form keeps what was typed) if the first is still running.
 *
 * A save that failed (validation, or an exception) releases its token, so the
 * corrected form goes through on the next attempt.
 */
class SaveDaybookEntryOnce
{
    private const SAVING = 'saving';

    private const SAVED = 'saved';

    /** How long a retry waits for the first request: 16 × 500ms. */
    private const WAIT_ATTEMPTS = 16;

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('entry_token');

        if (! is_string($token) || $token === '' || strlen($token) > 100) {
            return $next($request);
        }

        $key = 'daybook-entry:'.$request->user()?->id.':'.$token;

        // `saving` expires on its own: a request that died without releasing
        // its token must not lock the line out for a day.
        for ($attempt = 0; ! Cache::add($key, self::SAVING, now()->addMinutes(2)); $attempt++) {
            $state = Cache::get($key);

            if ($state !== null && $state !== self::SAVING) {
                return redirect()->back(fallback: route('daybook.index'))
                    ->with('success', 'تم الحفظ');
            }

            abort_if($attempt >= self::WAIT_ATTEMPTS, 409, 'ما زال الحفظ السابق جارياً.');

            Sleep::for(500)->milliseconds();
        }

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            Cache::forget($key);

            throw $e;
        }

        $failed = ! empty($response->exception)
            || $response->getStatusCode() >= 400
            || $request->session()->has('errors');

        if ($failed) {
            Cache::forget($key);
        } else {
            Cache::put($key, self::SAVED, now()->addDay());
        }

        return $response;
    }
}
