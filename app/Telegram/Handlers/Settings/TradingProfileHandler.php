<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\User;
use App\Telegram\Services\SettingsKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Trading Profile settings.
 *
 * Callback patterns:
 * - set:trading - Show trading settings
 * - set:trading:exp:{level} - Change experience level
 * - set:trading:risk:{level} - Change risk level
 * - set:trading:goal:{goal} - Change investment goal
 * - set:trading:goal:page:{n} - Paginate goals
 * - set:trading:style:{style} - Change trading style
 */
class TradingProfileHandler extends CallbackHandler
{
    protected string $match = '/^set:trading/';

    private const EXPERIENCE_LEVELS = ['beginner', 'intermediate', 'advanced'];

    private const RISK_LEVELS = ['conservative', 'moderate', 'aggressive'];

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
        $action = $parts[2] ?? null;
        $value = $parts[3] ?? null;
        $extra = $parts[4] ?? null;
        $locale = $user->language ?? 'en';

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        // Show main trading settings view
        if ($action === null) {
            return $this->showTradingSettings($chatId, $messageId, $user, $locale);
        }

        return match ($action) {
            'exp' => $this->handleExperience($user, $value, $chatId, $messageId, $locale),
            'risk' => $this->handleRisk($user, $value, $chatId, $messageId, $locale),
            'goal' => $this->handleGoal($user, $value, $extra, $chatId, $messageId, $locale),
            'style' => $this->handleStyle($user, $value, $chatId, $messageId, $locale),
            default => $this->showTradingSettings($chatId, $messageId, $user, $locale),
        };
    }

    private function showTradingSettings(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'ما الذي تريد تغييره؟'
            : 'What would you like to change?';

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildTradingMenu($user, $locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleExperience(User $user, ?string $level, int $chatId, int $messageId, string $locale): mixed
    {
        // Show experience selection screen
        if ($level === 'select') {
            return $this->showExperienceSelector($chatId, $messageId, $user, $locale);
        }

        if (! in_array($level, self::EXPERIENCE_LEVELS, true)) {
            return $this->answerWithError('Invalid experience level');
        }

        $user->update(['experience_level' => $level]);

        Log::info('Settings: Experience updated via Telegram', [
            'user_id' => $user->id,
            'experience_level' => $level,
        ]);

        $builder = new SettingsKeyboardBuilder;
        $label = $builder->getExperienceLabel($level, $locale);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم التحديث: {$label}"
                : "✓ Updated: {$label}",
            'show_alert' => false,
        ]);

        return $this->showTradingSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function showExperienceSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'ما هو مستوى خبرتك في التداول؟'
            : "What's your trading experience?";

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildExperienceSelector($user->experience_level, $locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleRisk(User $user, ?string $level, int $chatId, int $messageId, string $locale): mixed
    {
        // Show risk selection screen
        if ($level === 'select') {
            return $this->showRiskSelector($chatId, $messageId, $user, $locale);
        }

        if (! in_array($level, self::RISK_LEVELS, true)) {
            return $this->answerWithError('Invalid risk level');
        }

        $user->update(['risk_level' => $level]);

        Log::info('Settings: Risk level updated via Telegram', [
            'user_id' => $user->id,
            'risk_level' => $level,
        ]);

        $builder = new SettingsKeyboardBuilder;
        $label = $builder->getRiskLabel($level, $locale);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم التحديث: {$label}"
                : "✓ Updated: {$label}",
            'show_alert' => false,
        ]);

        return $this->showTradingSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function showRiskSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'ما هو مستوى تحملك للمخاطر؟'
            : "What's your risk tolerance?";

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildRiskSelector($user->risk_level, $locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleGoal(User $user, ?string $value, ?string $extra, int $chatId, int $messageId, string $locale): mixed
    {
        // Show goal selection screen
        if ($value === 'select') {
            return $this->showGoalSelector($chatId, $messageId, $user, $locale);
        }

        // Handle pagination
        if ($value === 'page') {
            $page = (int) ($extra ?? 1);

            return $this->showGoalSelector($chatId, $messageId, $user, $locale, $page);
        }

        // Handle goal selection
        if (! in_array($value, self::INVESTMENT_GOALS, true)) {
            return $this->answerWithError('Invalid investment goal');
        }

        $user->update(['investment_goal' => $value]);

        Log::info('Settings: Investment goal updated via Telegram', [
            'user_id' => $user->id,
            'investment_goal' => $value,
        ]);

        $builder = new SettingsKeyboardBuilder;
        $label = $builder->getGoalLabel($value, $locale);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم التحديث: {$label}"
                : "✓ Updated: {$label}",
            'show_alert' => false,
        ]);

        return $this->showTradingSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function handleStyle(User $user, ?string $style, int $chatId, int $messageId, string $locale): mixed
    {
        // Show style selection screen
        if ($style === 'select') {
            return $this->showStyleSelector($chatId, $messageId, $user, $locale);
        }

        if (! in_array($style, self::TRADING_STYLES, true)) {
            return $this->answerWithError('Invalid trading style');
        }

        $user->update(['trading_style' => $style]);

        Log::info('Settings: Trading style updated via Telegram', [
            'user_id' => $user->id,
            'trading_style' => $style,
        ]);

        $builder = new SettingsKeyboardBuilder;
        $label = $builder->getStyleLabel($style, $locale);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم التحديث: {$label}"
                : "✓ Updated: {$label}",
            'show_alert' => false,
        ]);

        return $this->showTradingSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function showStyleSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'ما هو أسلوب التداول المفضل لديك؟'
            : "What's your trading style?";

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStyleSelector($user->trading_style, $locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function showGoalSelector(int $chatId, int $messageId, User $user, string $locale, int $page = 1): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'ما هو هدفك الاستثماري؟'
            : "What's your investment goal?";

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildGoalSelector($user->investment_goal, $locale, $page),
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
