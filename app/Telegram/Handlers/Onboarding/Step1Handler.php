<?php

namespace App\Telegram\Handlers\Onboarding;

use App\Models\User;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Step 1: Experience & Risk level selection.
 *
 * Callback patterns:
 * - ob:exp:{level} - Set experience level
 * - ob:risk:{level} - Set risk level
 * - ob:step1:next - Proceed to step 2
 */
class Step1Handler extends CallbackHandler
{
    protected string $match = '/^ob:(exp|risk|step1):(beginner|intermediate|advanced|conservative|moderate|aggressive|next)$/';

    private const EXPERIENCE_LEVELS = ['beginner', 'intermediate', 'advanced'];

    private const RISK_LEVELS = ['conservative', 'moderate', 'aggressive'];

    public function handle(): mixed
    {
        $callbackQuery = $this->update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError('User not found. Please /start first.');
        }

        $parts = explode(':', $data);
        $action = $parts[1];
        $value = $parts[2];

        $locale = $user->language ?? 'en';
        $builder = new OnboardingKeyboardBuilder;

        return match ($action) {
            'exp' => $this->handleExperience($user, $value, $locale, $builder, $callbackQuery),
            'risk' => $this->handleRisk($user, $value, $locale, $builder, $callbackQuery),
            'step1' => $this->handleNext($user, $locale, $builder, $callbackQuery),
            default => $this->answerWithError('Invalid action'),
        };
    }

    private function handleExperience(User $user, string $level, string $locale, OnboardingKeyboardBuilder $builder, object $callbackQuery): mixed
    {
        if (! in_array($level, self::EXPERIENCE_LEVELS, true)) {
            return $this->answerWithError('Invalid experience level');
        }

        $user->update(['experience_level' => $level]);

        Log::info('Onboarding: Experience set via Telegram', [
            'user_id' => $user->id,
            'experience_level' => $level,
        ]);

        // Update keyboard with new selection
        $this->updateStep1Keyboard($user, $locale, $builder, $callbackQuery);

        $labels = [
            'beginner' => $locale === 'ar' ? 'مبتدئ' : 'Beginner',
            'intermediate' => $locale === 'ar' ? 'متوسط' : 'Intermediate',
            'advanced' => $locale === 'ar' ? 'متقدم' : 'Advanced',
        ];

        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم اختيار: {$labels[$level]}"
                : "✓ Selected: {$labels[$level]}",
            'show_alert' => false,
        ]);

        return null;
    }

    private function handleRisk(User $user, string $level, string $locale, OnboardingKeyboardBuilder $builder, object $callbackQuery): mixed
    {
        if (! in_array($level, self::RISK_LEVELS, true)) {
            return $this->answerWithError('Invalid risk level');
        }

        $user->update(['risk_level' => $level]);

        Log::info('Onboarding: Risk level set via Telegram', [
            'user_id' => $user->id,
            'risk_level' => $level,
        ]);

        // Update keyboard with new selection
        $this->updateStep1Keyboard($user, $locale, $builder, $callbackQuery);

        $labels = [
            'conservative' => $locale === 'ar' ? 'محافظ' : 'Conservative',
            'moderate' => $locale === 'ar' ? 'متوازن' : 'Moderate',
            'aggressive' => $locale === 'ar' ? 'مخاطر' : 'Aggressive',
        ];

        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم اختيار: {$labels[$level]}"
                : "✓ Selected: {$labels[$level]}",
            'show_alert' => false,
        ]);

        return null;
    }

    private function handleNext(User $user, string $locale, OnboardingKeyboardBuilder $builder, object $callbackQuery): mixed
    {
        // Validate step 1 is complete
        if (! $user->experience_level || ! $user->risk_level) {
            return $this->answerWithError(
                $locale === 'ar'
                    ? 'يرجى اختيار مستوى الخبرة والمخاطر أولا'
                    : 'Please select experience and risk level first'
            );
        }

        // Move to step 2
        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        $selected = $builder->getSelectedValues($user, 2);

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage(2, $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep2Keyboard($locale, $selected),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function updateStep1Keyboard(User $user, string $locale, OnboardingKeyboardBuilder $builder, object $callbackQuery): void
    {
        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        // Refresh user data
        $user->refresh();

        $selected = $builder->getSelectedValues($user, 1);

        $this->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep1Keyboard($locale, $selected),
            ],
        ]);
    }

    private function answerWithError(string $message): null
    {
        $this->answerCallbackQuery([
            'text' => $message,
            'show_alert' => true,
        ]);

        return null;
    }
}
