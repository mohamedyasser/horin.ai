<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;

class TelegramMessageBuilder
{
    private string $line = '━━━━━━━━━━━━━━━━━━';

    /**
     * Build notification message based on alert type.
     */
    public function buildAlertMessage(
        AlertNotification $notification,
        string $locale = 'en'
    ): array {
        $alert = $notification->alert;
        $history = $notification->alertHistory;

        if (! $alert || ! $history) {
            return $this->buildGenericMessage($notification, $locale);
        }

        $message = match ($alert->trigger_type) {
            'target_price' => $this->buildTargetPriceMessage($alert, $history, $locale),
            'breakout' => $this->buildBreakoutMessage($alert, $history, $locale),
            'zone' => $this->buildZoneMessage($alert, $history, $locale),
            'gap' => $this->buildGapMessage($alert, $history, $locale),
            '52week' => $this->build52WeekMessage($alert, $history, $locale),
            'daily_change' => $this->buildDailyChangeMessage($alert, $history, $locale),
            'entry_return' => $this->buildEntryReturnMessage($alert, $history, $locale),
            'prediction' => $this->buildPredictionMessage($alert, $history, $locale),
            'signal' => $this->buildSignalMessage($alert, $history, $locale),
            'anomaly' => $this->buildAnomalyMessage($alert, $history, $locale),
            'pattern' => $this->buildPatternMessage($alert, $history, $locale),
            'recommendation' => $this->buildRecommendationMessage($alert, $history, $locale),
            'compound_intelligence' => $this->buildCompoundMessage($alert, $history, $locale),
            default => $this->buildGenericMessage($notification, $locale),
        };

        return [
            'text' => $message,
            'keyboard' => $this->buildAlertKeyboard($alert, $history, $locale),
        ];
    }

    /**
     * Build target price alert message.
     */
    private function buildTargetPriceMessage(
        Alert $alert,
        AlertHistory $history,
        string $locale
    ): string {
        $asset = $alert->asset;
        $params = $alert->parameters;
        $context = $history->trigger_context ?? [];

        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $currentPrice = $history->trigger_value;
        $targetPrice = $params['target_price'] ?? 0;
        $changePercent = $context['change_percent'] ?? 0;
        $direction = $params['direction'] ?? 'above';

        $directionEmoji = $direction === 'above' ? '📈' : '📉';
        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');
        $date = now()->setTimezone('Africa/Cairo')->translatedFormat('M d, Y');

        if ($locale === 'ar') {
            return <<<MSG
🎯 *وصول السعر المستهدف*
{$this->line}

*{$symbol}* - {$name}

{$directionEmoji} السعر الحالي: *{$currentPrice} ج.م*
🎯 الهدف: {$targetPrice} ج.م
📊 التغير: {$changePercent}% اليوم

تم تفعيل التنبيه عند {$targetPrice} ج.م.

🕐 {$time} · {$date}

{$this->line}
MSG;
        }

        return <<<MSG
🎯 *Target Price Reached*
{$this->line}

*{$symbol}* - {$name}

{$directionEmoji} Current Price: *{$currentPrice} EGP*
🎯 Target: {$targetPrice} EGP
📊 Change: {$changePercent}% today

Your alert triggered at {$targetPrice} EGP.

🕐 {$time} · {$date}

{$this->line}
MSG;
    }

    /**
     * Build breakout alert message.
     */
    private function buildBreakoutMessage(
        Alert $alert,
        AlertHistory $history,
        string $locale
    ): string {
        $asset = $alert->asset;
        $params = $alert->parameters;
        $context = $history->trigger_context ?? [];

        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $currentPrice = $history->trigger_value;
        $level = $params['level'] ?? 0;
        $direction = $params['direction'] ?? 'above';
        $volumeRatio = $context['volume_ratio'] ?? 1;

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');
        $date = now()->setTimezone('Africa/Cairo')->translatedFormat('M d, Y');

        if ($locale === 'ar') {
            $directionText = $direction === 'above' ? 'أعلى' : 'أدنى';

            return <<<MSG
🚀 *اختراق مؤكد*
{$this->line}

*{$symbol}* - {$name}

📈 السعر الحالي: *{$currentPrice} ج.م*
🚩 مستوى الاختراق: {$level} ج.م
⬆️ الاتجاه: {$directionText} (مؤكد)
📊 الحجم: {$volumeRatio}x المتوسط

السعر استقر فوق مستوى الاختراق.

🕐 {$time} · {$date}

{$this->line}
MSG;
        }

        $directionText = $direction === 'above' ? 'Above' : 'Below';

        return <<<MSG
🚀 *Breakout Confirmed*
{$this->line}

*{$symbol}* - {$name}

📈 Current Price: *{$currentPrice} EGP*
🚩 Breakout Level: {$level} EGP
⬆️ Direction: {$directionText} (Confirmed)
📊 Volume: {$volumeRatio}x average

Price sustained above breakout level.

🕐 {$time} · {$date}

{$this->line}
MSG;
    }

    /**
     * Build signal alert message.
     */
    private function buildSignalMessage(
        Alert $alert,
        AlertHistory $history,
        string $locale
    ): string {
        $asset = $alert->asset;
        $context = $history->trigger_context ?? [];

        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $currentPrice = $history->trigger_value;
        $indicator = $context['indicator'] ?? 'N/A';
        $signalType = $context['signal_type'] ?? 'N/A';
        $strength = ($context['strength'] ?? 0) * 100;
        $indicatorValue = $context['indicator_value'] ?? 'N/A';

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');
        $date = now()->setTimezone('Africa/Cairo')->translatedFormat('M d, Y');

        if ($locale === 'ar') {
            return <<<MSG
📊 *تم رصد إشارة فنية*
{$this->line}

*{$symbol}* - {$name}

🔍 نوع الإشارة: *{$signalType}*
📈 المؤشر: {$indicator} = {$indicatorValue}
💪 القوة: {$strength}%

السعر الحالي: {$currentPrice} ج.م

🕐 {$time} · {$date}

{$this->line}
MSG;
        }

        return <<<MSG
📊 *Technical Signal Detected*
{$this->line}

*{$symbol}* - {$name}

🔍 Signal Type: *{$signalType}*
📈 Indicator: {$indicator} = {$indicatorValue}
💪 Strength: {$strength}%

Current Price: {$currentPrice} EGP

🕐 {$time} · {$date}

{$this->line}
MSG;
    }

    /**
     * Build anomaly alert message.
     */
    private function buildAnomalyMessage(
        Alert $alert,
        AlertHistory $history,
        string $locale
    ): string {
        $asset = $alert->asset;
        $context = $history->trigger_context ?? [];

        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $currentPrice = $history->trigger_value;
        $anomalyType = $context['anomaly_type'] ?? 'unknown';
        $severity = $context['severity'] ?? 'high';
        $confidence = ($context['confidence'] ?? 0) * 100;
        $reasons = $context['reasons'] ?? [];

        $severityEmoji = match ($severity) {
            'critical' => '🚨',
            'high' => '⚠️',
            default => '🔔',
        };

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');
        $date = now()->setTimezone('Africa/Cairo')->translatedFormat('M d, Y');

        if ($locale === 'ar') {
            $severityText = match ($severity) {
                'critical' => 'حرجة',
                'high' => 'عالية',
                default => 'متوسطة',
            };
            $reasonsList = implode("\n", array_map(fn ($r) => "• {$r}", $reasons));

            return <<<MSG
{$severityEmoji} *تم رصد شذوذ في السوق*
{$this->line}

*{$symbol}* - {$name}

⚠️ نوع الشذوذ: *{$anomalyType}*
⚡ الخطورة: {$severityText}
🎯 الثقة: {$confidence}%

🧠 الأسباب:
{$reasonsList}

السعر الحالي: {$currentPrice} ج.م

🕐 {$time} · {$date}

{$this->line}
MSG;
        }

        $reasonsList = implode("\n", array_map(fn ($r) => "• {$r}", $reasons));

        return <<<MSG
{$severityEmoji} *Market Anomaly Detected*
{$this->line}

*{$symbol}* - {$name}

⚠️ Anomaly Type: *{$anomalyType}*
⚡ Severity: {$severity}
🎯 Confidence: {$confidence}%

🧠 Reasons:
{$reasonsList}

Current Price: {$currentPrice} EGP

🕐 {$time} · {$date}

{$this->line}
MSG;
    }

    /**
     * Build pattern alert message.
     */
    private function buildPatternMessage(
        Alert $alert,
        AlertHistory $history,
        string $locale
    ): string {
        $asset = $alert->asset;
        $context = $history->trigger_context ?? [];

        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $currentPrice = $history->trigger_value;
        $patternType = $context['pattern_type'] ?? 'unknown';
        $status = $context['status'] ?? 'confirmed';
        $confidence = ($context['confidence'] ?? 0) * 100;
        $target = $context['target'] ?? null;
        $bias = $context['bias'] ?? 'bullish';

        $biasEmoji = $bias === 'bullish' ? '⬆️' : '⬇️';

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');
        $date = now()->setTimezone('Africa/Cairo')->translatedFormat('M d, Y');

        if ($locale === 'ar') {
            $statusText = $status === 'confirmed' ? 'مؤكد' : 'يتشكل';
            $biasText = $bias === 'bullish' ? 'صاعد' : 'هابط';

            $targetLine = $target
                ? "🎯 الهدف: {$target} ج.م"
                : '';

            return <<<MSG
📐 *تأكيد نموذج فني*
{$this->line}

*{$symbol}* - {$name}

🔍 النموذج: *{$patternType}*
✅ الحالة: {$statusText}
🎯 الثقة: {$confidence}%
{$biasEmoji} الميل: {$biasText}

{$targetLine}

السعر الحالي: {$currentPrice} ج.م

🕐 {$time} · {$date}

{$this->line}
MSG;
        }

        $statusText = $status === 'confirmed' ? 'Confirmed' : 'Forming';

        $targetLine = $target
            ? "🎯 Target: {$target} EGP"
            : '';

        return <<<MSG
📐 *Chart Pattern Confirmed*
{$this->line}

*{$symbol}* - {$name}

🔍 Pattern: *{$patternType}*
✅ Status: {$statusText}
🎯 Confidence: {$confidence}%
{$biasEmoji} Bias: {$bias}

{$targetLine}

Current Price: {$currentPrice} EGP

🕐 {$time} · {$date}

{$this->line}
MSG;
    }

    /**
     * Build recommendation alert message.
     */
    private function buildRecommendationMessage(
        Alert $alert,
        AlertHistory $history,
        string $locale
    ): string {
        $asset = $alert->asset;
        $context = $history->trigger_context ?? [];

        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $currentPrice = $history->trigger_value;
        $newRating = $context['new_rating'] ?? 'N/A';
        $previousRating = $context['previous_rating'] ?? null;
        $score = $context['score'] ?? 0;

        $isUpgrade = $this->isUpgrade($previousRating, $newRating);
        $changeEmoji = $isUpgrade ? '⬆️' : '⬇️';
        $changeText = $isUpgrade
            ? ($locale === 'ar' ? 'ترقية!' : 'Upgrade!')
            : ($locale === 'ar' ? 'تخفيض' : 'Downgrade');

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');
        $date = now()->setTimezone('Africa/Cairo')->translatedFormat('M d, Y');

        if ($locale === 'ar') {
            $previousLine = $previousRating
                ? "↩️ السابق: {$previousRating}"
                : '';

            return <<<MSG
⭐ *تحديث التوصية*
{$this->line}

*{$symbol}* - {$name}

{$changeEmoji} التصنيف الجديد: *{$newRating}*
{$previousLine}
🎯 الدرجة: {$score}/10
📊 {$changeText}

السعر الحالي: {$currentPrice} ج.م

🕐 {$time} · {$date}

{$this->line}
MSG;
        }

        $previousLine = $previousRating
            ? "↩️ Previous: {$previousRating}"
            : '';

        return <<<MSG
⭐ *Recommendation Updated*
{$this->line}

*{$symbol}* - {$name}

{$changeEmoji} New Rating: *{$newRating}*
{$previousLine}
🎯 Score: {$score}/10
📊 {$changeText}

Current Price: {$currentPrice} EGP

🕐 {$time} · {$date}

{$this->line}
MSG;
    }

    /**
     * Build compound intelligence message.
     */
    private function buildCompoundMessage(Alert $alert, AlertHistory $history, string $locale): string
    {
        $asset = $alert->asset;
        $context = $history->trigger_context ?? [];
        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $conditions = $context['conditions'] ?? [];
        $results = $context['results'] ?? [];
        $confidence = ($context['combined_confidence'] ?? 0) * 100;
        $currentPrice = $history->trigger_value;

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');

        $conditionLines = [];
        foreach ($conditions as $i => $cond) {
            $result = $results[$i] ?? false;
            $emoji = $result ? '✅' : '❌';
            $conditionLines[] = "{$emoji} ".($cond['description'] ?? 'Condition '.($i + 1));
        }
        $conditionsText = implode("\n", $conditionLines);

        if ($locale === 'ar') {
            return "⭐ *توافق إشارات متعددة!*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n🔥 *فرصة عالية الثقة*\n\n{$conditionsText}\n\n🎯 الثقة المجمعة: *{$confidence}%*\nالسعر الحالي: {$currentPrice} ج.م\n\n🕐 {$time}";
        }

        return "⭐ *Multiple Signals Aligned!*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n🔥 *High-Conviction Setup*\n\n{$conditionsText}\n\n🎯 Combined Confidence: *{$confidence}%*\nCurrent Price: {$currentPrice} EGP\n\n🕐 {$time}";
    }

    /**
     * Build zone message.
     */
    private function buildZoneMessage(Alert $alert, AlertHistory $history, string $locale): string
    {
        $asset = $alert->asset;
        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $currentPrice = $history->trigger_value;
        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');

        if ($locale === 'ar') {
            return "📍 *تنبيه المنطقة*\n{$this->line}\n\n*{$symbol}* - {$name}\n\nالسعر دخل المنطقة المحددة.\nالسعر الحالي: {$currentPrice} ج.م\n\n🕐 {$time}";
        }

        return "📍 *Zone Alert*\n{$this->line}\n\n*{$symbol}* - {$name}\n\nPrice entered the configured zone.\nCurrent Price: {$currentPrice} EGP\n\n🕐 {$time}";
    }

    /**
     * Build gap message.
     */
    private function buildGapMessage(Alert $alert, AlertHistory $history, string $locale): string
    {
        $asset = $alert->asset;
        $context = $history->trigger_context ?? [];
        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $gapPercent = $context['gap_percent'] ?? 0;
        $direction = $gapPercent > 0 ? 'up' : 'down';
        $emoji = $direction === 'up' ? '⬆️' : '⬇️';

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');

        if ($locale === 'ar') {
            $dirText = $direction === 'up' ? 'صاعدة' : 'هابطة';

            return "🕳 *فجوة سعرية {$dirText}*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n{$emoji} الفجوة: {$gapPercent}%\n\n🕐 {$time}";
        }

        return "🕳 *Gap {$direction} Detected*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n{$emoji} Gap: {$gapPercent}%\n\n🕐 {$time}";
    }

    /**
     * Build 52-week high/low message.
     */
    private function build52WeekMessage(Alert $alert, AlertHistory $history, string $locale): string
    {
        $asset = $alert->asset;
        $params = $alert->parameters;
        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $type = $params['type'] ?? 'high';
        $price = $history->trigger_value;

        $emoji = $type === 'high' ? '🏆' : '⚠️';
        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');

        if ($locale === 'ar') {
            $typeText = $type === 'high' ? 'قمة جديدة' : 'قاع جديد';

            return "{$emoji} *{$typeText} لـ 52 أسبوع!*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n⭐ السعر: *{$price} ج.م*\n\n🕐 {$time}";
        }

        $typeText = $type === 'high' ? 'New 52-Week High' : 'New 52-Week Low';

        return "{$emoji} *{$typeText}!*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n⭐ Price: *{$price} EGP*\n\n🕐 {$time}";
    }

    /**
     * Build daily change message.
     */
    private function buildDailyChangeMessage(Alert $alert, AlertHistory $history, string $locale): string
    {
        $asset = $alert->asset;
        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $changePercent = $history->trigger_value;
        $emoji = $changePercent > 0 ? '📈' : '📉';

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');

        if ($locale === 'ar') {
            return "{$emoji} *تنبيه حركة كبيرة*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n🔥 التغير: *{$changePercent}%*\n\n🕐 {$time}";
        }

        return "{$emoji} *Big Move Alert*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n🔥 Change: *{$changePercent}%*\n\n🕐 {$time}";
    }

    /**
     * Build entry return message.
     */
    private function buildEntryReturnMessage(Alert $alert, AlertHistory $history, string $locale): string
    {
        $asset = $alert->asset;
        $params = $alert->parameters;
        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $entryPrice = $params['entry_price'] ?? 0;
        $currentPrice = $history->trigger_value;

        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');

        if ($locale === 'ar') {
            return "🔄 *العودة لسعر الشراء*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n💰 السعر الحالي: *{$currentPrice} ج.م*\n🎯 سعر شرائك: {$entryPrice} ج.م\n\nفرصة للخروج بدون خسارة.\n\n🕐 {$time}";
        }

        return "🔄 *Back to Your Entry Price*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n💰 Current Price: *{$currentPrice} EGP*\n🎯 Your Entry: {$entryPrice} EGP\n\nBreak-even opportunity.\n\n🕐 {$time}";
    }

    /**
     * Build prediction message.
     */
    private function buildPredictionMessage(Alert $alert, AlertHistory $history, string $locale): string
    {
        $asset = $alert->asset;
        $context = $history->trigger_context ?? [];
        $symbol = $asset?->symbol ?? 'N/A';
        $name = $locale === 'ar' ? ($asset?->name_ar ?? $asset?->name) : $asset?->name;
        $direction = $context['direction'] ?? 'up';
        $confidence = ($context['confidence'] ?? 0) * 100;
        $horizon = $context['horizon'] ?? '1 hour';
        $currentPrice = $history->trigger_value;

        $dirEmoji = $direction === 'up' ? '⬆️' : '⬇️';
        $time = now()->setTimezone('Africa/Cairo')->format('h:i A');

        if ($locale === 'ar') {
            $dirText = $direction === 'up' ? 'صاعد' : 'هابط';

            return "🔮 *تنبيه توقع الذكاء الاصطناعي*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n🤖 توقع حورين:\n{$dirEmoji} الاتجاه: *{$dirText}*\n🕐 المدى: {$horizon}\n🎯 الثقة: *{$confidence}%*\n\nالسعر الحالي: {$currentPrice} ج.م\n\n🕐 {$time}";
        }

        return "🔮 *AI Prediction Alert*\n{$this->line}\n\n*{$symbol}* - {$name}\n\n🤖 Horin AI Prediction:\n{$dirEmoji} Direction: *{$direction}*\n🕐 Horizon: {$horizon}\n🎯 Confidence: *{$confidence}%*\n\nCurrent Price: {$currentPrice} EGP\n\n🕐 {$time}";
    }

    /**
     * Build generic message for unknown alert types.
     */
    private function buildGenericMessage(
        AlertNotification $notification,
        string $locale
    ): string {
        $title = $locale === 'ar' ? ($notification->title_ar ?? $notification->title) : $notification->title;
        $body = $locale === 'ar' ? ($notification->body_ar ?? $notification->body) : $notification->body;

        return <<<MSG
🔔 *{$title}*
{$this->line}

{$body}

{$this->line}
MSG;
    }

    /**
     * Build inline keyboard for alert message.
     */
    private function buildAlertKeyboard(
        Alert $alert,
        AlertHistory $history,
        string $locale
    ): array {
        $viewLabel = $locale === 'ar' ? 'عرض السهم' : 'View Stock';
        $snoozeLabel = $locale === 'ar' ? 'تأجيل' : 'Snooze';
        $manageLabel = $locale === 'ar' ? 'إدارة' : 'Manage';

        $assetSymbol = $alert->asset?->symbol ?? '';
        $alertId = $alert->id;
        $historyId = $history->id;

        return [
            [
                [
                    'text' => "📊 {$viewLabel}",
                    'url' => route('assets.show', ['locale' => $locale, 'asset' => $assetSymbol]),
                ],
            ],
            [
                [
                    'text' => "⏰ {$snoozeLabel} 1h",
                    'callback_data' => "snooze:{$alertId}:60",
                ],
                [
                    'text' => "⏰ {$snoozeLabel} 4h",
                    'callback_data' => "snooze:{$alertId}:240",
                ],
            ],
            [
                [
                    'text' => '✅ Acknowledge',
                    'callback_data' => "ack:{$historyId}",
                ],
                [
                    'text' => "⚙️ {$manageLabel}",
                    'url' => route('alerts.show', $alertId),
                ],
            ],
        ];
    }

    /**
     * Check if rating change is an upgrade.
     */
    private function isUpgrade(?string $previous, string $current): bool
    {
        $ratings = ['strong_sell', 'sell', 'hold', 'buy', 'strong_buy'];
        $prevIndex = $previous ? array_search(strtolower($previous), $ratings) : -1;
        $currIndex = array_search(strtolower($current), $ratings);

        return $currIndex > $prevIndex;
    }
}
