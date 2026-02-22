<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AddCorrelationId
{
    /**
     * Handle an incoming request.
     * Generates or forwards correlation ID for request tracing
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get correlation ID from request header, or generate new one
        $correlationId = $request->header('X-Request-ID') 
            ?: $request->header('X-Correlation-ID')
            ?: 'req-' . Str::random(8);

        // Add to request for use in controllers/logging
        $request->headers->set('X-Request-ID', $correlationId);
        $request->merge(['correlation_id' => $correlationId]);

        // Process request
        $response = $next($request);

        // Add correlation ID to response headers
        $response->headers->set('X-Request-ID', $correlationId);

        return $response;
    }
}





















