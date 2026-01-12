<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\TeleBot;

class TelegramMiniAppAuthController extends Controller
{
    /**
     * Authenticate a user via Telegram Mini App.
     *
     * The request is validated by the ValidateTelegramMiniApp middleware
     * which extracts user data from the init data.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $telegramUser = $request->attributes->get('telegram_mini_app_user');

        if (! $telegramUser || ! isset($telegramUser['id'])) {
            return response()->json([
                'error' => 'Invalid user data',
            ], 400);
        }

        $firstName = $telegramUser['first_name'] ?? '';
        $lastName = $telegramUser['last_name'] ?? '';
        $name = trim("{$firstName} {$lastName}");

        $user = User::updateOrCreate(
            ['telegram_id' => (string) $telegramUser['id']],
            [
                'name' => $name ?: 'Telegram User',
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
            ]
        );

        Auth::login($user, remember: true);

        Log::info('User authenticated via Mini App', [
            'user_id' => $user->id,
            'telegram_id' => $telegramUser['id'],
        ]);

        $requiresPhoneVerification = ! $user->hasVerifiedPhone();

        // If phone verification is required, send the verification request
        if ($requiresPhoneVerification && $user->telegram_id) {
            $this->sendPhoneVerificationRequest($user);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'telegram_username' => $user->telegram_username,
                'telegram_photo_url' => $user->telegram_photo_url,
            ],
            'requires_phone_verification' => $requiresPhoneVerification,
            'requires_onboarding' => ! $user->hasCompletedOnboarding(),
            'redirect_url' => $this->getRedirectUrl($user),
        ]);
    }

    /**
     * Determine the redirect URL based on user status.
     */
    private function getRedirectUrl(User $user): string
    {
        if (! $user->hasVerifiedPhone()) {
            return route('verification.phone');
        }

        if (! $user->hasCompletedOnboarding()) {
            return route('onboarding');
        }

        return route('dashboard');
    }

    /**
     * Send phone verification request via Telegram bot.
     */
    private function sendPhoneVerificationRequest(User $user): void
    {
        try {
            $locale = $user->language ?? 'en';
            $message = $locale === 'ar'
                ? "📱 يرجى التحقق من رقم هاتفك للمتابعة.\n\nاضغط الزر أدناه لإكمال التحقق."
                : "📱 Please verify your phone number to continue.\n\nTap the button below to complete verification.";
            $buttonText = $locale === 'ar' ? '✅ التحقق الآن' : '✅ Verify Now';

            $bot = new TeleBot(config('telegram.bot_token'));

            $bot->sendMessage([
                'chat_id' => $user->telegram_id,
                'text' => $message,
                'parse_mode' => 'Markdown',
                'reply_markup' => [
                    'inline_keyboard' => [[
                        [
                            'text' => $buttonText,
                            'web_app' => ['url' => route('verification.phone')],
                        ],
                    ]],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send phone verification request', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
