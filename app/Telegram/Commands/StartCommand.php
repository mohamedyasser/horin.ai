<?php

namespace App\Telegram\Commands;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class StartCommand extends CommandHandler
{
    protected static function command(): string
    {
        return 'start';
    }

    protected static function aliases(): array
    {
        return [];
    }

    protected static function description(): string
    {
        return 'Start the bot and verify your account';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $message = $update->message;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if ($user && $user->hasVerifiedPhone()) {
            $this->sendWelcomeBack($bot, $chatId, $user);
        } elseif ($user && ! $user->hasVerifiedPhone()) {
            $this->sendPhoneRequest($bot, $chatId);
        } else {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => __('auth.telegram.please_login_first'),
            ]);
        }

        return null;
    }

    private function sendWelcomeBack(TeleBot $bot, int $chatId, User $user): void
    {
        $locale = $user->language ?? 'en';
        $name = $user->name ?? 'there';

        $message = $locale === 'ar'
            ? "👋 مرحباً مجدداً، *{$name}*!\n\nأنت جاهز لتلقي تنبيهات الأسهم.\n\n📋 /alerts - عرض التنبيهات\n⚙️ /settings - الإعدادات\n❓ /help - المساعدة"
            : "👋 Welcome back, *{$name}*!\n\nYou're all set to receive stock alerts.\n\n📋 /alerts - View alerts\n⚙️ /settings - Settings\n❓ /help - Help";

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function sendPhoneRequest(TeleBot $bot, int $chatId): void
    {
        $bot->sendMessage([
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
