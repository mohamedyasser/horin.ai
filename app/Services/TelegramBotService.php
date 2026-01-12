<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Laravel\TeleBot;
use WeStacks\TeleBot\Objects\Message;

class TelegramBotService
{
    /**
     * Send a text message to a chat.
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        array $options = []
    ): ?Message {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ], $options);

            $result = TeleBot::sendMessage($params);

            Log::debug('Telegram message sent', [
                'chat_id' => $chatId,
                'message_id' => $result->message_id ?? null,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Send a message with inline keyboard.
     */
    public function sendMessageWithKeyboard(
        int|string $chatId,
        string $text,
        array $keyboard,
        array $options = []
    ): ?Message {
        $options['reply_markup'] = [
            'inline_keyboard' => $keyboard,
        ];

        return $this->sendMessage($chatId, $text, $options);
    }

    /**
     * Edit an existing message.
     */
    public function editMessage(
        int|string $chatId,
        int $messageId,
        string $text,
        array $options = []
    ): ?Message {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ], $options);

            return TeleBot::editMessageText($params);
        } catch (\Exception $e) {
            Log::error('Failed to edit Telegram message', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Edit message reply markup (keyboard).
     */
    public function editMessageKeyboard(
        int|string $chatId,
        int $messageId,
        array $keyboard
    ): bool {
        try {
            TeleBot::editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => [
                    'inline_keyboard' => $keyboard,
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to edit message keyboard', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Answer a callback query (button press).
     */
    public function answerCallback(
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false
    ): bool {
        try {
            TeleBot::answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to answer callback query', [
                'callback_query_id' => $callbackQueryId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove reply keyboard after use.
     */
    public function removeKeyboard(int|string $chatId, string $text): ?Message
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => [
                'remove_keyboard' => true,
            ],
        ]);
    }

    /**
     * Get bot info.
     */
    public function getMe(): array
    {
        return (array) TeleBot::getMe();
    }
}
