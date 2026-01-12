<?php

namespace App\Telegram\Handlers\Onboarding;

use App\Models\User;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Step 2: Investment Goal (2a) & Trading Style (2b) selection.
 *
 * Callback patterns:
 * - ob:goal:{goal} - Set investment goal, then auto-advance to style
 * - ob:goal:page:{n} - Change goal pagination page
 * - ob:style:{style} - Set trading style, then auto-advance to step 3
 * - ob:step2a:back - Go back from goal to risk (step 1b)
 * - ob:step2b:back - Go back from style to goal (step 2a)
 */
class Step2Handler extends CallbackHandler
{
    protected string $match = '/^ob:(goal|style|step2a|step2b):/';

    private const INVESTMENT_GOALS = [
        'capital_growth', 'fixed_income', 'risk_reduction', 'short_term_speculation',
        'retirement_planning', 'wealth_preservation', 'passive_income',
        'education_savings', 'home_purchase', 'emergency_fund',
    ];

    private const TRADING_STYLES = ['day_trading', 'swing_trading', 'position_trading', 'scalping_trading'];

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
        $extra = $parts[3] ?? null;

        $locale = $user->language ?? 'en';
        $builder = new OnboardingKeyboardBuilder;

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        return match ($action) {
            'goal' => $this->handleGoal($user, $value, $extra, $locale, $builder, $chatId, $messageId),
            'style' => $this->handleStyle($user, $value, $locale, $builder, $chatId, $messageId),
            'step2a' => $this->handleBackToRisk($user, $locale, $builder, $chatId, $messageId),
            'step2b' => $this->handleBackToGoal($user, $locale, $builder, $chatId, $messageId),
            default => $this->answerWithError('Invalid action'),
        };
    }

    private function handleGoal(User $user, ?string $value, ?string $extra, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        // Handle pagination
        if ($value === 'page' && $extra !== null) {
            $page = (int) $extra;

            $this->editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => [
                    'inline_keyboard' => $builder->buildStep2aKeyboard($locale, $user->investment_goal, $page),
                ],
            ]);

            $this->answerCallbackQuery(['text' => '']);

            return null;
        }

        // Handle noop
        if ($value === 'noop' || $value === null) {
            $this->answerCallbackQuery(['text' => '']);

            return null;
        }

        // Handle goal selection
        if (! in_array($value, self::INVESTMENT_GOALS, true)) {
            return $this->answerWithError('Invalid investment goal');
        }

        $user->update(['investment_goal' => $value]);

        Log::info('Onboarding: Investment goal set via Telegram', [
            'user_id' => $user->id,
            'investment_goal' => $value,
        ]);

        // Auto-advance to step 2b (style)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('2b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep2bKeyboard($locale, $user->trading_style),
            ],
        ]);

        $labels = $this->getGoalLabels($locale);

        $this->answerCallbackQuery([
            'text' => "✓ {$labels[$value]}",
            'show_alert' => false,
        ]);

        return null;
    }

    private function handleStyle(User $user, ?string $value, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        if (! in_array($value, self::TRADING_STYLES, true)) {
            return $this->answerWithError('Invalid trading style');
        }

        $user->update(['trading_style' => $value]);

        Log::info('Onboarding: Trading style set via Telegram', [
            'user_id' => $user->id,
            'trading_style' => $value,
        ]);

        // Auto-advance to step 3a (country)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('3a', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep3CountryKeyboard($locale, $user->country_id),
            ],
        ]);

        $labels = $this->getStyleLabels($locale);

        $this->answerCallbackQuery([
            'text' => "✓ {$labels[$value]}",
            'show_alert' => false,
        ]);

        return null;
    }

    private function handleBackToRisk(User $user, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        // Go back to step 1b (risk)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('1b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep1bKeyboard($locale, $user->risk_level),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleBackToGoal(User $user, string $locale, OnboardingKeyboardBuilder $builder, int $chatId, int $messageId): mixed
    {
        // Go back to step 2a (goal)
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('2a', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep2aKeyboard($locale, $user->investment_goal),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function getGoalLabels(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'capital_growth' => 'نمو رأس المال',
                'fixed_income' => 'دخل ثابت',
                'risk_reduction' => 'تقليل المخاطر',
                'short_term_speculation' => 'مضاربة',
                'retirement_planning' => 'تقاعد',
                'wealth_preservation' => 'حفظ الثروة',
                'passive_income' => 'دخل سلبي',
                'education_savings' => 'تعليم',
                'home_purchase' => 'شراء منزل',
                'emergency_fund' => 'طوارئ',
            ];
        }

        return [
            'capital_growth' => 'Capital Growth',
            'fixed_income' => 'Fixed Income',
            'risk_reduction' => 'Risk Reduction',
            'short_term_speculation' => 'Speculation',
            'retirement_planning' => 'Retirement',
            'wealth_preservation' => 'Wealth Preservation',
            'passive_income' => 'Passive Income',
            'education_savings' => 'Education',
            'home_purchase' => 'Home Purchase',
            'emergency_fund' => 'Emergency Fund',
        ];
    }

    private function getStyleLabels(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'day_trading' => 'تداول يومي',
                'swing_trading' => 'تداول متأرجح',
                'position_trading' => 'تداول مركز',
                'scalping_trading' => 'تداول سريع',
            ];
        }

        return [
            'day_trading' => 'Day Trading',
            'swing_trading' => 'Swing Trading',
            'position_trading' => 'Position Trading',
            'scalping_trading' => 'Scalping',
        ];
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
