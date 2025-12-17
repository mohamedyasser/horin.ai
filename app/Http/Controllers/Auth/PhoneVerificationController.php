<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use WeStacks\TeleBot\TeleBot;

class PhoneVerificationController extends Controller
{
    /**
     * Show the phone verification page.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedPhone()) {
            return redirect()->route('verification.notice');
        }

        return Inertia::render('auth/VerifyPhone', [
            'botUsername' => config('telegram.bot_username'),
            'telegramId' => $request->user()->telegram_id,
        ]);
    }

    /**
     * Get the current verification status.
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'verified' => $request->user()->hasVerifiedPhone(),
        ]);
    }

    /**
     * Resend the phone verification request via Telegram.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return redirect()->route('verification.notice');
        }

        try {
            $bot = new TeleBot(config('telegram.bot_token'));

            $bot->sendMessage([
                'chat_id' => $user->telegram_id,
                'text' => __('auth.telegram.verify_phone_message'),
                'reply_markup' => [
                    'keyboard' => [
                        [
                            [
                                'text' => __('auth.telegram.share_phone_button'),
                                'request_contact' => true,
                            ],
                        ],
                    ],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ],
            ]);

            return back()->with('status', 'verification-request-sent');
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram verification request', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'telegram' => __('auth.telegram.send_failed'),
            ]);
        }
    }
}
