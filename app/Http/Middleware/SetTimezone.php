<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SetTimezone
{
    /**
     * Apply the stored timezone setting on every web request.
     * This changes:
     *  - config('app.timezone')         → used by Carbon / Laravel internals
     *  - date_default_timezone_set()    → used by PHP date/time functions
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $tz = setting('timezone', config('app.timezone', 'UTC'));

            // Validate it is a real PHP timezone identifier
            if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
                Config::set('app.timezone', $tz);
                date_default_timezone_set($tz);
            }
        } catch (\Throwable $e) {
            // Silently fall back to UTC — DB may not be ready
        }

        return $next($request);
    }
}
