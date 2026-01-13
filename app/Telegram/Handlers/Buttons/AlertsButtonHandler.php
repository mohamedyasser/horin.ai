<?php

namespace App\Telegram\Handlers\Buttons;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use App\Telegram\Commands\AlertsCommand;
use App\Telegram\Keyboards\AlertsKeyboard;
use App\Telegram\Keyboards\MainMenuKeyboard;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AlertsButtonHandler extends AbstractButtonHandler
{
    public static function supportedActions(): array
    {
        return [
            'alerts',
            'new_alert',
            'alerts_list',
            'alerts_history',
            'alert_type_price',
            'alert_type_signal',
            'alert_type_prediction',
            'alert_type_anomaly',
            'alert_type_pattern',
            'alert_type_recommendation',
            'alert_trigger_target_price',
            'alert_trigger_daily_change',
            'alert_trigger_breakout',
            'alert_direction_above',
            'alert_direction_below',
            'alert_direction_both',
            'alert_confirm_create',
            'alert_snooze',
            'alert_unsnooze',
            'alert_pause',
            'alert_resume',
            'alert_delete',
            'alert_delete_confirm',
            'snooze_1h',
            'snooze_4h',
            'snooze_1d',
            'snooze_market_close',
            'alert_search_asset',
            // Anomaly types
            'anomaly_volume',
            'anomaly_price',
            'anomaly_volatility',
            'anomaly_all',
            // Pattern types
            'pattern_head_shoulders',
            'pattern_double',
            'pattern_triangle',
            'pattern_flag',
            'pattern_all',
            // Recommendation types
            'rec_strong_buy',
            'rec_buy',
            'rec_sell',
            'rec_strong_sell',
            'rec_any_change',
            // Confidence levels
            'confidence_high',
            'confidence_medium',
            'confidence_low',
        ];
    }

    public function showAlerts(int $chatId, ?User $user, string $locale): mixed
    {
        $handler = new AlertsCommand($this->bot, $this->update);

        return $handler->handle();
    }

    public function createNewAlert(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            $text = $locale === 'ar'
                ? '❌ يرجى التسجيل أولاً باستخدام /start'
                : '❌ Please register first using /start';

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        }

        $user->update(['telegram_alert_draft' => ['step' => 'type']]);

        $text = $locale === 'ar'
            ? "📊 *إنشاء تنبيه*\n\nما نوع التنبيه؟"
            : "📊 *Create Alert*\n\nWhat type of alert?";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertTypeKeyboard($locale),
        ]);
    }

    public function showAlertsList(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $alerts = Alert::where('user_id', $user->id)
            ->active()
            ->with('asset')
            ->take(10)
            ->get();

        if ($alerts->isEmpty()) {
            $text = $locale === 'ar'
                ? "📋 *تنبيهاتك*\n\nلا توجد تنبيهات نشطة.\n\nاضغط '➕ تنبيه جديد' لإنشاء تنبيه."
                : "📋 *Your Alerts*\n\nNo active alerts.\n\nTap '➕ New Alert' to create one.";

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);
        }

        $triggerLabels = [
            'target_price' => 'Price',
            'daily_change' => 'Change',
            'breakout' => 'Breakout',
        ];

        $alertLines = $alerts->map(function ($alert) use ($triggerLabels) {
            // Escape underscores in symbol for Markdown
            $symbol = str_replace('_', '\\_', $alert->asset?->symbol ?? 'N/A');
            $direction = $alert->direction ?? 'any';
            $params = $alert->parameters ?? [];
            $value = $params['target_price'] ?? $params['threshold_percent'] ?? null;

            $directionIcon = match ($direction) {
                'above' => '⬆️',
                'below' => '⬇️',
                'both' => '↕️',
                default => '📊',
            };

            if ($value !== null) {
                $valueStr = is_numeric($value) ? number_format((float) $value, 2) : $value;
                if (isset($params['threshold_percent'])) {
                    $valueStr .= '%';
                }

                return "• {$symbol}: {$directionIcon} {$valueStr}";
            }

            // Use label instead of raw trigger_type to avoid underscores
            $triggerLabel = $triggerLabels[$alert->trigger_type] ?? str_replace('_', ' ', $alert->trigger_type);

            return "• {$symbol}: {$directionIcon} {$triggerLabel}";
        })->join("\n");

        $tapHint = $locale === 'ar'
            ? "\n\n👆 اضغط على تنبيه لإدارته:"
            : "\n\n👆 Tap an alert to manage it:";

        $text = $locale === 'ar'
            ? "📋 *تنبيهاتك النشطة*\n\n{$alertLines}{$tapHint}"
            : "📋 *Your Active Alerts*\n\n{$alertLines}{$tapHint}";

        // Build inline keyboard with alert buttons
        $inlineKeyboard = $alerts->map(function ($alert) {
            $symbol = $alert->asset?->symbol ?? 'N/A';
            $direction = $alert->direction ?? 'both';
            $dirIcon = match ($direction) {
                'above' => '⬆️',
                'below' => '⬇️',
                'both' => '↕️',
                default => '📊',
            };

            $params = $alert->parameters ?? [];
            $value = $params['target_price'] ?? $params['threshold_percent'] ?? null;
            $valueStr = $value !== null ? number_format((float) $value, 2) : '';
            if (isset($params['threshold_percent']) && $value !== null) {
                $valueStr .= '%';
            }

            $buttonText = $valueStr ? "{$symbol} {$dirIcon} {$valueStr}" : "{$symbol} {$dirIcon}";

            return [['text' => $buttonText, 'callback_data' => "alert_manage:{$alert->id}"]];
        })->toArray();

        // Send message with inline keyboard for alert selection
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => ['inline_keyboard' => $inlineKeyboard],
        ]);

        // Also update the reply keyboard for navigation
        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $locale === 'ar' ? '⬇️ القائمة' : '⬇️ Menu',
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);
    }

    public function showAlertsHistory(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $alerts = Alert::where('user_id', $user->id)
            ->whereNotNull('last_triggered_at')
            ->orderBy('last_triggered_at', 'desc')
            ->with('asset')
            ->take(10)
            ->get();

        if ($alerts->isEmpty()) {
            $text = $locale === 'ar'
                ? "📜 *سجل التنبيهات*\n\nلا يوجد سجل تنبيهات."
                : "📜 *Alert History*\n\nNo alert history.";
        } else {
            $alertLines = $alerts->map(function ($alert) {
                // Escape underscores in symbol for Markdown
                $symbol = str_replace('_', '\\_', $alert->asset?->symbol ?? 'N/A');
                $date = $alert->last_triggered_at?->format('M d, H:i') ?? 'N/A';

                return "• {$symbol} - {$date}";
            })->join("\n");

            $text = $locale === 'ar'
                ? "📜 *سجل التنبيهات*\n\n{$alertLines}"
                : "📜 *Alert History*\n\n{$alertLines}";
        }

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);
    }

    // Alert type selection
    public function selectAlertTypePrice(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->selectAlertType($chatId, $user, $locale, 'price');
    }

    public function selectAlertTypeSignal(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->selectAlertType($chatId, $user, $locale, 'signal');
    }

    public function selectAlertTypePrediction(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->selectAlertType($chatId, $user, $locale, 'prediction');
    }

    public function selectAlertTypeAnomaly(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->selectAIAlertType($chatId, $user, $locale, 'anomaly');
    }

    public function selectAlertTypePattern(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->selectAIAlertType($chatId, $user, $locale, 'pattern');
    }

    public function selectAlertTypeRecommendation(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->selectAIAlertType($chatId, $user, $locale, 'recommendation');
    }

    private function selectAIAlertType(int $chatId, ?User $user, string $locale, string $type): mixed
    {
        if (! $user) {
            $text = $locale === 'ar'
                ? '❌ يرجى التسجيل أولاً باستخدام /start'
                : '❌ Please register first using /start';

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['type'] = $type;
        $draft['trigger_type'] = $type;
        $draft['step'] = 'asset';
        $user->update([
            'telegram_alert_draft' => $draft,
            'telegram_awaiting_input' => 'alert_asset_search',
        ]);

        $typeLabels = [
            'anomaly' => ['en' => 'Anomaly Alert', 'ar' => 'تنبيه شذوذ', 'icon' => '⚠️'],
            'pattern' => ['en' => 'Pattern Alert', 'ar' => 'تنبيه نمط', 'icon' => '📊'],
            'recommendation' => ['en' => 'Recommendation Alert', 'ar' => 'تنبيه توصية', 'icon' => '💡'],
        ];

        $typeLabel = $typeLabels[$type][$locale] ?? $type;
        $typeIcon = $typeLabels[$type]['icon'] ?? '📊';

        $text = $locale === 'ar'
            ? "{$typeIcon} *{$typeLabel}*\n\n🔍 أدخل رمز أو اسم الأصل للبحث:"
            : "{$typeIcon} *{$typeLabel}*\n\n🔍 Enter asset symbol or name to search:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::cancelKeyboard($locale),
        ]);
    }

    private function selectAlertType(int $chatId, ?User $user, string $locale, string $type): mixed
    {
        if (! $user) {
            $text = $locale === 'ar'
                ? '❌ يرجى التسجيل أولاً باستخدام /start'
                : '❌ Please register first using /start';

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['type'] = $type;
        $draft['step'] = 'asset';
        $user->update([
            'telegram_alert_draft' => $draft,
            'telegram_awaiting_input' => 'alert_asset_search',
        ]);

        $typeLabels = [
            'price' => ['en' => 'Price Alert', 'ar' => 'تنبيه السعر', 'icon' => '💰'],
            'signal' => ['en' => 'Signal Alert', 'ar' => 'تنبيه إشارة', 'icon' => '📈'],
            'prediction' => ['en' => 'Prediction Alert', 'ar' => 'تنبيه توقع', 'icon' => '🔮'],
        ];

        $typeLabel = $typeLabels[$type][$locale] ?? $type;
        $typeIcon = $typeLabels[$type]['icon'] ?? '📊';

        $text = $locale === 'ar'
            ? "{$typeIcon} *{$typeLabel}*\n\n🔍 أدخل رمز أو اسم الأصل للبحث:"
            : "{$typeIcon} *{$typeLabel}*\n\n🔍 Enter asset symbol or name to search:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::cancelKeyboard($locale),
        ]);
    }

    // Trigger type setters
    public function setTriggerTargetPrice(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAlertTriggerType($chatId, $user, $locale, 'target_price');
    }

    public function setTriggerDailyChange(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAlertTriggerType($chatId, $user, $locale, 'daily_change');
    }

    public function setTriggerBreakout(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAlertTriggerType($chatId, $user, $locale, 'breakout');
    }

    // Anomaly type setters
    public function setAnomalyVolume(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAnomalyType($chatId, $user, $locale, 'volume');
    }

    public function setAnomalyPrice(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAnomalyType($chatId, $user, $locale, 'price');
    }

    public function setAnomalyVolatility(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAnomalyType($chatId, $user, $locale, 'volatility');
    }

    public function setAnomalyAll(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAnomalyType($chatId, $user, $locale, 'all');
    }

    private function setAnomalyType(int $chatId, ?User $user, string $locale, string $anomalyType): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['parameters']['anomaly_type'] = $anomalyType;
        $draft['step'] = 'confidence';
        $user->update(['telegram_alert_draft' => $draft]);

        $labels = [
            'volume' => ['en' => 'Volume Anomaly', 'ar' => 'حجم غير طبيعي'],
            'price' => ['en' => 'Price Anomaly', 'ar' => 'سعر غير طبيعي'],
            'volatility' => ['en' => 'Volatility Anomaly', 'ar' => 'تقلب غير طبيعي'],
            'all' => ['en' => 'All Types', 'ar' => 'جميع الأنواع'],
        ];

        $label = $labels[$anomalyType][$locale] ?? $anomalyType;
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');

        $text = $locale === 'ar'
            ? "⚠️ *تنبيه شذوذ*\n\n📊 الأصل: {$symbol}\n📈 النوع: {$label}\n\n🎯 اختر مستوى الثقة المطلوب:"
            : "⚠️ *Anomaly Alert*\n\n📊 Asset: {$symbol}\n📈 Type: {$label}\n\n🎯 Select minimum confidence level:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::confidenceLevelKeyboard($locale),
        ]);
    }

    // Pattern type setters
    public function setPatternHeadShoulders(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setPatternType($chatId, $user, $locale, 'head_shoulders');
    }

    public function setPatternDouble(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setPatternType($chatId, $user, $locale, 'double_top_bottom');
    }

    public function setPatternTriangle(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setPatternType($chatId, $user, $locale, 'triangle');
    }

    public function setPatternFlag(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setPatternType($chatId, $user, $locale, 'flag_pennant');
    }

    public function setPatternAll(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setPatternType($chatId, $user, $locale, 'all');
    }

    private function setPatternType(int $chatId, ?User $user, string $locale, string $patternType): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['parameters']['pattern_type'] = $patternType;
        $draft['step'] = 'confidence';
        $user->update(['telegram_alert_draft' => $draft]);

        $labels = [
            'head_shoulders' => ['en' => 'Head & Shoulders', 'ar' => 'رأس وكتفين'],
            'double_top_bottom' => ['en' => 'Double Top/Bottom', 'ar' => 'قمة/قاع مزدوج'],
            'triangle' => ['en' => 'Triangle', 'ar' => 'مثلث'],
            'flag_pennant' => ['en' => 'Flag/Pennant', 'ar' => 'علم/راية'],
            'all' => ['en' => 'All Patterns', 'ar' => 'جميع الأنماط'],
        ];

        $label = $labels[$patternType][$locale] ?? $patternType;
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');

        $text = $locale === 'ar'
            ? "📊 *تنبيه نمط*\n\n📊 الأصل: {$symbol}\n📈 النمط: {$label}\n\n🎯 اختر مستوى الثقة المطلوب:"
            : "📊 *Pattern Alert*\n\n📊 Asset: {$symbol}\n📈 Pattern: {$label}\n\n🎯 Select minimum confidence level:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::confidenceLevelKeyboard($locale),
        ]);
    }

    // Recommendation type setters
    public function setRecStrongBuy(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setRecommendationType($chatId, $user, $locale, 'strong_buy');
    }

    public function setRecBuy(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setRecommendationType($chatId, $user, $locale, 'buy');
    }

    public function setRecSell(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setRecommendationType($chatId, $user, $locale, 'sell');
    }

    public function setRecStrongSell(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setRecommendationType($chatId, $user, $locale, 'strong_sell');
    }

    public function setRecAnyChange(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setRecommendationType($chatId, $user, $locale, 'any');
    }

    private function setRecommendationType(int $chatId, ?User $user, string $locale, string $recType): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['parameters']['recommendation_type'] = $recType;
        $draft['step'] = 'direction';
        $user->update(['telegram_alert_draft' => $draft]);

        $labels = [
            'strong_buy' => ['en' => 'Strong Buy', 'ar' => 'شراء قوي'],
            'buy' => ['en' => 'Buy', 'ar' => 'شراء'],
            'sell' => ['en' => 'Sell', 'ar' => 'بيع'],
            'strong_sell' => ['en' => 'Strong Sell', 'ar' => 'بيع قوي'],
            'any' => ['en' => 'Any Change', 'ar' => 'أي تغيير'],
        ];

        $label = $labels[$recType][$locale] ?? $recType;
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');

        $text = $locale === 'ar'
            ? "💡 *تنبيه توصية*\n\n📊 الأصل: {$symbol}\n📈 التوصية: {$label}\n\n🎯 اختر اتجاه السعر:"
            : "💡 *Recommendation Alert*\n\n📊 Asset: {$symbol}\n📈 Recommendation: {$label}\n\n🎯 Select price direction:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertDirectionKeyboard($locale),
        ]);
    }

    // Confidence level setters
    public function setConfidenceHigh(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setConfidenceLevel($chatId, $user, $locale, 80);
    }

    public function setConfidenceMedium(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setConfidenceLevel($chatId, $user, $locale, 60);
    }

    public function setConfidenceLow(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setConfidenceLevel($chatId, $user, $locale, 40);
    }

    private function setConfidenceLevel(int $chatId, ?User $user, string $locale, int $confidenceLevel): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['parameters']['min_confidence'] = $confidenceLevel;
        $draft['step'] = 'direction';
        $user->update(['telegram_alert_draft' => $draft]);

        $labels = [
            80 => ['en' => 'High (80%+)', 'ar' => 'عالي (80%+)'],
            60 => ['en' => 'Medium (60%+)', 'ar' => 'متوسط (60%+)'],
            40 => ['en' => 'Low (40%+)', 'ar' => 'منخفض (40%+)'],
        ];

        $label = $labels[$confidenceLevel][$locale] ?? "{$confidenceLevel}%";
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');
        $alertType = $draft['type'] ?? 'anomaly';

        $typeLabels = [
            'anomaly' => ['en' => 'Anomaly Alert', 'ar' => 'تنبيه شذوذ', 'icon' => '⚠️'],
            'pattern' => ['en' => 'Pattern Alert', 'ar' => 'تنبيه نمط', 'icon' => '📊'],
        ];

        $typeLabel = $typeLabels[$alertType][$locale] ?? $alertType;
        $typeIcon = $typeLabels[$alertType]['icon'] ?? '📊';

        $text = $locale === 'ar'
            ? "{$typeIcon} *{$typeLabel}*\n\n📊 الأصل: {$symbol}\n🎯 الثقة: {$label}\n\n⬆️ اختر اتجاه السعر:"
            : "{$typeIcon} *{$typeLabel}*\n\n📊 Asset: {$symbol}\n🎯 Confidence: {$label}\n\n⬆️ Select price direction:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertDirectionKeyboard($locale),
        ]);
    }

    private function setAlertTriggerType(int $chatId, ?User $user, string $locale, string $triggerType): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['trigger_type'] = $triggerType;
        $draft['step'] = 'value';
        $user->update(['telegram_alert_draft' => $draft]);

        $labels = [
            'target_price' => ['en' => 'Target Price', 'ar' => 'سعر مستهدف'],
            'daily_change' => ['en' => 'Daily Change', 'ar' => 'تغير يومي'],
            'breakout' => ['en' => 'Price Breakout', 'ar' => 'اختراق سعر'],
        ];

        $label = $labels[$triggerType][$locale] ?? $triggerType;
        // Escape underscores for Markdown
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');

        // Get current price for the asset
        $currentPriceText = '';
        if (! empty($draft['asset_id'])) {
            $asset = Asset::with('cachedPrice')->find($draft['asset_id']);
            if ($asset?->cachedPrice?->price) {
                $formattedPrice = number_format($asset->cachedPrice->price, 2);
                $currentPriceText = $locale === 'ar'
                    ? "\n💵 السعر الحالي: {$formattedPrice}"
                    : "\n💵 Current price: {$formattedPrice}";
            }
        }

        if ($triggerType === 'target_price' || $triggerType === 'breakout') {
            $user->update(['telegram_awaiting_input' => 'alert_target_price']);

            $text = $locale === 'ar'
                ? "🎯 *{$label}*\n\n📊 الأصل: {$symbol}{$currentPriceText}\n\nأدخل السعر المستهدف:"
                : "🎯 *{$label}*\n\n📊 Asset: {$symbol}{$currentPriceText}\n\nEnter the target price:";
        } else {
            $user->update(['telegram_awaiting_input' => 'alert_percentage']);

            $text = $locale === 'ar'
                ? "📊 *{$label}*\n\n📊 الأصل: {$symbol}{$currentPriceText}\n\nأدخل نسبة التغير (مثال: 5):"
                : "📊 *{$label}*\n\n📊 Asset: {$symbol}{$currentPriceText}\n\nEnter the change percentage (e.g., 5):";
        }

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::cancelKeyboard($locale),
        ]);
    }

    // Direction setters
    public function setDirectionAbove(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAlertDirection($chatId, $user, $locale, 'above');
    }

    public function setDirectionBelow(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAlertDirection($chatId, $user, $locale, 'below');
    }

    public function setDirectionBoth(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->setAlertDirection($chatId, $user, $locale, 'both');
    }

    private function setAlertDirection(int $chatId, ?User $user, string $locale, string $direction): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $draft['direction'] = $direction;
        $draft['step'] = 'confirm';
        $user->update(['telegram_alert_draft' => $draft]);

        $directionLabels = [
            'above' => ['en' => 'Above', 'ar' => 'أعلى من', 'icon' => '⬆️'],
            'below' => ['en' => 'Below', 'ar' => 'أقل من', 'icon' => '⬇️'],
            'both' => ['en' => 'Either Direction', 'ar' => 'أي اتجاه', 'icon' => '↕️'],
        ];

        $dirLabel = $directionLabels[$direction][$locale] ?? $direction;
        $dirIcon = $directionLabels[$direction]['icon'] ?? '';

        // Escape underscores for Markdown
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');
        $triggerType = $draft['trigger_type'] ?? 'target_price';
        $alertType = $draft['type'] ?? 'price';
        $params = $draft['parameters'] ?? [];

        // Map trigger types to display-friendly labels
        $triggerTypeLabels = [
            'target_price' => ['en' => 'Target Price', 'ar' => 'سعر مستهدف'],
            'daily_change' => ['en' => 'Daily Change', 'ar' => 'تغير يومي'],
            'breakout' => ['en' => 'Price Breakout', 'ar' => 'اختراق سعر'],
            'signal' => ['en' => 'Signal Alert', 'ar' => 'تنبيه إشارة'],
            'prediction' => ['en' => 'Prediction Alert', 'ar' => 'تنبيه توقع'],
            'anomaly' => ['en' => 'Anomaly Alert', 'ar' => 'تنبيه شذوذ'],
            'pattern' => ['en' => 'Pattern Alert', 'ar' => 'تنبيه نمط'],
            'recommendation' => ['en' => 'Recommendation Alert', 'ar' => 'تنبيه توصية'],
        ];
        $triggerTypeLabel = $triggerTypeLabels[$triggerType][$locale] ?? $triggerType;

        // Build confirmation message based on alert type
        if (in_array($alertType, ['anomaly', 'pattern'])) {
            // Anomaly/Pattern alerts have confidence parameter
            $confidence = $params['min_confidence'] ?? 60;
            $subType = $params['anomaly_type'] ?? $params['pattern_type'] ?? 'all';

            $subTypeLabels = [
                // Anomaly types
                'volume' => ['en' => 'Volume', 'ar' => 'حجم'],
                'price' => ['en' => 'Price', 'ar' => 'سعر'],
                'volatility' => ['en' => 'Volatility', 'ar' => 'تقلب'],
                // Pattern types
                'head_shoulders' => ['en' => 'Head & Shoulders', 'ar' => 'رأس وكتفين'],
                'double_top_bottom' => ['en' => 'Double Top/Bottom', 'ar' => 'قمة/قاع مزدوج'],
                'triangle' => ['en' => 'Triangle', 'ar' => 'مثلث'],
                'flag_pennant' => ['en' => 'Flag/Pennant', 'ar' => 'علم/راية'],
                'all' => ['en' => 'All', 'ar' => 'الكل'],
            ];
            $subTypeLabel = $subTypeLabels[$subType][$locale] ?? $subType;

            $text = $locale === 'ar'
                ? "✅ *تأكيد التنبيه*\n\n📊 الأصل: {$symbol}\n📈 النوع: {$triggerTypeLabel}\n🔎 التفاصيل: {$subTypeLabel}\n🎯 الثقة: {$confidence}%+\n{$dirIcon} الاتجاه: {$dirLabel}\n\nهل تريد إنشاء هذا التنبيه؟"
                : "✅ *Confirm Alert*\n\n📊 Asset: {$symbol}\n📈 Type: {$triggerTypeLabel}\n🔎 Details: {$subTypeLabel}\n🎯 Confidence: {$confidence}%+\n{$dirIcon} Direction: {$dirLabel}\n\nCreate this alert?";
        } elseif ($alertType === 'recommendation') {
            // Recommendation alerts have recommendation type
            $recType = $params['recommendation_type'] ?? 'any';

            $recTypeLabels = [
                'strong_buy' => ['en' => 'Strong Buy', 'ar' => 'شراء قوي'],
                'buy' => ['en' => 'Buy', 'ar' => 'شراء'],
                'sell' => ['en' => 'Sell', 'ar' => 'بيع'],
                'strong_sell' => ['en' => 'Strong Sell', 'ar' => 'بيع قوي'],
                'any' => ['en' => 'Any Change', 'ar' => 'أي تغيير'],
            ];
            $recTypeLabel = $recTypeLabels[$recType][$locale] ?? $recType;

            $text = $locale === 'ar'
                ? "✅ *تأكيد التنبيه*\n\n📊 الأصل: {$symbol}\n📈 النوع: {$triggerTypeLabel}\n💡 التوصية: {$recTypeLabel}\n{$dirIcon} الاتجاه: {$dirLabel}\n\nهل تريد إنشاء هذا التنبيه؟"
                : "✅ *Confirm Alert*\n\n📊 Asset: {$symbol}\n📈 Type: {$triggerTypeLabel}\n💡 Recommendation: {$recTypeLabel}\n{$dirIcon} Direction: {$dirLabel}\n\nCreate this alert?";
        } elseif ($alertType === 'signal' || $alertType === 'prediction') {
            // Signal/Prediction alerts don't have value parameters
            $text = $locale === 'ar'
                ? "✅ *تأكيد التنبيه*\n\n📊 الأصل: {$symbol}\n📈 النوع: {$triggerTypeLabel}\n{$dirIcon} الاتجاه: {$dirLabel}\n\nهل تريد إنشاء هذا التنبيه؟"
                : "✅ *Confirm Alert*\n\n📊 Asset: {$symbol}\n📈 Type: {$triggerTypeLabel}\n{$dirIcon} Direction: {$dirLabel}\n\nCreate this alert?";
        } else {
            // Price alerts have value parameters
            $value = $params['target_price']
                ?? $params['level']
                ?? $params['threshold_percent']
                ?? 'N/A';

            $valueStr = is_numeric($value) ? number_format($value, 2) : $value;
            if ($triggerType === 'daily_change') {
                $valueStr .= '%';
            }

            $text = $locale === 'ar'
                ? "✅ *تأكيد التنبيه*\n\n📊 الأصل: {$symbol}\n📈 النوع: {$triggerTypeLabel}\n🎯 القيمة: {$valueStr}\n{$dirIcon} الاتجاه: {$dirLabel}\n\nهل تريد إنشاء هذا التنبيه؟"
                : "✅ *Confirm Alert*\n\n📊 Asset: {$symbol}\n📈 Type: {$triggerTypeLabel}\n🎯 Value: {$valueStr}\n{$dirIcon} Direction: {$dirLabel}\n\nCreate this alert?";
        }

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertConfirmKeyboard($locale),
        ]);
    }

    public function confirmCreateAlert(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];

        if (empty($draft['asset_id']) || empty($draft['trigger_type'])) {
            $text = $locale === 'ar'
                ? '❌ بيانات التنبيه غير مكتملة. يرجى البدء من جديد.'
                : '❌ Alert data incomplete. Please start again.';

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);
        }

        $alertData = [
            'user_id' => $user->id,
            'asset_id' => $draft['asset_id'],
            'type' => $draft['type'] ?? 'price',
            'trigger_type' => $draft['trigger_type'],
            'direction' => $draft['direction'] ?? 'both',
            'parameters' => $draft['parameters'] ?? [],
            'status' => 'active',
        ];

        $alert = Alert::create($alertData);

        Log::info('Alert created via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'type' => $alert->type,
        ]);

        $user->update(['telegram_alert_draft' => null]);

        // Escape underscores for Markdown
        $symbol = str_replace('_', '\\_', $draft['asset_symbol'] ?? 'N/A');

        $text = $locale === 'ar'
            ? "✅ *تم إنشاء التنبيه*\n\n📊 الأصل: {$symbol}\n\nسيتم إشعارك عند تحقق الشرط."
            : "✅ *Alert Created*\n\n📊 Asset: {$symbol}\n\nYou'll be notified when the condition is met.";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertSuccessKeyboard($locale),
        ]);
    }

    public function showSnoozeOptions(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $text = $locale === 'ar'
            ? "😴 *تأجيل التنبيه*\n\nاختر مدة التأجيل:"
            : "😴 *Snooze Alert*\n\nSelect snooze duration:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::snoozeOptionsKeyboard($locale),
        ]);
    }

    public function unsnoozeAlert(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $text = $locale === 'ar'
                ? '❌ التنبيه غير موجود'
                : '❌ Alert not found';

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);
        }

        $alert->update(['snoozed_until' => null]);

        Log::info('Alert unsnoozed via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
        ]);

        $text = $locale === 'ar'
            ? '✅ تم إلغاء تأجيل التنبيه'
            : '✅ Alert unsnoozed';

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);
    }

    public function toggleAlertStatus(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $text = $locale === 'ar'
                ? '❌ التنبيه غير موجود'
                : '❌ Alert not found';

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);
        }

        $isCurrentlyActive = $alert->status === 'active';
        $newStatus = $isCurrentlyActive ? 'paused' : 'active';
        $alert->update(['status' => $newStatus]);

        Log::info('Alert status toggled via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'status' => $newStatus,
        ]);

        $text = $newStatus === 'active'
            ? ($locale === 'ar' ? '✅ تم تفعيل التنبيه' : '✅ Alert resumed')
            : ($locale === 'ar' ? '⏸️ تم إيقاف التنبيه مؤقتاً' : '⏸️ Alert paused');

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);
    }

    public function showDeleteConfirmation(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->with('asset')
            ->first();

        if (! $alert) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        // Escape underscores for Markdown
        $symbol = str_replace('_', '\\_', $alert->asset?->symbol ?? 'N/A');

        $text = $locale === 'ar'
            ? "🗑️ *حذف التنبيه*\n\n📊 الأصل: {$symbol}\n\n⚠️ هل أنت متأكد من حذف هذا التنبيه؟"
            : "🗑️ *Delete Alert*\n\n📊 Asset: {$symbol}\n\n⚠️ Are you sure you want to delete this alert?";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::deleteConfirmKeyboard($locale),
        ]);
    }

    public function executeDeleteAlert(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $text = $locale === 'ar'
                ? '❌ التنبيه غير موجود'
                : '❌ Alert not found';

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);
        }

        Log::info('Alert deleted via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
        ]);

        $alert->delete();

        $draft['viewing_alert_id'] = null;
        $user->update(['telegram_alert_draft' => $draft]);

        $text = $locale === 'ar'
            ? '✅ تم حذف التنبيه'
            : '✅ Alert deleted';

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);
    }

    // Snooze presets
    public function applySnooze1h(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->applySnooze($chatId, $user, $locale, '1h');
    }

    public function applySnooze4h(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->applySnooze($chatId, $user, $locale, '4h');
    }

    public function applySnooze1d(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->applySnooze($chatId, $user, $locale, '1d');
    }

    public function applySnoozeMarketClose(int $chatId, ?User $user, string $locale): mixed
    {
        return $this->applySnooze($chatId, $user, $locale, 'market_close');
    }

    private function applySnooze(int $chatId, ?User $user, string $locale, string $duration): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $draft = $user->telegram_alert_draft ?? [];
        $alertId = $draft['viewing_alert_id'] ?? null;

        if (! $alertId) {
            return $this->showAlertsList($chatId, $user, $locale);
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $text = $locale === 'ar'
                ? '❌ التنبيه غير موجود'
                : '❌ Alert not found';

            return $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);
        }

        $snoozedUntil = match ($duration) {
            '1h' => Carbon::now()->addHour(),
            '4h' => Carbon::now()->addHours(4),
            '1d' => Carbon::now()->addDay(),
            'market_close' => Carbon::now()->setTime(16, 0, 0),
            default => Carbon::now()->addHour(),
        };

        $alert->update(['snoozed_until' => $snoozedUntil]);

        Log::info('Alert snoozed via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'duration' => $duration,
            'snoozed_until' => $snoozedUntil,
        ]);

        $durationLabels = [
            '1h' => ['en' => '1 hour', 'ar' => 'ساعة واحدة'],
            '4h' => ['en' => '4 hours', 'ar' => '4 ساعات'],
            '1d' => ['en' => '1 day', 'ar' => 'يوم واحد'],
            'market_close' => ['en' => 'market close', 'ar' => 'إغلاق السوق'],
        ];

        $durationLabel = $durationLabels[$duration][$locale] ?? $duration;

        $text = $locale === 'ar'
            ? "😴 تم تأجيل التنبيه لمدة {$durationLabel}"
            : "😴 Alert snoozed for {$durationLabel}";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);
    }

    public function promptAssetSearch(int $chatId, ?User $user, string $locale): mixed
    {
        if (! $user) {
            return $this->goBackToMainMenu($chatId, $user, $locale);
        }

        $user->update(['telegram_awaiting_input' => 'alert_asset_search']);

        $text = $locale === 'ar'
            ? "🔍 *بحث عن أصل*\n\nأدخل رمز أو اسم الأصل للبحث:"
            : "🔍 *Search Asset*\n\nEnter asset symbol or name to search:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::cancelKeyboard($locale),
        ]);
    }

    private function goBackToMainMenu(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "🏠 *القائمة الرئيسية*\n\nاختر من القائمة أدناه:"
            : "🏠 *Main Menu*\n\nChoose from the menu below:";

        return $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => MainMenuKeyboard::forUser($user, $locale),
        ]);
    }
}
