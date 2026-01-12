<?php

namespace App\Telegram\Commands;

use App\Models\User;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use WeStacks\TeleBot\Foundation\CommandHandler;

class OnboardingCommand extends CommandHandler
{
    protected static function aliases(): array
    {
        return ['/onboarding', '/setup'];
    }

    protected static function description(?string $locale = null): string
    {
        return 'Start or continue your account setup';
    }

    public function handle(): mixed
    {
        $chatId = $this->update->message->chat->id;
        $telegramId = (string) $this->update->message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $this->sendLoginPrompt($chatId);

            return null;
        }

        $locale = $user->language ?? 'en';
        $builder = new OnboardingKeyboardBuilder;

        // Check if onboarding already complete
        if ($user->hasCompletedOnboarding()) {
            $this->sendAlreadyCompleteMessage($chatId, $locale);

            return null;
        }

        // Determine current step
        $currentStep = $builder->getCurrentStep($user);

        if ($currentStep === 'complete') {
            // User has filled all data but not marked as complete
            $user->markOnboardingAsComplete();
            $this->sendCompletionMessage($chatId, $locale, $builder);

            return null;
        }

        // Send the appropriate step
        $this->sendOnboardingStep($chatId, $user, $currentStep, $builder);

        return null;
    }

    /**
     * Send onboarding step message with keyboard.
     */
    public function sendOnboardingStep(int $chatId, User $user, string $step, ?OnboardingKeyboardBuilder $builder = null): void
    {
        $builder = $builder ?? new OnboardingKeyboardBuilder;
        $locale = $user->language ?? 'en';

        $message = $builder->getStepMessage($step, $locale);
        $keyboard = match ($step) {
            '1a' => $builder->buildStep1aKeyboard($locale, $user->experience_level),
            '1b' => $builder->buildStep1bKeyboard($locale, $user->risk_level),
            '2a' => $builder->buildStep2aKeyboard($locale, $user->investment_goal),
            '2b' => $builder->buildStep2bKeyboard($locale, $user->trading_style),
            '3a' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            '3b' => $builder->buildStep3MarketsKeyboard($locale, $user->country_id, $user->markets()->pluck('markets.id')->toArray()),
            '4' => $builder->buildStep4Keyboard($locale, $user->sectors()->pluck('sectors.id')->toArray()),
            default => [],
        };

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);
    }

    /**
     * Send completion message with dashboard button.
     */
    public function sendCompletionMessage(int $chatId, string $locale, ?OnboardingKeyboardBuilder $builder = null): void
    {
        $builder = $builder ?? new OnboardingKeyboardBuilder;

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $builder->getCompletionMessage($locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildCompletionKeyboard($locale),
            ],
        ]);
    }

    private function sendAlreadyCompleteMessage(int $chatId, string $locale): void
    {
        $text = $locale === 'ar'
            ? "✅ لقد أكملت الإعداد بالفعل!\n\nاستخدم /settings لتعديل إعداداتك."
            : "✅ You've already completed setup!\n\nUse /settings to modify your preferences.";

        $buttonText = $locale === 'ar' ? '📊 فتح لوحة التحكم' : '📊 Open Dashboard';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
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

    private function sendLoginPrompt(int $chatId): void
    {
        $locale = $this->update->message->from->language_code ?? 'en';
        // Normalize to supported languages
        $locale = str_starts_with($locale, 'ar') ? 'ar' : 'en';

        $text = $locale === 'ar'
            ? "👋 مرحبا! اضغط الزر أدناه للتسجيل."
            : "👋 Hi! Tap the button below to register.";

        $buttonText = $locale === 'ar' ? '📱 التسجيل الآن' : '📱 Register Now';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $buttonText,
                        'web_app' => ['url' => config('app.url').'/auth/telegram/register'],
                    ],
                ]],
            ],
        ]);
    }
}
