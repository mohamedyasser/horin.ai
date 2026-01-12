<?php

namespace App\Telegram\Handlers\Buttons;

use App\Models\User;
use App\Telegram\Keyboards\TradingKeyboard;

class TradingButtonHandler extends AbstractButtonHandler
{
    public static function supportedActions(): array
    {
        return [
            'trading_experience',
            'trading_risk',
            'trading_goal',
            'trading_style',
            'set_experience_beginner',
            'set_experience_intermediate',
            'set_experience_advanced',
            'set_risk_conservative',
            'set_risk_moderate',
            'set_risk_aggressive',
            'set_goal_capital_growth',
            'set_goal_fixed_income',
            'set_goal_risk_reduction',
            'set_goal_short_term',
            'set_style_day_trading',
            'set_style_swing_trading',
            'set_style_position_trading',
            'set_style_scalping',
        ];
    }

    public function showExperienceSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "📈 *مستوى الخبرة*\n\nما هو مستوى خبرتك في التداول؟"
            : "📈 *Experience Level*\n\nWhat's your trading experience?";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => TradingKeyboard::experienceKeyboard($locale),
        ]);
    }

    public function showRiskSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "⚠️ *مستوى المخاطرة*\n\nما هو مستوى تحملك للمخاطر؟"
            : "⚠️ *Risk Level*\n\nWhat's your risk tolerance?";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => TradingKeyboard::riskKeyboard($locale),
        ]);
    }

    public function showGoalSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "🎯 *الهدف الاستثماري*\n\nما هو هدفك الاستثماري؟"
            : "🎯 *Investment Goal*\n\nWhat's your investment goal?";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => TradingKeyboard::goalKeyboard($locale),
        ]);
    }

    public function showStyleSelector(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "📊 *أسلوب التداول*\n\nما هو أسلوب التداول المفضل لديك؟"
            : "📊 *Trading Style*\n\nWhat's your preferred trading style?";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => TradingKeyboard::styleKeyboard($locale),
        ]);
    }

    // Experience setters
    public function setExperienceBeginner(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setExperience($chatId, $user, $locale, 'beginner');
    }

    public function setExperienceIntermediate(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setExperience($chatId, $user, $locale, 'intermediate');
    }

    public function setExperienceAdvanced(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setExperience($chatId, $user, $locale, 'advanced');
    }

    private function setExperience(int $chatId, ?User $user, string $locale, string $level): mixed
    {
        if (! $user) {
            return (new NavigationButtonHandler($this->bot, $this->update))
                ->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['experience_level' => $level]);

        $labels = [
            'beginner' => ['en' => 'Beginner', 'ar' => 'مبتدئ'],
            'intermediate' => ['en' => 'Intermediate', 'ar' => 'متوسط'],
            'advanced' => ['en' => 'Advanced', 'ar' => 'متقدم'],
        ];

        $label = $labels[$level][$locale] ?? $level;

        $text = $locale === 'ar'
            ? "✅ تم تحديث مستوى الخبرة إلى: {$label}"
            : "✅ Experience level updated to: {$label}";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => TradingKeyboard::menu($locale),
        ]);
    }

    // Risk setters
    public function setRiskConservative(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setRiskLevel($chatId, $user, $locale, 'conservative');
    }

    public function setRiskModerate(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setRiskLevel($chatId, $user, $locale, 'moderate');
    }

    public function setRiskAggressive(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setRiskLevel($chatId, $user, $locale, 'aggressive');
    }

    private function setRiskLevel(int $chatId, ?User $user, string $locale, string $level): mixed
    {
        if (! $user) {
            return (new NavigationButtonHandler($this->bot, $this->update))
                ->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['risk_level' => $level]);

        $labels = [
            'conservative' => ['en' => 'Conservative', 'ar' => 'محافظ'],
            'moderate' => ['en' => 'Moderate', 'ar' => 'معتدل'],
            'aggressive' => ['en' => 'Aggressive', 'ar' => 'مغامر'],
        ];

        $label = $labels[$level][$locale] ?? $level;

        $text = $locale === 'ar'
            ? "✅ تم تحديث مستوى المخاطرة إلى: {$label}"
            : "✅ Risk level updated to: {$label}";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => TradingKeyboard::menu($locale),
        ]);
    }

    // Goal setters
    public function setGoalCapitalGrowth(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setGoal($chatId, $user, $locale, 'capital_growth');
    }

    public function setGoalFixedIncome(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setGoal($chatId, $user, $locale, 'fixed_income');
    }

    public function setGoalRiskReduction(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setGoal($chatId, $user, $locale, 'risk_reduction');
    }

    public function setGoalShortTerm(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setGoal($chatId, $user, $locale, 'short_term_speculation');
    }

    private function setGoal(int $chatId, ?User $user, string $locale, string $goal): mixed
    {
        if (! $user) {
            return (new NavigationButtonHandler($this->bot, $this->update))
                ->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['investment_goal' => $goal]);

        $labels = [
            'capital_growth' => ['en' => 'Capital Growth', 'ar' => 'نمو رأس المال'],
            'fixed_income' => ['en' => 'Fixed Income', 'ar' => 'دخل ثابت'],
            'risk_reduction' => ['en' => 'Risk Reduction', 'ar' => 'تقليل المخاطر'],
            'short_term_speculation' => ['en' => 'Short-term Speculation', 'ar' => 'مضاربة قصيرة'],
        ];

        $label = $labels[$goal][$locale] ?? $goal;

        $text = $locale === 'ar'
            ? "✅ تم تحديث الهدف الاستثماري إلى: {$label}"
            : "✅ Investment goal updated to: {$label}";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => TradingKeyboard::menu($locale),
        ]);
    }

    // Style setters
    public function setStyleDayTrading(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setStyle($chatId, $user, $locale, 'day_trading');
    }

    public function setStyleSwingTrading(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setStyle($chatId, $user, $locale, 'swing_trading');
    }

    public function setStylePositionTrading(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setStyle($chatId, $user, $locale, 'position_trading');
    }

    public function setStyleScalping(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setStyle($chatId, $user, $locale, 'scalping_trading');
    }

    private function setStyle(int $chatId, ?User $user, string $locale, string $style): mixed
    {
        if (! $user) {
            return (new NavigationButtonHandler($this->bot, $this->update))
                ->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['trading_style' => $style]);

        $labels = [
            'day_trading' => ['en' => 'Day Trading', 'ar' => 'تداول يومي'],
            'swing_trading' => ['en' => 'Swing Trading', 'ar' => 'تداول متأرجح'],
            'position_trading' => ['en' => 'Position Trading', 'ar' => 'تداول مراكز'],
            'scalping_trading' => ['en' => 'Scalping', 'ar' => 'سكالبينج'],
        ];

        $label = $labels[$style][$locale] ?? $style;

        $text = $locale === 'ar'
            ? "✅ تم تحديث أسلوب التداول إلى: {$label}"
            : "✅ Trading style updated to: {$label}";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => TradingKeyboard::menu($locale),
        ]);
    }
}
