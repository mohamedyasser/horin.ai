<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\TeleBot;

class TelegramBotService
{
    private TeleBot $bot;

    public function __construct()
    {
        $this->bot = new TeleBot(config('telegram.bot_token'));
    }

    /**
     * Send phone verification request to user via Telegram.
     */
    public function sendPhoneVerificationRequest(User $user): bool
    {
        if (! $user->telegram_id) {
            return false;
        }

        try {
            $this->bot->sendMessage([
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

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram phone verification request', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Send a simple text message to a Telegram chat.
     */
    public function sendMessage(int|string $chatId, string $text): bool
    {
        try {
            $this->bot->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'text' => $text,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the underlying TeleBot instance if needed.
     */
    public function getBot(): TeleBot
    {
        return $this->bot;
    }
}
