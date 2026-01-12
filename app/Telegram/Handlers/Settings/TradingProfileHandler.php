<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\User;
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
        $expLabels = $this->getExperienceLabels($locale);
        $riskLabels = $this->getRiskLabels($locale);
        $goalLabels = $this->getGoalLabels($locale);
        $styleLabels = $this->getStyleLabels($locale);

        $exp = $expLabels[$user->experience_level] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
        $risk = $riskLabels[$user->risk_level] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
        $goal = $goalLabels[$user->investment_goal] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');
        $style = $styleLabels[$user->trading_style] ?? ($locale === 'ar' ? 'غير محدد' : 'Not set');

        // Simple question: What would you like to change?
        $text = $locale === 'ar'
            ? 'ما الذي تريد تغييره؟'
            : 'What would you like to change?';

        // Menu buttons - each leads to a single question
        $keyboard = [
            [[
                'text' => $locale === 'ar' ? "🎯 الخبرة: {$exp}" : "🎯 Experience: {$exp}",
                'callback_data' => 'set:trading:exp:select',
            ]],
            [[
                'text' => $locale === 'ar' ? "⚖️ المخاطر: {$risk}" : "⚖️ Risk: {$risk}",
                'callback_data' => 'set:trading:risk:select',
            ]],
            [[
                'text' => $locale === 'ar' ? "📈 الهدف: {$goal}" : "📈 Goal: {$goal}",
                'callback_data' => 'set:trading:goal:select',
            ]],
            [[
                'text' => $locale === 'ar' ? "📊 الأسلوب: {$style}" : "📊 Style: {$style}",
                'callback_data' => 'set:trading:style:select',
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'set:menu',
            ]],
        ];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
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

        $labels = $this->getExperienceLabels($locale);
        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم التحديث: {$labels[$level]}"
                : "✓ Updated: {$labels[$level]}",
            'show_alert' => false,
        ]);

        return $this->showTradingSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function showExperienceSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? 'ما هو مستوى خبرتك في التداول؟'
            : "What's your trading experience?";

        $labels = $this->getExperienceLabels($locale);
        $icons = ['beginner' => '🌱', 'intermediate' => '📈', 'advanced' => '🎯'];

        $keyboard = [];
        foreach (self::EXPERIENCE_LEVELS as $level) {
            $isSelected = $user->experience_level === $level;
            $icon = $icons[$level];
            $label = $labels[$level];
            $keyboard[] = [[
                'text' => $isSelected ? "✓ {$icon} {$label}" : "{$icon} {$label}",
                'callback_data' => "set:trading:exp:{$level}",
            ]];
        }

        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:trading',
        ]];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
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

        $labels = $this->getRiskLabels($locale);
        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم التحديث: {$labels[$level]}"
                : "✓ Updated: {$labels[$level]}",
            'show_alert' => false,
        ]);

        return $this->showTradingSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function showRiskSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? 'ما هو مستوى تحملك للمخاطر؟'
            : "What's your risk tolerance?";

        $labels = $this->getRiskLabels($locale);
        $icons = ['conservative' => '🛡️', 'moderate' => '⚖️', 'aggressive' => '🚀'];

        $keyboard = [];
        foreach (self::RISK_LEVELS as $level) {
            $isSelected = $user->risk_level === $level;
            $icon = $icons[$level];
            $label = $labels[$level];
            $keyboard[] = [[
                'text' => $isSelected ? "✓ {$icon} {$label}" : "{$icon} {$label}",
                'callback_data' => "set:trading:risk:{$level}",
            ]];
        }

        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:trading',
        ]];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
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

        $labels = $this->getGoalLabels($locale);
        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم التحديث: {$labels[$value]}"
                : "✓ Updated: {$labels[$value]}",
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

        $labels = $this->getStyleLabels($locale);
        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✓ تم التحديث: {$labels[$style]}"
                : "✓ Updated: {$labels[$style]}",
            'show_alert' => false,
        ]);

        return $this->showTradingSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function showStyleSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? 'ما هو أسلوب التداول المفضل لديك؟'
            : "What's your trading style?";

        $labels = $this->getStyleLabels($locale);
        $icons = [
            'day_trading' => '📊',
            'swing_trading' => '🔄',
            'position_trading' => '📅',
            'scalping_trading' => '⚡',
        ];

        $keyboard = [];
        foreach (self::TRADING_STYLES as $style) {
            $isSelected = $user->trading_style === $style;
            $icon = $icons[$style];
            $label = $labels[$style];
            $keyboard[] = [[
                'text' => $isSelected ? "✓ {$icon} {$label}" : "{$icon} {$label}",
                'callback_data' => "set:trading:style:{$style}",
            ]];
        }

        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:trading',
        ]];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function showGoalSelector(int $chatId, int $messageId, User $user, string $locale, int $page = 1): mixed
    {
        $text = $locale === 'ar'
            ? 'ما هو هدفك الاستثماري؟'
            : "What's your investment goal?";

        $goalLabels = $this->getGoalLabels($locale);
        $goalIcons = [
            'capital_growth' => '📈',
            'fixed_income' => '💰',
            'risk_reduction' => '🛡️',
            'short_term_speculation' => '⚡',
            'retirement_planning' => '🏖️',
            'wealth_preservation' => '🏛️',
            'passive_income' => '💸',
            'education_savings' => '🎓',
            'home_purchase' => '🏠',
            'emergency_fund' => '🆘',
        ];

        $perPage = 6;
        $totalPages = (int) ceil(count(self::INVESTMENT_GOALS) / $perPage);
        $offset = ($page - 1) * $perPage;
        $pageGoals = array_slice(self::INVESTMENT_GOALS, $offset, $perPage);

        $keyboard = [];

        // Goals in rows of 2
        $rows = array_chunk($pageGoals, 2);
        foreach ($rows as $row) {
            $btns = [];
            foreach ($row as $goal) {
                $icon = $goalIcons[$goal];
                $label = $goalLabels[$goal];
                $isSelected = $user->investment_goal === $goal;
                $btns[] = [
                    'text' => $isSelected ? "✓ {$icon} {$label}" : "{$icon} {$label}",
                    'callback_data' => "set:trading:goal:{$goal}",
                ];
            }
            $keyboard[] = $btns;
        }

        // Pagination
        if ($totalPages > 1) {
            $navRow = [];
            if ($page > 1) {
                $navRow[] = [
                    'text' => '◀️',
                    'callback_data' => 'set:trading:goal:page:'.($page - 1),
                ];
            }
            $navRow[] = [
                'text' => "{$page}/{$totalPages}",
                'callback_data' => 'noop',
            ];
            if ($page < $totalPages) {
                $navRow[] = [
                    'text' => '▶️',
                    'callback_data' => 'set:trading:goal:page:'.($page + 1),
                ];
            }
            $keyboard[] = $navRow;
        }

        // Back button
        $keyboard[] = [[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'set:trading',
        ]];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function getExperienceLabels(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'beginner' => 'مبتدئ',
                'intermediate' => 'متوسط',
                'advanced' => 'متقدم',
            ];
        }

        return [
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
        ];
    }

    private function getRiskLabels(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'conservative' => 'محافظ',
                'moderate' => 'متوازن',
                'aggressive' => 'مخاطر',
            ];
        }

        return [
            'conservative' => 'Conservative',
            'moderate' => 'Moderate',
            'aggressive' => 'Aggressive',
        ];
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
                'day_trading' => 'يومي',
                'swing_trading' => 'متأرجح',
                'position_trading' => 'مركز',
                'scalping_trading' => 'سريع',
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
