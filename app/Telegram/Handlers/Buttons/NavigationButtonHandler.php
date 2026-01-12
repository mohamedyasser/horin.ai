<?php

namespace App\Telegram\Handlers\Buttons;

use App\Models\User;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\OnboardingCommand;
use App\Telegram\Keyboards\MainMenuKeyboard;
use Illuminate\Support\Facades\Log;

class NavigationButtonHandler extends AbstractButtonHandler
{
    public static function supportedActions(): array
    {
        return [
            'back',
            'cancel_input',
            'set_language_en',
            'set_language_ar',
            'phone_share_hint',
            'help',
            'onboarding',
        ];
    }

    public function goBackToMainMenu(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "🏠 *القائمة الرئيسية*\n\nاختر من القائمة أدناه:"
            : "🏠 *Main Menu*\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => MainMenuKeyboard::forUser($user, $locale),
        ]);

        return null;
    }

    public function cancelInput(int $chatId, ?User $user, string $locale): mixed
    {
        if ($user) {
            $user->update(['telegram_awaiting_input' => null]);
        }

        return $this->goBackToMainMenu($chatId, $user, $locale);
    }

    public function setLanguageEn(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setUserLanguage($chatId, $user, 'en');
    }

    public function setLanguageAr(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setUserLanguage($chatId, $user, 'ar');
    }

    private function setUserLanguage(int $chatId, ?User $user, string $newLocale): mixed
    {
        $message = $this->update->message;
        $telegramId = (string) $message->from->id;

        if ($user) {
            $user->update(['language' => $newLocale]);

            $text = $newLocale === 'ar'
                ? "✅ تم تغيير اللغة إلى العربية.\n\nاختر من القائمة أدناه:"
                : "✅ Language changed to English.\n\nChoose from the menu below:";

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => MainMenuKeyboard::forUser($user, $newLocale),
            ]);
        } else {
            $from = $message->from;
            $firstName = $from->first_name ?? '';
            $lastName = $from->last_name ?? '';
            $name = trim("{$firstName} {$lastName}") ?: 'Telegram User';

            $user = User::create([
                'name' => $name,
                'telegram_id' => $telegramId,
                'telegram_username' => $from->username ?? null,
                'language' => $newLocale,
            ]);

            Log::info('New user created via language selection', [
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
                'language' => $newLocale,
            ]);

            $text = $newLocale === 'ar'
                ? "✅ تم اختيار العربية.\n\n📱 يرجى مشاركة رقم هاتفك للمتابعة.\n\nاضغط الزر أدناه:"
                : "✅ English selected.\n\n📱 Please share your phone number to continue.\n\nTap the button below:";

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => MainMenuKeyboard::phoneVerificationKeyboard($newLocale),
            ]);
        }

        return null;
    }

    public function showPhoneShareHint(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "📱 يرجى الضغط على الزر أدناه لمشاركة رقم هاتفك.\n\n⚠️ لا تكتب الرقم - اضغط الزر للمشاركة التلقائية."
            : "📱 Please tap the button below to share your phone number.\n\n⚠️ Don't type the text - tap the button to share automatically.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => MainMenuKeyboard::phoneVerificationKeyboard($locale),
        ]);

        return null;
    }

    public function triggerHelpCommand(int $chatId, ?User $user, string $locale): mixed
    {
        $handler = new HelpCommand($this->bot, $this->update);

        return $handler->handle();
    }

    public function triggerOnboardingCommand(int $chatId, ?User $user, string $locale): mixed
    {
        $handler = new OnboardingCommand($this->bot, $this->update);

        return $handler->handle();
    }
}
