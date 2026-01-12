<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Telegram\Handlers\Buttons\ButtonRegistry;
use App\Telegram\Keyboards\MainMenuKeyboard;
use WeStacks\TeleBot\Foundation\UpdateHandler;

/**
 * Handler for reply keyboard button clicks and unknown text inputs.
 *
 * This handler routes button presses to domain-specific handlers via ButtonRegistry.
 */
class KeyboardButtonHandler extends UpdateHandler
{
    public function trigger(): bool
    {
        if (! isset($this->update->message?->text)) {
            return false;
        }

        $text = $this->update->message->text;

        if (str_starts_with($text, '/')) {
            return false;
        }

        $telegramId = (string) $this->update->message->from->id;
        $user = User::where('telegram_id', $telegramId)->first();

        if ($user && $user->telegram_awaiting_input) {
            return false;
        }

        if ($user && $user->hasVerifiedPhone() && ! $user->hasCompletedOnboarding()) {
            return false;
        }

        return true;
    }

    public function handle(): mixed
    {
        $message = $this->update->message;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;
        $text = trim($message->text);

        $user = User::where('telegram_id', $telegramId)->first();
        $locale = $user?->language ?? $this->getLocaleFromTelegram();

        $resolved = ButtonRegistry::resolve($text);

        if ($resolved) {
            [$handlerClass, $method, $action] = $resolved;
            $handler = new $handlerClass($this->bot, $this->update);

            return $handler->$method($chatId, $user, $locale);
        }

        return $this->handleUnknownInput($chatId, $user, $locale);
    }

    private function handleUnknownInput(int $chatId, ?User $user, string $locale): mixed
    {
        $response = MainMenuKeyboard::getUnknownInputResponse($user, $locale);

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $response['text'],
            'reply_markup' => $response['reply_markup'],
        ]);

        return null;
    }

    private function getLocaleFromTelegram(): string
    {
        $langCode = $this->update->message->from->language_code ?? 'en';

        return str_starts_with($langCode, 'ar') ? 'ar' : 'en';
    }
}
