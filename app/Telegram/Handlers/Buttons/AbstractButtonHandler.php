<?php

namespace App\Telegram\Handlers\Buttons;

use App\Models\User;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

abstract class AbstractButtonHandler implements ButtonHandlerInterface
{
    protected TeleBot $bot;

    protected Update $update;

    public function __construct(TeleBot $bot, Update $update)
    {
        $this->bot = $bot;
        $this->update = $update;
    }

    /**
     * Send a message to the chat.
     */
    protected function sendMessage(array $params): mixed
    {
        return $this->bot->sendMessage($params);
    }

    /**
     * Get user by telegram ID.
     */
    protected function getUser(string $telegramId): ?User
    {
        return User::where('telegram_id', $telegramId)->first();
    }

    /**
     * Get locale from telegram user if not available from user model.
     */
    protected function getLocaleFromTelegram(): string
    {
        $langCode = $this->update->message->from->language_code ?? 'en';

        return str_starts_with($langCode, 'ar') ? 'ar' : 'en';
    }
}
