<?php

namespace App\Http\Middleware;

use App\Services\TelegramMiniAppValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTelegramMiniApp
{
    public function __construct(
        private TelegramMiniAppValidator $validator
    ) {}

    /**
     * Handle an incoming request.
     *
     * Validates the Authorization header containing Telegram Mini App init data.
     * Format: Authorization: tma <initData>
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization', '');
        $parts = explode(' ', $authHeader, 2);
        $authType = $parts[0] ?? '';
        $initData = $parts[1] ?? '';

        if ($authType !== 'tma' || empty($initData)) {
            return response()->json([
                'error' => 'Invalid authorization',
                'message' => 'Expected Authorization header with format: tma <initData>',
            ], 401);
        }

        $validatedData = $this->validator->validate($initData);

        if ($validatedData === false) {
            return response()->json([
                'error' => 'Invalid init data',
                'message' => 'The provided Telegram Mini App init data is invalid or expired.',
            ], 401);
        }

        $user = $this->validator->extractUser($validatedData);

        if (! $user || ! isset($user['id'])) {
            return response()->json([
                'error' => 'Invalid user data',
                'message' => 'The init data does not contain valid user information.',
            ], 401);
        }

        // Store validated data for controller use
        $request->attributes->set('telegram_mini_app_data', $validatedData);
        $request->attributes->set('telegram_mini_app_user', $user);

        return $next($request);
    }
}
