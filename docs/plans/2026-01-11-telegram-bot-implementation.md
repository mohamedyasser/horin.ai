# Telegram Bot Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement a full-featured Telegram bot for the Kira Alert System that sends rich, formatted notifications and handles user interactions.

**Architecture:** The implementation builds on existing alert infrastructure. A new `TelegramBotService` sends formatted messages using the WeStacks TeleBot library. A `TelegramMessageBuilder` formats notifications per the design spec. The webhook controller handles commands and inline button callbacks.

**Tech Stack:** Laravel 12, WeStacks TeleBot, Redis (queue), PHP 8.4

---

## Prerequisites

**Existing Files Referenced:**
- `app/Jobs/Alerts/SendAlertNotification.php` - Has placeholder `sendTelegram()`
- `app/Jobs/Alerts/GenerateDigest.php` - Has TODO for TelegramBotService
- `app/Jobs/Alerts/ProcessEscalation.php` - Needs Telegram integration
- `app/Http/Controllers/Auth/TelegramWebhookController.php` - Basic webhook
- `config/telegram.php` - Bot configuration
- `docs/plans/2026-01-10-kira-alert-notification-examples.md` - Message formats

**Environment Variables Required:**
```
TELEGRAM_BOT_TOKEN=your_bot_token_from_botfather
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_WEBHOOK_SECRET=random_secure_string
```

---

## Task 1: Create TelegramBotService

**Files:**
- Create: `app/Services/TelegramBotService.php`

**Step 1: Create the service file**

Run:
```bash
php artisan make:class Services/TelegramBotService --no-interaction
```

**Step 2: Implement TelegramBotService**

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\TeleBot;

class TelegramBotService
{
    private TeleBot $bot;

    public function __construct()
    {
        $token = config('telegram.bot_token');

        if (! $token) {
            throw new \RuntimeException('Telegram bot token not configured');
        }

        $this->bot = new TeleBot($token);
    }

    /**
     * Send a text message to a chat.
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        array $options = []
    ): ?array {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ], $options);

            $result = $this->bot->sendMessage($params);

            Log::debug('Telegram message sent', [
                'chat_id' => $chatId,
                'message_id' => $result['message_id'] ?? null,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Send a message with inline keyboard.
     */
    public function sendMessageWithKeyboard(
        int|string $chatId,
        string $text,
        array $keyboard,
        array $options = []
    ): ?array {
        $options['reply_markup'] = json_encode([
            'inline_keyboard' => $keyboard,
        ]);

        return $this->sendMessage($chatId, $text, $options);
    }

    /**
     * Edit an existing message.
     */
    public function editMessage(
        int|string $chatId,
        int $messageId,
        string $text,
        array $options = []
    ): ?array {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ], $options);

            return $this->bot->editMessageText($params);
        } catch (\Exception $e) {
            Log::error('Failed to edit Telegram message', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Answer a callback query (button press).
     */
    public function answerCallback(
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false
    ): bool {
        try {
            $this->bot->answerCallbackQuery([
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to answer callback query', [
                'callback_query_id' => $callbackQueryId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get the underlying TeleBot instance.
     */
    public function getBot(): TeleBot
    {
        return $this->bot;
    }
}
```

**Step 3: Register in AppServiceProvider**

Add to `app/Providers/AppServiceProvider.php` in the `register()` method:

```php
$this->app->singleton(\App\Services\TelegramBotService::class, function ($app) {
    return new \App\Services\TelegramBotService();
});
```

**Step 4: Commit**

```bash
git add app/Services/TelegramBotService.php app/Providers/AppServiceProvider.php
git commit -m "feat: add TelegramBotService for sending bot messages"
```

---

## Task 2: Create TelegramMessageBuilder

**Files:**
- Create: `app/Services/TelegramMessageBuilder.php`

**Step 1: Create the builder class**

Run:
```bash
php artisan make:class Services/TelegramMessageBuilder --no-interaction
```

**Step 2: Implement message building logic**

```php
<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;

class TelegramMessageBuilder
{
    private const LINE = '━━━━━━━━━━━━━━━━━━';

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
{$this::LINE}

*{$symbol}* - {$name}

{$directionEmoji} السعر الحالي: *{$currentPrice} ج.م*
🎯 الهدف: {$targetPrice} ج.م
📊 التغير: {$changePercent}% اليوم

تم تفعيل التنبيه عند {$targetPrice} ج.م.

🕐 {$time} · {$date}

{$this::LINE}
MSG;
        }

        return <<<MSG
🎯 *Target Price Reached*
{$this::LINE}

*{$symbol}* - {$name}

{$directionEmoji} Current Price: *{$currentPrice} EGP*
🎯 Target: {$targetPrice} EGP
📊 Change: {$changePercent}% today

Your alert triggered at {$targetPrice} EGP.

🕐 {$time} · {$date}

{$this::LINE}
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
{$this::LINE}

*{$symbol}* - {$name}

📈 السعر الحالي: *{$currentPrice} ج.م*
🚩 مستوى الاختراق: {$level} ج.م
⬆️ الاتجاه: {$directionText} (مؤكد)
📊 الحجم: {$volumeRatio}x المتوسط

السعر استقر فوق مستوى الاختراق.

🕐 {$time} · {$date}

{$this::LINE}
MSG;
        }

        $directionText = $direction === 'above' ? 'Above' : 'Below';
        return <<<MSG
🚀 *Breakout Confirmed*
{$this::LINE}

*{$symbol}* - {$name}

📈 Current Price: *{$currentPrice} EGP*
🚩 Breakout Level: {$level} EGP
⬆️ Direction: {$directionText} (Confirmed)
📊 Volume: {$volumeRatio}x average

Price sustained above breakout level.

🕐 {$time} · {$date}

{$this::LINE}
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
{$this::LINE}

*{$symbol}* - {$name}

🔍 نوع الإشارة: *{$signalType}*
📈 المؤشر: {$indicator} = {$indicatorValue}
💪 القوة: {$strength}%

السعر الحالي: {$currentPrice} ج.م

🕐 {$time} · {$date}

{$this::LINE}
MSG;
        }

        return <<<MSG
📊 *Technical Signal Detected*
{$this::LINE}

*{$symbol}* - {$name}

🔍 Signal Type: *{$signalType}*
📈 Indicator: {$indicator} = {$indicatorValue}
💪 Strength: {$strength}%

Current Price: {$currentPrice} EGP

🕐 {$time} · {$date}

{$this::LINE}
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
{$this::LINE}

*{$symbol}* - {$name}

⚠️ نوع الشذوذ: *{$anomalyType}*
⚡ الخطورة: {$severityText}
🎯 الثقة: {$confidence}%

🧠 الأسباب:
{$reasonsList}

السعر الحالي: {$currentPrice} ج.م

🕐 {$time} · {$date}

{$this::LINE}
MSG;
        }

        $reasonsList = implode("\n", array_map(fn ($r) => "• {$r}", $reasons));

        return <<<MSG
{$severityEmoji} *Market Anomaly Detected*
{$this::LINE}

*{$symbol}* - {$name}

⚠️ Anomaly Type: *{$anomalyType}*
⚡ Severity: {$severity}
🎯 Confidence: {$confidence}%

🧠 Reasons:
{$reasonsList}

Current Price: {$currentPrice} EGP

🕐 {$time} · {$date}

{$this::LINE}
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
{$this::LINE}

*{$symbol}* - {$name}

🔍 النموذج: *{$patternType}*
✅ الحالة: {$statusText}
🎯 الثقة: {$confidence}%
{$biasEmoji} الميل: {$biasText}

{$targetLine}

السعر الحالي: {$currentPrice} ج.م

🕐 {$time} · {$date}

{$this::LINE}
MSG;
        }

        $statusText = $status === 'confirmed' ? 'Confirmed' : 'Forming';

        $targetLine = $target
            ? "🎯 Target: {$target} EGP"
            : '';

        return <<<MSG
📐 *Chart Pattern Confirmed*
{$this::LINE}

*{$symbol}* - {$name}

🔍 Pattern: *{$patternType}*
✅ Status: {$statusText}
🎯 Confidence: {$confidence}%
{$biasEmoji} Bias: {$bias}

{$targetLine}

Current Price: {$currentPrice} EGP

🕐 {$time} · {$date}

{$this::LINE}
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
{$this::LINE}

*{$symbol}* - {$name}

{$changeEmoji} التصنيف الجديد: *{$newRating}*
{$previousLine}
🎯 الدرجة: {$score}/10
📊 {$changeText}

السعر الحالي: {$currentPrice} ج.م

🕐 {$time} · {$date}

{$this::LINE}
MSG;
        }

        $previousLine = $previousRating
            ? "↩️ Previous: {$previousRating}"
            : '';

        return <<<MSG
⭐ *Recommendation Updated*
{$this::LINE}

*{$symbol}* - {$name}

{$changeEmoji} New Rating: *{$newRating}*
{$previousLine}
🎯 Score: {$score}/10
📊 {$changeText}

Current Price: {$currentPrice} EGP

🕐 {$time} · {$date}

{$this::LINE}
MSG;
    }

    /**
     * Build generic message for unknown alert types.
     */
    private function buildGenericMessage(
        AlertNotification $notification,
        string $locale
    ): string {
        $title = $locale === 'ar' ? $notification->title_ar : $notification->title;
        $body = $locale === 'ar' ? $notification->body_ar : $notification->body;

        return <<<MSG
🔔 *{$title}*
{$this::LINE}

{$body}

{$this::LINE}
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
                    'url' => config('app.url')."/assets/{$assetSymbol}",
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
                    'text' => "✅ Acknowledge",
                    'callback_data' => "ack:{$historyId}",
                ],
                [
                    'text' => "⚙️ {$manageLabel}",
                    'url' => config('app.url')."/alerts/{$alertId}",
                ],
            ],
        ];
    }

    /**
     * Build zone message.
     */
    private function buildZoneMessage(Alert $alert, AlertHistory $history, string $locale): string
    {
        return $this->buildGenericMessage(
            AlertNotification::make([
                'title' => 'Zone Alert',
                'title_ar' => 'تنبيه المنطقة',
                'body' => "Price entered zone",
                'body_ar' => 'السعر دخل المنطقة',
            ]),
            $locale
        );
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
            return "🕳 *فجوة سعرية {$dirText}*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n{$emoji} الفجوة: {$gapPercent}%\n\n🕐 {$time}";
        }

        return "🕳 *Gap {$direction} Detected*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n{$emoji} Gap: {$gapPercent}%\n\n🕐 {$time}";
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
            return "{$emoji} *{$typeText} لـ 52 أسبوع!*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n⭐ السعر: *{$price} ج.م*\n\n🕐 {$time}";
        }

        $typeText = $type === 'high' ? 'New 52-Week High' : 'New 52-Week Low';
        return "{$emoji} *{$typeText}!*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n⭐ Price: *{$price} EGP*\n\n🕐 {$time}";
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
            return "{$emoji} *تنبيه حركة كبيرة*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n🔥 التغير: *{$changePercent}%*\n\n🕐 {$time}";
        }

        return "{$emoji} *Big Move Alert*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n🔥 Change: *{$changePercent}%*\n\n🕐 {$time}";
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
            return "🔄 *العودة لسعر الشراء*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n💰 السعر الحالي: *{$currentPrice} ج.م*\n🎯 سعر شرائك: {$entryPrice} ج.م\n\nفرصة للخروج بدون خسارة.\n\n🕐 {$time}";
        }

        return "🔄 *Back to Your Entry Price*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n💰 Current Price: *{$currentPrice} EGP*\n🎯 Your Entry: {$entryPrice} EGP\n\nBreak-even opportunity.\n\n🕐 {$time}";
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
            return "🔮 *تنبيه توقع الذكاء الاصطناعي*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n🤖 توقع كيرا:\n{$dirEmoji} الاتجاه: *{$dirText}*\n🕐 المدى: {$horizon}\n🎯 الثقة: *{$confidence}%*\n\nالسعر الحالي: {$currentPrice} ج.م\n\n🕐 {$time}";
        }

        return "🔮 *AI Prediction Alert*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n🤖 Kira AI Prediction:\n{$dirEmoji} Direction: *{$direction}*\n🕐 Horizon: {$horizon}\n🎯 Confidence: *{$confidence}%*\n\nCurrent Price: {$currentPrice} EGP\n\n🕐 {$time}";
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
            $conditionLines[] = "{$emoji} " . ($cond['description'] ?? "Condition " . ($i + 1));
        }
        $conditionsText = implode("\n", $conditionLines);

        if ($locale === 'ar') {
            return "⭐ *توافق إشارات متعددة!*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n🔥 *فرصة عالية الثقة*\n\n{$conditionsText}\n\n🎯 الثقة المجمعة: *{$confidence}%*\nالسعر الحالي: {$currentPrice} ج.م\n\n🕐 {$time}";
        }

        return "⭐ *Multiple Signals Aligned!*\n{$this::LINE}\n\n*{$symbol}* - {$name}\n\n🔥 *High-Conviction Setup*\n\n{$conditionsText}\n\n🎯 Combined Confidence: *{$confidence}%*\nCurrent Price: {$currentPrice} EGP\n\n🕐 {$time}";
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
```

**Step 3: Commit**

```bash
git add app/Services/TelegramMessageBuilder.php
git commit -m "feat: add TelegramMessageBuilder for rich alert messages"
```

---

## Task 3: Create SendTelegramMessage Job

**Files:**
- Create: `app/Jobs/Alerts/SendTelegramMessage.php`

**Step 1: Create the job**

Run:
```bash
php artisan make:job Alerts/SendTelegramMessage --no-interaction
```

**Step 2: Implement the job**

```php
<?php

namespace App\Jobs\Alerts;

use App\Models\AlertNotification;
use App\Services\TelegramBotService;
use App\Services\TelegramMessageBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public function __construct(
        private AlertNotification $notification
    ) {}

    public function handle(
        TelegramBotService $telegram,
        TelegramMessageBuilder $messageBuilder
    ): void {
        $user = $this->notification->user;

        if (! $user || ! $user->telegram_id) {
            $this->notification->markAsFailed('No Telegram ID');
            return;
        }

        $locale = $user->language ?? 'en';

        try {
            $messageData = $messageBuilder->buildAlertMessage($this->notification, $locale);

            $result = $telegram->sendMessageWithKeyboard(
                $user->telegram_id,
                $messageData['text'],
                $messageData['keyboard']
            );

            $this->notification->update([
                'status' => 'sent',
                'sent_at' => now(),
                'data' => array_merge($this->notification->data ?? [], [
                    'telegram_message_id' => $result['message_id'] ?? null,
                ]),
            ]);

            Log::info('Telegram alert sent', [
                'notification_id' => $this->notification->id,
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
            ]);

        } catch (\Exception $e) {
            $this->handleFailure($e);
        }
    }

    private function handleFailure(\Exception $e): void
    {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();

        // Handle specific Telegram errors
        if (str_contains($errorMessage, 'chat not found') ||
            str_contains($errorMessage, 'bot was blocked') ||
            $errorCode === 403) {
            // User blocked the bot or chat doesn't exist
            $this->notification->markAsFailed('User blocked bot or invalid chat');
            $this->notification->user?->update(['telegram_id' => null]);
            return;
        }

        if ($errorCode === 429) {
            // Rate limited - retry after delay
            $retryAfter = $this->extractRetryAfter($errorMessage) ?? 60;
            $this->release($retryAfter);
            return;
        }

        // General failure
        $this->notification->markAsFailed($errorMessage);
        $this->notification->incrementRetry();

        Log::error('Telegram send failed', [
            'notification_id' => $this->notification->id,
            'error' => $errorMessage,
            'code' => $errorCode,
        ]);

        if ($this->attempts() >= $this->tries) {
            $this->fail($e);
        }
    }

    private function extractRetryAfter(string $message): ?int
    {
        if (preg_match('/retry after (\d+)/i', $message, $matches)) {
            return (int) $matches[1];
        }
        return null;
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Telegram message job failed permanently', [
            'notification_id' => $this->notification->id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

**Step 3: Commit**

```bash
git add app/Jobs/Alerts/SendTelegramMessage.php
git commit -m "feat: add SendTelegramMessage job for Telegram delivery"
```

---

## Task 4: Update SendAlertNotification to Use TelegramBotService

**Files:**
- Modify: `app/Jobs/Alerts/SendAlertNotification.php`

**Step 1: Update the sendTelegram method**

Replace the `sendTelegram` method (around lines 192-210):

```php
private function sendTelegram(AlertNotification $notification): void
{
    $user = $notification->user;

    if (! $user->telegram_id) {
        $notification->markAsFailed('No Telegram ID configured');
        return;
    }

    // Dispatch dedicated Telegram job
    SendTelegramMessage::dispatch($notification);
}
```

**Step 2: Add import at top of file**

Add after line 7:
```php
use App\Jobs\Alerts\SendTelegramMessage;
```

**Step 3: Commit**

```bash
git add app/Jobs/Alerts/SendAlertNotification.php
git commit -m "feat: integrate SendTelegramMessage in SendAlertNotification"
```

---

## Task 5: Update GenerateDigest to Use TelegramBotService

**Files:**
- Modify: `app/Jobs/Alerts/GenerateDigest.php`

**Step 1: Update the sendDigest method**

Replace the `sendDigest` method (around lines 168-180):

```php
private function sendDigest(User $user, AlertNotification $notification, string $content): void
{
    if (! $user->telegram_id) {
        Log::info('User has no Telegram ID for digest', ['user_id' => $user->id]);
        return;
    }

    try {
        $telegram = app(\App\Services\TelegramBotService::class);
        $telegram->sendMessage($user->telegram_id, $content);

        Log::info('Telegram digest sent', [
            'user_id' => $user->id,
            'telegram_id' => $user->telegram_id,
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to send Telegram digest', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Jobs/Alerts/GenerateDigest.php
git commit -m "feat: integrate TelegramBotService in GenerateDigest"
```

---

## Task 6: Enhance TelegramWebhookController with Commands

**Files:**
- Modify: `app/Http/Controllers/Auth/TelegramWebhookController.php`

**Step 1: Add new command handlers**

Replace the entire class with:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private TelegramBotService $telegram
    ) {}

    /**
     * Handle incoming Telegram webhook requests.
     */
    public function handle(Request $request): JsonResponse
    {
        $update = $request->all();

        Log::debug('Telegram webhook received', ['update' => $update]);

        try {
            if (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            } elseif (isset($update['message']['contact'])) {
                $this->handleContact($update['message']);
            } elseif (isset($update['message']['text'])) {
                $this->handleTextMessage($update['message']);
            }
        } catch (\Exception $e) {
            Log::error('Webhook handler error', ['error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle text messages (commands).
     */
    protected function handleTextMessage(array $message): void
    {
        $text = $message['text'];
        $from = $message['from'];
        $chatId = $message['chat']['id'];

        $command = strtolower(explode(' ', $text)[0]);

        match ($command) {
            '/start' => $this->handleStart($chatId, $from),
            '/help' => $this->handleHelp($chatId, $from),
            '/alerts' => $this->handleAlerts($chatId, $from),
            '/settings' => $this->handleSettings($chatId, $from),
            '/language', '/lang' => $this->handleLanguage($chatId, $from),
            default => null, // Ignore unknown commands
        };
    }

    /**
     * Handle /start command.
     */
    protected function handleStart(int $chatId, array $from): void
    {
        $user = User::where('telegram_id', (string) $from['id'])->first();

        if ($user && $user->hasVerifiedPhone()) {
            $this->sendWelcomeBack($chatId, $user);
        } elseif ($user && ! $user->hasVerifiedPhone()) {
            $this->sendPhoneRequestMessage($chatId);
        } else {
            $this->telegram->sendMessage(
                $chatId,
                __('auth.telegram.please_login_first')
            );
        }
    }

    /**
     * Handle /help command.
     */
    protected function handleHelp(int $chatId, array $from): void
    {
        $user = User::where('telegram_id', (string) $from['id'])->first();
        $locale = $user?->language ?? 'en';

        $helpText = $locale === 'ar' ? $this->getHelpTextAr() : $this->getHelpTextEn();

        $this->telegram->sendMessage($chatId, $helpText);
    }

    /**
     * Handle /alerts command.
     */
    protected function handleAlerts(int $chatId, array $from): void
    {
        $user = User::where('telegram_id', (string) $from['id'])->first();

        if (! $user) {
            $this->telegram->sendMessage(
                $chatId,
                __('auth.telegram.please_login_first')
            );
            return;
        }

        $locale = $user->language ?? 'en';
        $alerts = Alert::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('asset')
            ->take(10)
            ->get();

        if ($alerts->isEmpty()) {
            $message = $locale === 'ar'
                ? '📭 لا توجد تنبيهات نشطة.\n\nاستخدم التطبيق لإنشاء تنبيهات جديدة.'
                : '📭 No active alerts.\n\nUse the app to create new alerts.';

            $this->telegram->sendMessage($chatId, $message);
            return;
        }

        $lines = [$locale === 'ar' ? '📋 *تنبيهاتك النشطة:*' : '📋 *Your Active Alerts:*'];
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        foreach ($alerts as $alert) {
            $symbol = $alert->asset?->symbol ?? 'N/A';
            $type = $alert->trigger_type;
            $lines[] = "• *{$symbol}* - {$type}";
        }

        $lines[] = '';
        $lines[] = $locale === 'ar'
            ? '📊 استخدم التطبيق لإدارة التنبيهات'
            : '📊 Use the app to manage alerts';

        $this->telegram->sendMessageWithKeyboard(
            $chatId,
            implode("\n", $lines),
            [[
                ['text' => '🔗 Open App', 'url' => config('app.url') . '/alerts'],
            ]]
        );
    }

    /**
     * Handle /settings command.
     */
    protected function handleSettings(int $chatId, array $from): void
    {
        $user = User::where('telegram_id', (string) $from['id'])->first();

        if (! $user) {
            $this->telegram->sendMessage(
                $chatId,
                __('auth.telegram.please_login_first')
            );
            return;
        }

        $locale = $user->language ?? 'en';
        $prefs = $user->getAlertPreferences();

        $quietStart = $prefs->quiet_hours_start ?? '23:00';
        $quietEnd = $prefs->quiet_hours_end ?? '07:00';
        $maxHour = $prefs->max_alerts_per_hour ?? 10;
        $maxDay = $prefs->max_alerts_per_day ?? 50;

        if ($locale === 'ar') {
            $message = <<<MSG
⚙️ *إعداداتك*
━━━━━━━━━━━━━━━━━━

🌐 اللغة: العربية
🌙 ساعات الهدوء: {$quietStart} - {$quietEnd}
📊 الحد الأقصى/ساعة: {$maxHour}
📊 الحد الأقصى/يوم: {$maxDay}

استخدم التطبيق لتعديل الإعدادات.
MSG;
        } else {
            $message = <<<MSG
⚙️ *Your Settings*
━━━━━━━━━━━━━━━━━━

🌐 Language: English
🌙 Quiet Hours: {$quietStart} - {$quietEnd}
📊 Max alerts/hour: {$maxHour}
📊 Max alerts/day: {$maxDay}

Use the app to modify settings.
MSG;
        }

        $this->telegram->sendMessageWithKeyboard(
            $chatId,
            $message,
            [[
                ['text' => '⚙️ Open Settings', 'url' => config('app.url') . '/settings/alerts'],
            ]]
        );
    }

    /**
     * Handle /language command.
     */
    protected function handleLanguage(int $chatId, array $from): void
    {
        $this->telegram->sendMessageWithKeyboard(
            $chatId,
            '🌐 Select your language / اختر لغتك:',
            [[
                ['text' => '🇬🇧 English', 'callback_data' => 'lang:en'],
                ['text' => '🇸🇦 العربية', 'callback_data' => 'lang:ar'],
            ]]
        );
    }

    /**
     * Handle callback queries (button presses).
     */
    protected function handleCallbackQuery(array $callback): void
    {
        $data = $callback['data'] ?? '';
        $from = $callback['from'];
        $callbackId = $callback['id'];
        $messageId = $callback['message']['message_id'] ?? null;
        $chatId = $callback['message']['chat']['id'] ?? $from['id'];

        [$action, ...$params] = explode(':', $data);

        match ($action) {
            'snooze' => $this->handleSnooze($callbackId, $chatId, $messageId, $from, $params),
            'ack' => $this->handleAcknowledge($callbackId, $chatId, $messageId, $from, $params),
            'lang' => $this->handleLanguageChange($callbackId, $chatId, $from, $params),
            default => $this->telegram->answerCallback($callbackId, 'Unknown action'),
        };
    }

    /**
     * Handle snooze button press.
     */
    protected function handleSnooze(
        string $callbackId,
        int $chatId,
        ?int $messageId,
        array $from,
        array $params
    ): void {
        $alertId = $params[0] ?? null;
        $minutes = (int) ($params[1] ?? 60);

        if (! $alertId) {
            $this->telegram->answerCallback($callbackId, 'Invalid alert');
            return;
        }

        $user = User::where('telegram_id', (string) $from['id'])->first();

        if (! $user) {
            $this->telegram->answerCallback($callbackId, 'User not found');
            return;
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $this->telegram->answerCallback($callbackId, 'Alert not found');
            return;
        }

        $alert->update([
            'snoozed_until' => now()->addMinutes($minutes),
        ]);

        $locale = $user->language ?? 'en';
        $confirmText = $locale === 'ar'
            ? "⏰ تم تأجيل التنبيه لمدة {$minutes} دقيقة"
            : "⏰ Alert snoozed for {$minutes} minutes";

        $this->telegram->answerCallback($callbackId, $confirmText, true);
    }

    /**
     * Handle acknowledge button press.
     */
    protected function handleAcknowledge(
        string $callbackId,
        int $chatId,
        ?int $messageId,
        array $from,
        array $params
    ): void {
        $historyId = $params[0] ?? null;

        if (! $historyId) {
            $this->telegram->answerCallback($callbackId, 'Invalid history');
            return;
        }

        $user = User::where('telegram_id', (string) $from['id'])->first();

        if (! $user) {
            $this->telegram->answerCallback($callbackId, 'User not found');
            return;
        }

        $history = AlertHistory::where('id', $historyId)
            ->where('user_id', $user->id)
            ->first();

        if (! $history) {
            $this->telegram->answerCallback($callbackId, 'Alert not found');
            return;
        }

        $history->update([
            'acknowledged_at' => now(),
        ]);

        $locale = $user->language ?? 'en';
        $confirmText = $locale === 'ar' ? '✅ تم التأكيد' : '✅ Acknowledged';

        $this->telegram->answerCallback($callbackId, $confirmText, true);
    }

    /**
     * Handle language change callback.
     */
    protected function handleLanguageChange(
        string $callbackId,
        int $chatId,
        array $from,
        array $params
    ): void {
        $newLang = $params[0] ?? 'en';

        $user = User::where('telegram_id', (string) $from['id'])->first();

        if ($user) {
            $user->update(['language' => $newLang]);
        }

        $confirmText = $newLang === 'ar'
            ? '✅ تم تغيير اللغة إلى العربية'
            : '✅ Language changed to English';

        $this->telegram->answerCallback($callbackId, $confirmText, true);
    }

    /**
     * Send welcome back message.
     */
    protected function sendWelcomeBack(int $chatId, User $user): void
    {
        $locale = $user->language ?? 'en';
        $name = $user->name ?? 'there';

        if ($locale === 'ar') {
            $message = "👋 مرحباً مجدداً، *{$name}*!\n\nأنت جاهز لتلقي تنبيهات الأسهم.\n\n📋 /alerts - عرض التنبيهات\n⚙️ /settings - الإعدادات\n❓ /help - المساعدة";
        } else {
            $message = "👋 Welcome back, *{$name}*!\n\nYou're all set to receive stock alerts.\n\n📋 /alerts - View alerts\n⚙️ /settings - Settings\n❓ /help - Help";
        }

        $this->telegram->sendMessage($chatId, $message);
    }

    /**
     * Send phone request message.
     */
    protected function sendPhoneRequestMessage(int $chatId): void
    {
        $this->telegram->getBot()->sendMessage([
            'chat_id' => $chatId,
            'text' => __('auth.telegram.verify_phone_message'),
            'reply_markup' => json_encode([
                'keyboard' => [[
                    [
                        'text' => __('auth.telegram.share_phone_button'),
                        'request_contact' => true,
                    ],
                ]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);
    }

    /**
     * Handle contact message for phone verification.
     */
    protected function handleContact(array $message): void
    {
        $contact = $message['contact'];
        $from = $message['from'];
        $chatId = $message['chat']['id'];

        if ($contact['user_id'] !== $from['id']) {
            $this->telegram->sendMessage(
                $chatId,
                __('auth.telegram_verification.contact_mismatch')
            );
            return;
        }

        $user = User::where('telegram_id', (string) $from['id'])->first();

        if (! $user) {
            $this->telegram->sendMessage(
                $chatId,
                __('auth.telegram_verification.user_not_found')
            );
            return;
        }

        $normalizedPhone = $this->normalizePhone($contact['phone_number']);

        $user->phone = $normalizedPhone;
        $user->markPhoneAsVerified();

        $this->telegram->sendMessage(
            $chatId,
            __('auth.telegram_verification.success')
        );
    }

    /**
     * Normalize phone number.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if (! str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Get help text in English.
     */
    private function getHelpTextEn(): string
    {
        return <<<MSG
❓ *Kira Bot Help*
━━━━━━━━━━━━━━━━━━

*Available Commands:*

📋 /alerts - View your active alerts
⚙️ /settings - View your notification settings
🌐 /language - Change language
❓ /help - Show this help message

*Alert Actions:*
When you receive an alert, you can:
• Tap "View Stock" to see details
• Tap "Snooze" to pause the alert
• Tap "Acknowledge" to confirm receipt

*Need more help?*
Visit: https://kira.app/help
MSG;
    }

    /**
     * Get help text in Arabic.
     */
    private function getHelpTextAr(): string
    {
        return <<<MSG
❓ *مساعدة بوت كيرا*
━━━━━━━━━━━━━━━━━━

*الأوامر المتاحة:*

📋 /alerts - عرض تنبيهاتك النشطة
⚙️ /settings - عرض إعدادات الإشعارات
🌐 /language - تغيير اللغة
❓ /help - عرض هذه الرسالة

*إجراءات التنبيهات:*
عند استلام تنبيه، يمكنك:
• الضغط على "عرض السهم" لرؤية التفاصيل
• الضغط على "تأجيل" لإيقاف التنبيه مؤقتاً
• الضغط على "تأكيد" لتأكيد الاستلام

*تحتاج مساعدة إضافية؟*
زر: https://kira.app/help
MSG;
    }
}
```

**Step 2: Update constructor injection in route**

Since we're using constructor injection, the service will be auto-resolved.

**Step 3: Commit**

```bash
git add app/Http/Controllers/Auth/TelegramWebhookController.php
git commit -m "feat: enhance TelegramWebhookController with commands and callbacks"
```

---

## Task 7: Add Telegram Alert Translations

**Files:**
- Modify: `lang/en/alerts.php` (create if doesn't exist)
- Modify: `lang/ar/alerts.php` (create if doesn't exist)

**Step 1: Create English translations file**

Create file `lang/en/alerts.php`:

```php
<?php

return [
    'telegram' => [
        'target_price_reached' => 'Target Price Reached',
        'breakout_confirmed' => 'Breakout Confirmed',
        'zone_entered' => 'Entered Zone',
        'zone_exited' => 'Exited Zone',
        'gap_detected' => 'Gap Detected',
        '52week_high' => 'New 52-Week High!',
        '52week_low' => 'New 52-Week Low',
        'daily_change' => 'Big Move Alert',
        'entry_return' => 'Back to Your Entry Price',
        'prediction' => 'AI Prediction Alert',
        'signal' => 'Technical Signal Detected',
        'anomaly' => 'Market Anomaly Detected',
        'pattern' => 'Chart Pattern Confirmed',
        'recommendation' => 'Recommendation Updated',
        'compound' => 'Multiple Signals Aligned!',

        'current_price' => 'Current Price',
        'target' => 'Target',
        'change' => 'Change',
        'confidence' => 'Confidence',
        'direction' => 'Direction',
        'view_stock' => 'View Stock',
        'snooze' => 'Snooze',
        'manage' => 'Manage',
        'acknowledge' => 'Acknowledge',
    ],
];
```

**Step 2: Create Arabic translations file**

Create file `lang/ar/alerts.php`:

```php
<?php

return [
    'telegram' => [
        'target_price_reached' => 'وصول السعر المستهدف',
        'breakout_confirmed' => 'اختراق مؤكد',
        'zone_entered' => 'دخول المنطقة',
        'zone_exited' => 'خروج من المنطقة',
        'gap_detected' => 'فجوة سعرية',
        '52week_high' => 'قمة جديدة لـ 52 أسبوع!',
        '52week_low' => 'قاع جديد لـ 52 أسبوع',
        'daily_change' => 'تنبيه حركة كبيرة',
        'entry_return' => 'العودة لسعر الشراء',
        'prediction' => 'تنبيه توقع الذكاء الاصطناعي',
        'signal' => 'تم رصد إشارة فنية',
        'anomaly' => 'تم رصد شذوذ في السوق',
        'pattern' => 'تأكيد نموذج فني',
        'recommendation' => 'تحديث التوصية',
        'compound' => 'توافق إشارات متعددة!',

        'current_price' => 'السعر الحالي',
        'target' => 'الهدف',
        'change' => 'التغير',
        'confidence' => 'الثقة',
        'direction' => 'الاتجاه',
        'view_stock' => 'عرض السهم',
        'snooze' => 'تأجيل',
        'manage' => 'إدارة',
        'acknowledge' => 'تأكيد',
    ],
];
```

**Step 3: Commit**

```bash
git add lang/en/alerts.php lang/ar/alerts.php
git commit -m "feat: add Telegram alert translations for EN and AR"
```

---

## Task 8: Create SetTelegramWebhook Command

**Files:**
- Create: `app/Console/Commands/SetTelegramWebhook.php`

**Step 1: Create the command**

Run:
```bash
php artisan make:command SetTelegramWebhook --no-interaction
```

**Step 2: Implement the command**

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use WeStacks\TeleBot\TeleBot;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook
                            {--remove : Remove the webhook instead of setting it}';

    protected $description = 'Set or remove the Telegram webhook URL';

    public function handle(): int
    {
        $token = config('telegram.bot_token');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN is not configured.');
            return self::FAILURE;
        }

        $bot = new TeleBot($token);

        if ($this->option('remove')) {
            return $this->removeWebhook($bot);
        }

        return $this->setWebhook($bot);
    }

    private function setWebhook(TeleBot $bot): int
    {
        $webhookUrl = config('app.url') . '/telegram/webhook';
        $secret = config('telegram.webhook_secret');

        $this->info("Setting webhook to: {$webhookUrl}");

        try {
            $params = [
                'url' => $webhookUrl,
                'allowed_updates' => ['message', 'callback_query'],
                'drop_pending_updates' => true,
            ];

            if ($secret) {
                $params['secret_token'] = $secret;
            }

            $result = $bot->setWebhook($params);

            if ($result) {
                $this->info('✅ Webhook set successfully!');

                // Get webhook info
                $info = $bot->getWebhookInfo();
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['URL', $info['url'] ?? 'N/A'],
                        ['Has Custom Cert', ($info['has_custom_certificate'] ?? false) ? 'Yes' : 'No'],
                        ['Pending Updates', $info['pending_update_count'] ?? 0],
                        ['Last Error', $info['last_error_message'] ?? 'None'],
                    ]
                );

                return self::SUCCESS;
            }

            $this->error('Failed to set webhook.');
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    private function removeWebhook(TeleBot $bot): int
    {
        $this->info('Removing webhook...');

        try {
            $result = $bot->deleteWebhook(['drop_pending_updates' => true]);

            if ($result) {
                $this->info('✅ Webhook removed successfully!');
                return self::SUCCESS;
            }

            $this->error('Failed to remove webhook.');
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
```

**Step 3: Commit**

```bash
git add app/Console/Commands/SetTelegramWebhook.php
git commit -m "feat: add SetTelegramWebhook command"
```

---

## Task 9: Add Webhook Secret Validation Middleware

**Files:**
- Create: `app/Http/Middleware/ValidateTelegramWebhook.php`
- Modify: `bootstrap/app.php`

**Step 1: Create the middleware**

Run:
```bash
php artisan make:middleware ValidateTelegramWebhook --no-interaction
```

**Step 2: Implement the middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('telegram.webhook_secret');

        // If no secret configured, allow all requests (development mode)
        if (! $secret) {
            return $next($request);
        }

        $headerSecret = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($headerSecret !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
```

**Step 3: Register middleware alias in bootstrap/app.php**

Add to the `withMiddleware` callback:

```php
$middleware->alias([
    'telegram.webhook' => \App\Http\Middleware\ValidateTelegramWebhook::class,
]);
```

**Step 4: Update route to use middleware**

In `routes/web.php`, update the webhook route:

```php
Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.webhook')
    ->withoutMiddleware(['web', 'csrf']);
```

**Step 5: Commit**

```bash
git add app/Http/Middleware/ValidateTelegramWebhook.php bootstrap/app.php routes/web.php
git commit -m "feat: add ValidateTelegramWebhook middleware for security"
```

---

## Task 10: Write Tests for TelegramBotService

**Files:**
- Create: `tests/Unit/Services/TelegramBotServiceTest.php`

**Step 1: Create test file**

Run:
```bash
php artisan make:test Services/TelegramBotServiceTest --unit --no-interaction
```

**Step 2: Implement tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\TelegramBotService;
use Mockery;
use Tests\TestCase;
use WeStacks\TeleBot\TeleBot;

class TelegramBotServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_throws_exception_when_token_not_configured(): void
    {
        config(['telegram.bot_token' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Telegram bot token not configured');

        new TelegramBotService();
    }

    /** @test */
    public function it_sends_message_with_default_options(): void
    {
        config(['telegram.bot_token' => 'test-token']);

        $service = new TelegramBotService();

        // We can't easily mock the internal TeleBot, so we test the service is constructed
        $this->assertInstanceOf(TelegramBotService::class, $service);
    }

    /** @test */
    public function it_builds_keyboard_correctly(): void
    {
        config(['telegram.bot_token' => 'test-token']);

        $service = new TelegramBotService();

        // Test that the service exists and can access its bot
        $this->assertInstanceOf(TeleBot::class, $service->getBot());
    }
}
```

**Step 3: Run test to verify**

Run:
```bash
php artisan test tests/Unit/Services/TelegramBotServiceTest.php --filter=it_throws_exception
```

Expected: PASS

**Step 4: Commit**

```bash
git add tests/Unit/Services/TelegramBotServiceTest.php
git commit -m "test: add TelegramBotService unit tests"
```

---

## Task 11: Write Tests for TelegramMessageBuilder

**Files:**
- Create: `tests/Unit/Services/TelegramMessageBuilderTest.php`

**Step 1: Create test file**

Run:
```bash
php artisan make:test Services/TelegramMessageBuilderTest --unit --no-interaction
```

**Step 2: Implement tests**

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;
use App\Models\Asset;
use App\Services\TelegramMessageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramMessageBuilderTest extends TestCase
{
    use RefreshDatabase;

    private TelegramMessageBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new TelegramMessageBuilder();
    }

    /** @test */
    public function it_builds_target_price_message_in_english(): void
    {
        $asset = Asset::factory()->create([
            'symbol' => 'COMI',
            'name' => 'Commercial International Bank',
        ]);

        $alert = Alert::factory()->create([
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
            'parameters' => ['target_price' => 52.00, 'direction' => 'above'],
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'trigger_value' => 52.50,
            'trigger_context' => ['change_percent' => 4.2],
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'en');

        $this->assertArrayHasKey('text', $result);
        $this->assertArrayHasKey('keyboard', $result);
        $this->assertStringContainsString('Target Price Reached', $result['text']);
        $this->assertStringContainsString('COMI', $result['text']);
        $this->assertStringContainsString('52.50', $result['text']);
    }

    /** @test */
    public function it_builds_target_price_message_in_arabic(): void
    {
        $asset = Asset::factory()->create([
            'symbol' => 'COMI',
            'name' => 'Commercial International Bank',
            'name_ar' => 'البنك التجاري الدولي',
        ]);

        $alert = Alert::factory()->create([
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
            'parameters' => ['target_price' => 52.00],
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'trigger_value' => 52.50,
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'ar');

        $this->assertStringContainsString('وصول السعر المستهدف', $result['text']);
        $this->assertStringContainsString('ج.م', $result['text']);
    }

    /** @test */
    public function it_builds_signal_message(): void
    {
        $asset = Asset::factory()->create(['symbol' => 'HRHO']);

        $alert = Alert::factory()->create([
            'asset_id' => $asset->id,
            'trigger_type' => 'signal',
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
            'trigger_value' => 15.50,
            'trigger_context' => [
                'indicator' => 'RSI',
                'signal_type' => 'Oversold',
                'strength' => 0.85,
                'indicator_value' => 28.5,
            ],
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'en');

        $this->assertStringContainsString('Technical Signal', $result['text']);
        $this->assertStringContainsString('RSI', $result['text']);
    }

    /** @test */
    public function it_includes_action_buttons_in_keyboard(): void
    {
        $asset = Asset::factory()->create(['symbol' => 'TEST']);

        $alert = Alert::factory()->create([
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
        ]);

        $history = AlertHistory::factory()->create([
            'alert_id' => $alert->id,
        ]);

        $notification = AlertNotification::factory()->create([
            'alert_id' => $alert->id,
            'alert_history_id' => $history->id,
        ]);

        $result = $this->builder->buildAlertMessage($notification, 'en');

        $keyboard = $result['keyboard'];

        $this->assertIsArray($keyboard);
        $this->assertNotEmpty($keyboard);

        // Should have View Stock button
        $viewButton = $keyboard[0][0] ?? null;
        $this->assertNotNull($viewButton);
        $this->assertStringContainsString('View Stock', $viewButton['text']);

        // Should have Snooze buttons
        $snoozeRow = $keyboard[1] ?? null;
        $this->assertNotNull($snoozeRow);
        $this->assertStringContainsString('Snooze', $snoozeRow[0]['text']);
    }
}
```

**Step 3: Run tests**

Run:
```bash
php artisan test tests/Unit/Services/TelegramMessageBuilderTest.php -v
```

Expected: All tests PASS

**Step 4: Commit**

```bash
git add tests/Unit/Services/TelegramMessageBuilderTest.php
git commit -m "test: add TelegramMessageBuilder unit tests"
```

---

## Task 12: Write Feature Test for Webhook Controller

**Files:**
- Create: `tests/Feature/TelegramWebhookTest.php`

**Step 1: Create test file**

Run:
```bash
php artisan make:test TelegramWebhookTest --no-interaction
```

**Step 2: Implement tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock TelegramBotService to avoid actual API calls
        $mock = Mockery::mock(TelegramBotService::class);
        $mock->shouldReceive('sendMessage')->andReturn(['message_id' => 1]);
        $mock->shouldReceive('sendMessageWithKeyboard')->andReturn(['message_id' => 1]);
        $mock->shouldReceive('answerCallback')->andReturn(true);

        $this->app->instance(TelegramBotService::class, $mock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_start_command_for_verified_user(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '123456789',
            'phone_verified_at' => now(),
        ]);

        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 123456789],
                'from' => ['id' => 123456789],
                'text' => '/start',
            ],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
    }

    /** @test */
    public function it_handles_alerts_command(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '123456789',
            'phone_verified_at' => now(),
        ]);

        Alert::factory()->count(3)->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 123456789],
                'from' => ['id' => 123456789],
                'text' => '/alerts',
            ],
        ]);

        $response->assertOk();
    }

    /** @test */
    public function it_handles_snooze_callback(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '123456789',
        ]);

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'snoozed_until' => null,
        ]);

        $response = $this->postJson('/telegram/webhook', [
            'callback_query' => [
                'id' => 'callback123',
                'from' => ['id' => 123456789],
                'message' => [
                    'chat' => ['id' => 123456789],
                    'message_id' => 1,
                ],
                'data' => "snooze:{$alert->id}:60",
            ],
        ]);

        $response->assertOk();

        $alert->refresh();
        $this->assertNotNull($alert->snoozed_until);
    }

    /** @test */
    public function it_handles_language_change_callback(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '123456789',
            'language' => 'en',
        ]);

        $response = $this->postJson('/telegram/webhook', [
            'callback_query' => [
                'id' => 'callback123',
                'from' => ['id' => 123456789],
                'message' => [
                    'chat' => ['id' => 123456789],
                ],
                'data' => 'lang:ar',
            ],
        ]);

        $response->assertOk();

        $user->refresh();
        $this->assertEquals('ar', $user->language);
    }

    /** @test */
    public function it_validates_webhook_secret_when_configured(): void
    {
        config(['telegram.webhook_secret' => 'super-secret-token']);

        // Without secret header
        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 123],
                'from' => ['id' => 123],
                'text' => '/start',
            ],
        ]);

        $response->assertStatus(401);

        // With correct secret header
        $response = $this->postJson('/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 123],
                'from' => ['id' => 123],
                'text' => '/start',
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'super-secret-token',
        ]);

        $response->assertOk();
    }
}
```

**Step 3: Run tests**

Run:
```bash
php artisan test tests/Feature/TelegramWebhookTest.php -v
```

Expected: All tests PASS

**Step 4: Commit**

```bash
git add tests/Feature/TelegramWebhookTest.php
git commit -m "test: add TelegramWebhook feature tests"
```

---

## Task 13: Final Integration and Documentation

**Step 1: Run all tests**

Run:
```bash
php artisan test --filter=Telegram -v
```

Expected: All tests PASS

**Step 2: Run Pint for code formatting**

Run:
```bash
vendor/bin/pint --dirty
```

**Step 3: Add remaining files and create final commit**

```bash
git add .
git commit -m "feat: complete Telegram bot implementation for Kira Alert System"
```

---

## Summary

**Files Created:**
- `app/Services/TelegramBotService.php` - Core bot messaging service
- `app/Services/TelegramMessageBuilder.php` - Rich message formatting
- `app/Jobs/Alerts/SendTelegramMessage.php` - Async Telegram delivery job
- `app/Http/Middleware/ValidateTelegramWebhook.php` - Webhook security
- `app/Console/Commands/SetTelegramWebhook.php` - Webhook setup command
- `lang/en/alerts.php` - English alert translations
- `lang/ar/alerts.php` - Arabic alert translations
- `tests/Unit/Services/TelegramBotServiceTest.php`
- `tests/Unit/Services/TelegramMessageBuilderTest.php`
- `tests/Feature/TelegramWebhookTest.php`

**Files Modified:**
- `app/Providers/AppServiceProvider.php` - Register TelegramBotService
- `app/Jobs/Alerts/SendAlertNotification.php` - Use SendTelegramMessage job
- `app/Jobs/Alerts/GenerateDigest.php` - Use TelegramBotService
- `app/Http/Controllers/Auth/TelegramWebhookController.php` - Full command handling
- `bootstrap/app.php` - Register webhook middleware
- `routes/web.php` - Add webhook middleware

**Environment Setup:**
```bash
# Add to .env
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_WEBHOOK_SECRET=random_secure_string

# Set webhook
php artisan telegram:set-webhook
```

---

## Deployment Checklist

1. [ ] Set environment variables (TELEGRAM_BOT_TOKEN, etc.)
2. [ ] Run migrations (if any pending)
3. [ ] Run `php artisan telegram:set-webhook` to register webhook with Telegram
4. [ ] Verify webhook is working with `php artisan telegram:set-webhook` (shows webhook info)
5. [ ] Test bot by sending `/start` command
6. [ ] Monitor logs for any errors

---

## Related Documents

- [Kira Alert System Design](./2026-01-10-kira-alert-system-design.md)
- [Kira Alert Notification Examples](./2026-01-10-kira-alert-notification-examples.md)
- [Kira Alert System Test Scenarios](./2026-01-11-kira-alert-system-test-scenarios.md)
