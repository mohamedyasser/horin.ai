<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTelegramWebhook
{
    /**
     * Handle an incoming request.
     *
     * Validates the X-Telegram-Bot-Api-Secret-Token header against
     * the configured webhook secret.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('telebot.bots.horin.webhook.secret_token');

        // If no secret is configured, skip validation
        if (empty($secret)) {
            return $next($request);
        }

        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($headerSecret !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
