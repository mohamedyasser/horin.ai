<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\TeleBot;

class TelegramWebhookController extends Controller
{
    private TeleBot $bot;

    public function __construct()
    {
        $this->bot = new TeleBot(config('telegram.bot_token'));
    }

    /**
     * Handle incoming Telegram webhook requests.
     */
    public function handle(Request $request): JsonResponse
    {
        $update = $request->all();

        Log::info('Telegram webhook received', ['update' => $update]);

        if (isset($update['message']['contact'])) {
            $this->handleContact($update['message']);
        } elseif (isset($update['message']['text'])) {
            $this->handleTextMessage($update['message']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle text messages (including /start command).
     */
    protected function handleTextMessage(array $message): void
    {
        $text = $message['text'];
        $from = $message['from'];
        $chatId = $message['chat']['id'];

        // Handle /start command - send phone verification button
        if (str_starts_with($text, '/start')) {
            $user = User::query()->where('telegram_id', (string) $from['id'])->first();

            if ($user && ! $user->hasVerifiedPhone()) {
                $this->sendPhoneRequestMessage($chatId);
            } elseif ($user && $user->hasVerifiedPhone()) {
                $this->sendMessage($chatId, __('auth.telegram.already_verified'));
            } else {
                $this->sendMessage($chatId, __('auth.telegram.please_login_first'));
            }
        }
    }

    /**
     * Send message with phone request button.
     */
    protected function sendPhoneRequestMessage(int $chatId): void
    {
        try {
            $this->bot->sendMessage([
                'chat_id' => $chatId,
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
        } catch (\Exception $e) {
            Log::error('Failed to send phone request message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle contact message for phone verification.
     */
    protected function handleContact(array $message): void
    {
        $contact = $message['contact'];
        $from = $message['from'];
        $chatId = $message['chat']['id'];

        if ($contact['user_id'] !== $from['id']) {
            $this->sendMessage(
                $chatId,
                __('auth.telegram_verification.contact_mismatch')
            );

            return;
        }

        $user = User::query()->where('telegram_id', (string) $from['id'])->first();

        if (! $user) {
            $this->sendMessage(
                $chatId,
                __('auth.telegram_verification.user_not_found')
            );

            return;
        }

        $normalizedPhone = $this->normalizePhone($contact['phone_number']);

        $user->phone = $normalizedPhone;
        $user->markPhoneAsVerified();

        $this->sendMessage(
            $chatId,
            __('auth.telegram_verification.success')
        );
    }

    /**
     * Send a message to a Telegram chat.
     */
    protected function sendMessage(int $chatId, string $text): void
    {
        try {
            $this->bot->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'text' => $text,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalize phone number to ensure it starts with +.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        return $phone;
    }
}
