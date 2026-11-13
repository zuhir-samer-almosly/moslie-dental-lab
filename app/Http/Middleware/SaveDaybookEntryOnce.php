<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
 * A save that failed (validation, or an exception) releases its token, so the
 * corrected form goes through on the next attempt.
 */
class SaveDaybookEntryOnce
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->input('entry_token');

        if (! is_string($token) || $token === '' || strlen($token) > 100) {
            return $next($request);
        }

        $key = 'daybook-entry:'.$request->user()?->id.':'.$token;

        if (! Cache::add($key, true, now()->addDay())) {
            return redirect()->back(fallback: route('daybook.index'))
                ->with('success', 'تم الحفظ');
        }

        $response = $next($request);

        $failed = ! empty($response->exception)
            || $response->getStatusCode() >= 400
            || $request->session()->has('errors');

        if ($failed) {
            Cache::forget($key);
        }

        return $response;
    }
}
