<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use App\Telegram\Services\DefaultKeyboardBuilder;
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
                'text' => '❌ Please share your own phone number.',
                'reply_markup' => [
                    'keyboard' => [[
                        [
                            'text' => '📱 Share Phone Number',
                            'request_contact' => true,
                        ],
                    ]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ],
            ]);

            return null;
        }

        // Normalize phone number (ensure + prefix)
        $phone = $contact->phone_number;
        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            // Create new user from Telegram data
            $user = $this->createUserFromTelegram($telegramId, $phone, $contact, $message->from);

            Log::info('New user registered via Telegram', [
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
                'phone' => $phone,
            ]);
        } else {
            // Update existing user with verified phone
            $user->update([
                'phone' => $phone,
                'phone_verified_at' => now(),
            ]);

            Log::info('Phone verified via Telegram', [
                'user_id' => $user->id,
                'phone' => $phone,
            ]);
        }

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

    private function createUserFromTelegram(string $telegramId, string $phone, object $contact, object $from): User
    {
        // Build name from contact or from data
        $firstName = $contact->first_name ?? $from->first_name ?? '';
        $lastName = $contact->last_name ?? $from->last_name ?? '';
        $name = trim("{$firstName} {$lastName}") ?: 'Telegram User';

        // Get language preference from Telegram
        $language = $from->language_code ?? 'en';
        // Normalize to supported languages (en, ar)
        $language = str_starts_with($language, 'ar') ? 'ar' : 'en';

        return User::create([
            'name' => $name,
            'phone' => $phone,
            'phone_verified_at' => now(),
            'telegram_id' => $telegramId,
            'language' => $language,
        ]);
    }

    private function startOnboarding(int $chatId, User $user): void
    {
        $builder = new OnboardingKeyboardBuilder;
        $locale = $user->language ?? 'en';

        // Determine current step
        $currentStep = $builder->getCurrentStep($user);

        if ($currentStep === 'complete') {
            // Already complete somehow
            $user->markOnboardingAsComplete();
            $this->sendWelcomeBack($chatId, $locale);

            return;
        }

        $message = $builder->getStepMessage($currentStep, $locale);

        $keyboard = match ($currentStep) {
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

    private function sendWelcomeBack(int $chatId, string $locale): void
    {
        $telegramId = (string) $this->update->message->from->id;
        $user = User::where('telegram_id', $telegramId)->first();

        $text = $locale === 'ar'
            ? "🎉 أنت جاهز لتلقي تنبيهات الأسهم!\n\nاختر من القائمة أدناه:"
            : "🎉 You're all set to receive stock alerts!\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => DefaultKeyboardBuilder::forUser($user, $locale),
        ]);
    }
}
