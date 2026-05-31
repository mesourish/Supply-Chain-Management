<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class FixSubfolderIntendedUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && Session::has('url.intended')) {
            $intended = Session::get('url.intended');
            $appUrl = config('app.url');

            // Parse the app URL to extract the subfolder path if any
            if ($appUrl) {
                $appPath = parse_url($appUrl, PHP_URL_PATH);
                $appPath = rtrim($appPath, '/'); // Remove any trailing slash

                if (!empty($appPath) && $appPath !== '/') {
                    $parsedIntended = parse_url($intended);

                    if (isset($parsedIntended['path'])) {
                        $intendedPath = $parsedIntended['path'];

                        // Check if the intended path is not already prefixed with the app's base path
                        if (!str_starts_with($intendedPath, $appPath . '/') && $intendedPath !== $appPath) {
                            $newPath = $appPath . '/' . ltrim($intendedPath, '/');

                            $scheme = isset($parsedIntended['scheme']) ? $parsedIntended['scheme'] . '://' : '';
                            $host = isset($parsedIntended['host']) ? $parsedIntended['host'] : '';
                            $port = isset($parsedIntended['port']) ? ':' . $parsedIntended['port'] : '';
                            $query = isset($parsedIntended['query']) ? '?' . $parsedIntended['query'] : '';
                            $fragment = isset($parsedIntended['fragment']) ? '#' . $parsedIntended['fragment'] : '';

                            $fixedIntended = $scheme . $host . $port . $newPath . $query . $fragment;
                            Session::put('url.intended', $fixedIntended);
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
