<?php

namespace App\Telegram\Commands;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;

class StartCommand extends CommandHandler
{
    protected static function aliases(): array
    {
        return ['/start'];
    }

    protected static function description(?string $locale = null): string
    {
        return 'Start the bot and verify your account';
    }

    public function handle(): mixed
    {
        $message = $this->update->message;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if ($user && $user->hasVerifiedPhone()) {
            $this->sendWelcomeBack($chatId, $user);
        } elseif ($user && ! $user->hasVerifiedPhone()) {
            $this->sendPhoneRequest($chatId);
        } else {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => __('auth.telegram.please_login_first'),
            ]);
        }

        return null;
    }

    private function sendWelcomeBack(int $chatId, User $user): void
    {
        $locale = $user->language ?? 'en';
        $name = $user->name ?? 'there';

        $message = $locale === 'ar'
            ? "👋 مرحباً مجدداً، *{$name}*!\n\nأنت جاهز لتلقي تنبيهات الأسهم.\n\n📋 /alerts - عرض التنبيهات\n⚙️ /settings - الإعدادات\n❓ /help - المساعدة"
            : "👋 Welcome back, *{$name}*!\n\nYou're all set to receive stock alerts.\n\n📋 /alerts - View alerts\n⚙️ /settings - Settings\n❓ /help - Help";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function sendPhoneRequest(int $chatId): void
    {
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => __('auth.telegram.verify_phone_message'),
            'reply_markup' => [
                'keyboard' => [[
                    [
                        'text' => __('auth.telegram.share_phone_button'),
                        'request_contact' => true,
                    ],
                ]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ],
        ]);
    }
}
