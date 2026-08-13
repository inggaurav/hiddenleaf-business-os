<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $submitted = $request->header('X-Request-ID');
        $requestId = is_string($submitted) && Str::isUuid($submitted)
            ? strtolower($submitted)
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        app()->instance('request_id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        try {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } finally {
            Log::withoutContext(['request_id']);
            app()->forgetInstance('request_id');
        }
    }
}
