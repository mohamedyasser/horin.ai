<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Telegram\Commands\OnboardingCommand;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\UpdateHandler;

class ContactHandler extends UpdateHandler
{
    public function trigger(): bool
    {
        return isset($this->update->message?->contact);
    }

    public function handle(): mixed
    {
        $message = $this->update->message;
        $contact = $message->contact;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;

        // Verify the contact belongs to the sender
        if ((string) $contact->user_id !== $telegramId) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Please share your own phone number.',
            ]);

            return null;
        }

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $this->sendLoginPrompt($chatId);

            return null;
        }

        // Normalize phone number (ensure + prefix)
        $phone = $contact->phone_number;
        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        // Update user with verified phone
        $user->update([
            'phone' => $phone,
            'phone_verified_at' => now(),
        ]);

        Log::info('Phone verified via Telegram', [
            'user_id' => $user->id,
            'phone' => $phone,
        ]);

        $locale = $user->language ?? 'en';

        // Remove the reply keyboard first with success message
        if ($locale === 'ar') {
            $text = '✅ تم التحقق من رقم الهاتف بنجاح!';
        } else {
            $text = '✅ Phone verified successfully!';
        }

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'remove_keyboard' => true,
            ],
        ]);

        // Check if user needs onboarding
        if (! $user->hasCompletedOnboarding()) {
            // Auto-trigger onboarding
            $this->startOnboarding($chatId, $user);
        } else {
            // User already completed onboarding - show welcome back
            $this->sendWelcomeBack($chatId, $locale);
        }

        return null;
    }

    private function sendLoginPrompt(int $chatId): void
    {
        $locale = $this->update->message->from->language_code ?? 'en';

        $text = $locale === 'ar'
            ? '👋 مرحباً! افتح التطبيق للبدء.'
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

    private function startOnboarding(int $chatId, User $user): void
    {
        $builder = new OnboardingKeyboardBuilder;
        $locale = $user->language ?? 'en';

        // Determine current step
        $currentStep = $builder->getCurrentStep($user);

        if ($currentStep === 0) {
            // Already complete somehow
            $user->markOnboardingAsComplete();
            $this->sendWelcomeBack($chatId, $locale);

            return;
        }

        $selected = $builder->getSelectedValues($user, $currentStep);
        $message = $builder->getStepMessage($currentStep, $locale);

        $keyboard = match ($currentStep) {
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

    private function sendWelcomeBack(int $chatId, string $locale): void
    {
        $text = $locale === 'ar'
            ? "🎉 أنت جاهز لتلقي تنبيهات الأسهم!\n\nاضغط الزر أدناه لفتح التطبيق."
            : "🎉 You're all set to receive stock alerts!\n\nTap below to open the app.";

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
}
