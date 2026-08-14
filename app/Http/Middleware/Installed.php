<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Installed
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing') || ! config('installer.enabled', false)) {
            if ($request->is('install*')) {
                return redirect('/');
            }

            return $next($request);
        }

        if (! file_exists(config('installer.lock_file', storage_path('installed')))) {
            if ($request->is('install*')) {
                return $next($request);
            }

            return redirect('/install');
        }

        if ($request->is('install*')) {
            return redirect('/');
        }

        return $next($request);
    }
}
