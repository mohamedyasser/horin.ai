<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $locale = $user->language ?? 'en';
        $message = $locale === 'ar'
            ? "📱 يرجى التحقق من رقم هاتفك للمتابعة.\n\nاضغط الزر أدناه لمشاركة رقم هاتفك."
            : "📱 Please verify your phone number to continue.\n\nTap the button below to share your phone number.";
        $buttonText = $locale === 'ar' ? '📱 مشاركة رقم الهاتف' : '📱 Share Phone Number';

        $bot = new TeleBot(config('telegram.bot_token'));

        $bot->sendMessage([
            'chat_id' => $user->telegram_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'keyboard' => [[
                    [
                        'text' => $buttonText,
                        'request_contact' => true,
                    ],
                ]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ],
        ]);

        return back()->with('status', 'verification-message-sent');
    }
}
