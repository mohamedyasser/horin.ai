<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramHashValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use WeStacks\TeleBot\TeleBot;

class TelegramAuthController extends Controller
{
    public function __construct(
        private TelegramHashValidator $hashValidator
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
    public function callback(Request $request): RedirectResponse
    {
        dd($request->all());
        $data = $request->only([
            'id', 'first_name', 'last_name', 'username',
            'photo_url', 'auth_date', 'hash',
        ]);

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
        if (! $user->telegram_id) {
            return;
        }

        $bot = new TeleBot(config('telegram.bot_token'));

        $bot->sendMessage([
            'chat_id' => $user->telegram_id,
            'text' => __('auth.telegram.verify_phone_message'),
            'reply_markup' => json_encode([
                'keyboard' => [[
                    [
                        'text' => __('auth.telegram.share_phone_button'),
                        'request_contact' => true,
                    ],
                ]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);
    }
}
