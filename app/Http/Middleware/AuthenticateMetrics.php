<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMetrics
{
    /**
     * Handle an incoming request.
     *
     * Validates metrics endpoint access using either:
     * 1. Bearer token matching METRICS_TOKEN env variable
     * 2. IP address in METRICS_ALLOWED_IPS whitelist
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check bearer token
        $configuredToken = config('alerts.ops.metrics_token');
        if ($configuredToken) {
            $providedToken = $request->bearerToken();
            if ($providedToken === $configuredToken) {
                return $next($request);
            }
        }

        // Check IP whitelist
        $allowedIps = config('alerts.ops.metrics_allowed_ips', []);
        if (! empty($allowedIps)) {
            $clientIp = $request->ip();
            if (in_array($clientIp, $allowedIps, true)) {
                return $next($request);
            }
        }

        // If no token configured and no IP whitelist, allow localhost only
        if (! $configuredToken && empty($allowedIps)) {
            $clientIp = $request->ip();
            if (in_array($clientIp, ['127.0.0.1', '::1'], true)) {
                return $next($request);
            }
        }

        return response('Unauthorized', 401);
    }
}
