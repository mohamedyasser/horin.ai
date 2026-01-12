<?php

namespace App\Telegram\Commands;

use App\Models\User;
use Illuminate\Support\Facades\Log;
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
        Log::debug('StartCommand handle() called');

        try {
            $message = $this->update->message;
            $chatId = $message->chat->id;
            $telegramId = (string) $message->from->id;

            Log::debug('StartCommand processing', [
                'chat_id' => $chatId,
                'telegram_id' => $telegramId,
            ]);

            $user = User::where('telegram_id', $telegramId)->first();

            Log::debug('StartCommand user lookup', [
                'user_found' => $user !== null,
                'user_id' => $user?->id,
            ]);

            if ($user && $user->hasVerifiedPhone()) {
                Log::debug('StartCommand: sending welcome back');
                $this->sendWelcomeBack($chatId, $user);
            } elseif ($user && ! $user->hasVerifiedPhone()) {
                Log::debug('StartCommand: sending phone request');
                $this->sendPhoneRequest($chatId);
            } else {
                Log::debug('StartCommand: sending welcome with mini app button');
                $this->sendWelcomeNewUser($chatId, $message->from);
            }

            Log::debug('StartCommand completed successfully');

            return null;
        } catch (\Exception $e) {
            Log::error('StartCommand error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function sendWelcomeBack(int $chatId, User $user): void
    {
        $locale = $user->language ?? 'en';
        $name = $user->name ?? 'there';

        $message = $locale === 'ar'
            ? "👋 مرحباً مجدداً، *{$name}*!\n\nأنت جاهز لتلقي تنبيهات الأسهم.\n\n📋 /alerts - عرض التنبيهات\n⚙️ /settings - الإعدادات\n❓ /help - المساعدة"
            : "👋 Welcome back, *{$name}*!\n\nYou're all set to receive stock alerts.\n\n📋 /alerts - View alerts\n⚙️ /settings - Settings\n❓ /help - Help";

        $buttonText = $locale === 'ar' ? '📊 فتح حورين' : '📊 Open Horin';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $buttonText,
                        'web_app' => ['url' => config('app.url').'/dashboard'],
                    ],
                ]],
            ],
        ]);
    }

    private function sendWelcomeNewUser(int $chatId, object $from): void
    {
        $locale = $from->language_code ?? 'en';
        $name = $from->first_name ?? 'there';

        $message = $locale === 'ar'
            ? "👋 مرحباً *{$name}*!\n\nأنا بوت حورين للتنبيهات. افتح التطبيق للبدء والحصول على تنبيهات الأسهم."
            : "👋 Welcome *{$name}*!\n\nI'm the Horin alerts bot. Open the app to get started and receive stock alerts.";

        $buttonText = $locale === 'ar' ? '🚀 فتح حورين' : '🚀 Open Horin';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $buttonText,
                        'web_app' => ['url' => config('app.url')],
                    ],
                ]],
            ],
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
