<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPortalUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! in_array($user->role, ['client', 'customer', 'vendor'], true)) {
            return $next($request);
        }

        $route = (string) optional($request->route())->getName();
        $allowed = str_starts_with($route, 'portal.')
            || str_starts_with($route, 'profile.')
            || str_starts_with($route, 'verification.')
            || in_array($route, ['logout', 'workspaces.switch', 'notifications.index', 'notifications.read', 'notifications.read-all'], true);

        if ($allowed) {
            return $next($request);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return redirect()->route('portal.dashboard');
        }

        abort(403, 'Portal users may only mutate their own portal records.');
    }
}
