<?php

namespace App\Telegram\Handlers\Onboarding;

use App\Models\User;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Step 1: Experience (1a) & Risk (1b) level selection.
 *
 * Callback patterns:
 * - ob:exp:{level} - Set experience level, then auto-advance to risk
 * - ob:risk:{level} - Set risk level, then auto-advance to step 2
 * - ob:step1b:back - Go back from risk to experience
 */
class Step1Handler extends CallbackHandler
{
    protected string $match = '/^ob:(exp|risk|step1b):/';

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
        $value = $parts[2] ?? null;

        $locale = $user->language ?? 'en';
        $builder = new OnboardingKeyboardBuilder;

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        return match ($action) {
            'exp' => $this->handleExperience($user, $value, $locale, $builder, $chatId, $messageId),
            'risk' => $this->handleRisk($user, $value, $locale, $builder, $chatId, $messageId),
            'step1b' => $this->handleBack($user, $locale, $builder, $chatId, $messageId),
            default => $this->answerWithError('Invalid action'),
        };
    }

    private function handleExperience(User $user, ?string $level, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        if (! in_array($level, self::EXPERIENCE_LEVELS, true)) {
            return $this->answerWithError('Invalid experience level');
        }

        $user->update(['experience_level' => $level]);

        Log::info('Onboarding: Experience set via Telegram', [
            'user_id' => $user->id,
            'experience_level' => $level,
        ]);

        // Auto-advance to step 1b (risk)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('1b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep1bKeyboard($locale, $user->risk_level),
            ],
        ]);

        $labels = [
            'beginner' => $locale === 'ar' ? 'مبتدئ' : 'Beginner',
            'intermediate' => $locale === 'ar' ? 'متوسط' : 'Intermediate',
            'advanced' => $locale === 'ar' ? 'متقدم' : 'Advanced',
        ];

        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ {$labels[$level]}"
                : "✓ {$labels[$level]}",
            'show_alert' => false,
        ]);

        return null;
    }

    private function handleRisk(User $user, ?string $level, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        if (! in_array($level, self::RISK_LEVELS, true)) {
            return $this->answerWithError('Invalid risk level');
        }

        $user->update(['risk_level' => $level]);

        Log::info('Onboarding: Risk level set via Telegram', [
            'user_id' => $user->id,
            'risk_level' => $level,
        ]);

        // Auto-advance to step 2a (goal)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('2a', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep2aKeyboard($locale, $user->investment_goal),
            ],
        ]);

        $labels = [
            'conservative' => $locale === 'ar' ? 'محافظ' : 'Conservative',
            'moderate' => $locale === 'ar' ? 'متوازن' : 'Moderate',
            'aggressive' => $locale === 'ar' ? 'مخاطر' : 'Aggressive',
        ];

        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ {$labels[$level]}"
                : "✓ {$labels[$level]}",
            'show_alert' => false,
        ]);

        return null;
    }

    private function handleBack(User $user, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        // Go back to step 1a (experience)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('1a', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep1aKeyboard($locale, $user->experience_level),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
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
