<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhoneVerificationController extends Controller
{
    public function __construct(
        private TelegramBotService $telegramBot
    ) {}

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

        $success = $this->telegramBot->sendPhoneVerificationRequest($user);

        if ($success) {
            return back()->with('status', 'verification-request-sent');
        }

        return back()->withErrors([
            'telegram' => __('auth.telegram.send_failed'),
        ]);
    }
}
