<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramAuthRequest;
use App\Models\User;
use App\Services\TelegramBotService;
use App\Services\TelegramHashValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TelegramAuthController extends Controller
{
    public function __construct(
        private TelegramHashValidator $hashValidator,
        private TelegramBotService $telegramBot
    ) {}

    /**
     * Show the Telegram auth page.
     */
    public function show(): Response
    {
        return Inertia::render('auth/TelegramAuth', [
            'botUsername' => config('telegram.bot_username'),
        ]);
    }

    /**
     * Handle Telegram auth callback.
     */
    public function callback(TelegramAuthRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! $this->hashValidator->validate($data)) {
            return redirect()->route('auth.telegram')
                ->withErrors(['telegram' => __('auth.telegram.invalid')]);
        }

        $user = User::updateOrCreate(
            ['telegram_id' => $data['id']],
            [
                'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                'telegram_username' => $data['username'] ?? null,
                'telegram_photo_url' => $data['photo_url'] ?? null,
            ]
        );

        Auth::login($user, remember: true);

        if (! $user->hasVerifiedPhone()) {
            $this->sendPhoneVerificationRequest($user);

            return redirect()->route('verification.phone');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Send phone verification request via Telegram bot.
     */
    private function sendPhoneVerificationRequest(User $user): void
    {
        $this->telegramBot->sendPhoneVerificationRequest($user);
    }
}
