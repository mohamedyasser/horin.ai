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

        if ($currentStep === 0) {
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
    public function sendOnboardingStep(int $chatId, User $user, int $step, ?OnboardingKeyboardBuilder $builder = null): void
    {
        $builder = $builder ?? new OnboardingKeyboardBuilder;
        $locale = $user->language ?? 'en';
        $selected = $builder->getSelectedValues($user, $step);

        $message = $builder->getStepMessage($step, $locale);
        $keyboard = match ($step) {
            1 => $builder->buildStep1Keyboard($locale, $selected),
            2 => $builder->buildStep2Keyboard($locale, $selected),
            3 => $selected['country_id']
                ? $builder->buildStep3MarketsKeyboard($locale, $selected['country_id'], $selected['markets'] ?? [])
                : $builder->buildStep3CountryKeyboard($locale),
            4 => $builder->buildStep4Keyboard($locale, $selected['sectors'] ?? []),
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

        $text = $locale === 'ar'
            ? '👋 مرحبا! افتح التطبيق للبدء.'
            : '👋 Hi! Open the app to get started.';

        $buttonText = $locale === 'ar' ? '🚀 فتح حورين' : '🚀 Open Horin';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
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
}
