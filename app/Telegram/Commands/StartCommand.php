<?php

namespace App\Telegram\Commands;

use App\Models\User;
use App\Telegram\Services\DefaultKeyboardBuilder;
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
        // Escape underscores for Markdown
        $name = str_replace('_', '\\_', $user->name ?? 'there');

        $message = $locale === 'ar'
            ? "👋 مرحباً مجدداً، *{$name}*!\n\nأنت جاهز لتلقي تنبيهات الأسهم. اختر من القائمة أدناه:"
            : "👋 Welcome back, *{$name}*!\n\nYou're all set to receive stock alerts. Choose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::forUser($user),
        ]);
    }

    private function sendWelcomeNewUser(int $chatId, object $from): void
    {
        // Escape underscores for Markdown
        $name = str_replace('_', '\\_', $from->first_name ?? 'there');

        // Show bilingual welcome with language selection
        $message = "👋 Welcome *{$name}*! / مرحباً *{$name}*!\n\n"
            ."Please select your language:\n"
            .'يرجى اختيار لغتك:';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::languageKeyboard(),
        ]);
    }

    private function sendPhoneRequest(int $chatId): void
    {
        $locale = $this->update->message->from->language_code ?? 'en';
        $locale = str_starts_with($locale, 'ar') ? 'ar' : 'en';

        $message = $locale === 'ar'
            ? "📱 يرجى التحقق من رقم هاتفك للمتابعة.\n\nاضغط الزر أدناه لمشاركة رقم هاتفك."
            : "📱 Please verify your phone number to continue.\n\nTap the button below to share your phone number.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::phoneVerificationKeyboard($locale),
        ]);
    }
}
