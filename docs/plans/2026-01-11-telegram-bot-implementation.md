# Telegram Bot Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement a full-featured Telegram bot for the Kira Alert System that sends rich, formatted notifications and handles user interactions.

**Architecture:** The implementation uses the **westacks/telebot-laravel** adapter which provides:
- `TeleBot` Facade for easy bot access
- Artisan commands for generating handlers (`make:telebot:*`)
- Built-in webhook and polling commands
- Notification channel integration
- Telegram logger driver

The implementation builds on existing alert infrastructure. A `TelegramBotService` wraps the Facade for testability. A `TelegramMessageBuilder` formats notifications per the design spec. Class-based handlers process commands and callbacks using the TeleBot handler pipeline.

**Tech Stack:** Laravel 12, westacks/telebot + westacks/telebot-laravel, Redis (queue), PHP 8.4

**Package Documentation:**
- [TeleBot Docs](https://westacks.github.io/telebot/)
- [TeleBot Laravel Adapter](https://github.com/westacks/telebot-laravel)

---

## Prerequisites

**Step 0: Install Laravel Adapter**

The project has `westacks/telebot` installed, but needs the Laravel adapter for Facade, artisan commands, and notification channel:

```bash
composer require westacks/telebot-laravel
php artisan telebot:install
```

This will:
- Publish `config/telebot.php` configuration
- Set up the TeleBot Facade
- Register artisan commands

**Existing Files Referenced:**
- `app/Jobs/Alerts/SendAlertNotification.php` - Has placeholder `sendTelegram()`
- `app/Jobs/Alerts/GenerateDigest.php` - Has TODO for TelegramBotService
- `app/Jobs/Alerts/ProcessEscalation.php` - Needs Telegram integration
- `app/Http/Controllers/Auth/TelegramWebhookController.php` - Basic webhook (will be replaced with handler classes)
- `config/telegram.php` - Existing bot config (will migrate to `config/telebot.php`)
- `docs/plans/2026-01-10-kira-alert-notification-examples.md` - Message formats

**Environment Variables Required:**
```
TELEGRAM_BOT_TOKEN=your_bot_token_from_botfather
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_WEBHOOK_SECRET=random_secure_string
```

---

## Task 1: Configure TeleBot Laravel and Create TelegramBotService

**Files:**
- Modify: `config/telebot.php` (created by `telebot:install`)
- Create: `app/Services/TelegramBotService.php`

**Step 1: Configure telebot.php**

After running `php artisan telebot:install`, update `config/telebot.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Bot
    |--------------------------------------------------------------------------
    |
    | The default bot that will be used when no bot is specified.
    |
    */
    'default' => 'kira',

    /*
    |--------------------------------------------------------------------------
    | Bots
    |--------------------------------------------------------------------------
    |
    | Configure your Telegram bots here.
    |
    */
    'bots' => [
        'kira' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'name' => env('TELEGRAM_BOT_USERNAME'),

            // Handler kernel for processing updates
            'kernel' => \App\Telegram\KiraKernel::class,

            // Webhook configuration
            'webhook' => [
                'url' => env('APP_URL') . '/telegram/webhook',
                'certificate' => null,
                'ip_address' => null,
                'max_connections' => 40,
                'allowed_updates' => ['message', 'callback_query'],
                'drop_pending_updates' => false,
                'secret_token' => env('TELEGRAM_WEBHOOK_SECRET'),
            ],
        ],
    ],
];
```

**Step 2: Create the TelegramBotService wrapper**

Run:
```bash
php artisan make:class Services/TelegramBotService --no-interaction
```

Implement with TeleBot Facade:

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Laravel\TeleBot;
use WeStacks\TeleBot\Objects\Message;

class TelegramBotService
{
    /**
     * Send a text message to a chat.
     */
    public function sendMessage(
        int|string $chatId,
        string $text,
        array $options = []
    ): ?Message {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ], $options);

            $result = TeleBot::sendMessage($params);

            Log::debug('Telegram message sent', [
                'chat_id' => $chatId,
                'message_id' => $result->message_id ?? null,
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
    ): ?Message {
        $options['reply_markup'] = [
            'inline_keyboard' => $keyboard,
        ];

        return $this->sendMessage($chatId, $text, $options);
    }

    /**
     * Send a message with reply keyboard (e.g., phone request).
     */
    public function sendMessageWithReplyKeyboard(
        int|string $chatId,
        string $text,
        array $keyboard,
        array $options = []
    ): ?Message {
        $options['reply_markup'] = array_merge([
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ], $options['reply_markup'] ?? []);

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
    ): ?Message {
        try {
            $params = array_merge([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ], $options);

            return TeleBot::editMessageText($params);
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
     * Edit message reply markup (keyboard).
     */
    public function editMessageKeyboard(
        int|string $chatId,
        int $messageId,
        array $keyboard
    ): bool {
        try {
            TeleBot::editMessageReplyMarkup([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reply_markup' => [
                    'inline_keyboard' => $keyboard,
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to edit message keyboard', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return false;
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
            TeleBot::answerCallbackQuery([
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
     * Remove reply keyboard after use.
     */
    public function removeKeyboard(int|string $chatId, string $text): ?Message
    {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => [
                'remove_keyboard' => true,
            ],
        ]);
    }

    /**
     * Get bot info.
     */
    public function getMe(): array
    {
        return (array) TeleBot::getMe();
    }
}
```

**Step 3: Register as singleton in AppServiceProvider**

Add to `app/Providers/AppServiceProvider.php` in the `register()` method:

```php
$this->app->singleton(\App\Services\TelegramBotService::class);
```

**Step 4: Commit**

```bash
git add config/telebot.php app/Services/TelegramBotService.php app/Providers/AppServiceProvider.php
git commit -m "feat: configure TeleBot Laravel and add TelegramBotService"
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

## Task 6: Create TeleBot Kernel and Handler Classes

**Overview:** Use TeleBot's handler pipeline pattern instead of a monolithic controller. This provides:
- Separation of concerns (one handler per command/callback type)
- Automatic command registration via `php artisan telebot:commands --setup`
- Built-in state management via `RequestInputHandler`
- Testable handler classes

**Files:**
- Create: `app/Telegram/KiraKernel.php`
- Create: `app/Telegram/Commands/StartCommand.php`
- Create: `app/Telegram/Commands/HelpCommand.php`
- Create: `app/Telegram/Commands/AlertsCommand.php`
- Create: `app/Telegram/Commands/SettingsCommand.php`
- Create: `app/Telegram/Commands/LanguageCommand.php`
- Create: `app/Telegram/Handlers/SnoozeCallbackHandler.php`
- Create: `app/Telegram/Handlers/AcknowledgeCallbackHandler.php`
- Create: `app/Telegram/Handlers/LanguageCallbackHandler.php`
- Create: `app/Telegram/Handlers/ContactHandler.php`
- Modify: `app/Http/Controllers/Auth/TelegramWebhookController.php`

**Step 1: Create the Kernel**

Run:
```bash
php artisan make:telebot:kernel KiraKernel --no-interaction
```

Update `app/Telegram/KiraKernel.php`:

```php
<?php

namespace App\Telegram;

use App\Telegram\Commands\AlertsCommand;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\LanguageCommand;
use App\Telegram\Commands\SettingsCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Handlers\AcknowledgeCallbackHandler;
use App\Telegram\Handlers\ContactHandler;
use App\Telegram\Handlers\LanguageCallbackHandler;
use App\Telegram\Handlers\SnoozeCallbackHandler;
use WeStacks\TeleBot\Foundation\Kernel;

class KiraKernel extends Kernel
{
    /**
     * Registered update handlers.
     * Order matters - handlers are processed in sequence.
     */
    protected array $handlers = [
        // Commands (processed first)
        StartCommand::class,
        HelpCommand::class,
        AlertsCommand::class,
        SettingsCommand::class,
        LanguageCommand::class,

        // Callback handlers
        SnoozeCallbackHandler::class,
        AcknowledgeCallbackHandler::class,
        LanguageCallbackHandler::class,

        // Contact handler for phone verification
        ContactHandler::class,
    ];
}
```

**Step 2: Create StartCommand**

Run:
```bash
php artisan make:telebot:command-handler StartCommand --no-interaction
```

Update `app/Telegram/Commands/StartCommand.php`:

```php
<?php

namespace App\Telegram\Commands;

use App\Models\User;
use App\Services\TelegramBotService;
use WeStacks\TeleBot\Foundation\CommandHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class StartCommand extends CommandHandler
{
    protected static function command(): string
    {
        return 'start';
    }

    protected static function aliases(): array
    {
        return [];
    }

    protected static function description(): string
    {
        return 'Start the bot and verify your account';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $message = $update->message;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if ($user && $user->hasVerifiedPhone()) {
            $this->sendWelcomeBack($bot, $chatId, $user);
        } elseif ($user && ! $user->hasVerifiedPhone()) {
            $this->sendPhoneRequest($bot, $chatId);
        } else {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => __('auth.telegram.please_login_first'),
            ]);
        }

        return null; // Stop processing
    }

    private function sendWelcomeBack(TeleBot $bot, int $chatId, User $user): void
    {
        $locale = $user->language ?? 'en';
        $name = $user->name ?? 'there';

        $message = $locale === 'ar'
            ? "👋 مرحباً مجدداً، *{$name}*!\n\nأنت جاهز لتلقي تنبيهات الأسهم.\n\n📋 /alerts - عرض التنبيهات\n⚙️ /settings - الإعدادات\n❓ /help - المساعدة"
            : "👋 Welcome back, *{$name}*!\n\nYou're all set to receive stock alerts.\n\n📋 /alerts - View alerts\n⚙️ /settings - Settings\n❓ /help - Help";

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
        ]);
    }

    private function sendPhoneRequest(TeleBot $bot, int $chatId): void
    {
        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => __('auth.telegram.verify_phone_message'),
            'reply_markup' => [
                'keyboard' => [[
                    [
                        'text' => __('auth.telegram.share_phone_button'),
                        'request_contact' => true,
                    ],
                ]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ],
        ]);
    }
}
```

**Step 3: Create HelpCommand**

Run:
```bash
php artisan make:telebot:command-handler HelpCommand --no-interaction
```

Update `app/Telegram/Commands/HelpCommand.php`:

```php
<?php

namespace App\Telegram\Commands;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class HelpCommand extends CommandHandler
{
    protected static function command(): string
    {
        return 'help';
    }

    protected static function description(): string
    {
        return 'Show available commands and help';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $chatId = $update->message->chat->id;
        $telegramId = (string) $update->message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();
        $locale = $user?->language ?? 'en';

        $helpText = $locale === 'ar' ? $this->getHelpTextAr() : $this->getHelpTextEn();

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $helpText,
            'parse_mode' => 'Markdown',
        ]);

        return null;
    }

    private function getHelpTextEn(): string
    {
        return <<<'MSG'
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
Visit our app for full features.
MSG;
    }

    private function getHelpTextAr(): string
    {
        return <<<'MSG'
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
زر التطبيق للمزيد من الميزات.
MSG;
    }
}
```

**Step 4: Create AlertsCommand**

Run:
```bash
php artisan make:telebot:command-handler AlertsCommand --no-interaction
```

Update `app/Telegram/Commands/AlertsCommand.php`:

```php
<?php

namespace App\Telegram\Commands;

use App\Models\Alert;
use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class AlertsCommand extends CommandHandler
{
    protected static function command(): string
    {
        return 'alerts';
    }

    protected static function description(): string
    {
        return 'View your active alerts';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $chatId = $update->message->chat->id;
        $telegramId = (string) $update->message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => __('auth.telegram.please_login_first'),
            ]);
            return null;
        }

        $locale = $user->language ?? 'en';
        $alerts = Alert::where('user_id', $user->id)
            ->where('status', 'active')
            ->with('asset')
            ->take(10)
            ->get();

        if ($alerts->isEmpty()) {
            $message = $locale === 'ar'
                ? "📭 لا توجد تنبيهات نشطة.\n\nاستخدم التطبيق لإنشاء تنبيهات جديدة."
                : "📭 No active alerts.\n\nUse the app to create new alerts.";

            $bot->sendMessage(['chat_id' => $chatId, 'text' => $message]);
            return null;
        }

        $lines = [$locale === 'ar' ? '📋 *تنبيهاتك النشطة:*' : '📋 *Your Active Alerts:*'];
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        foreach ($alerts as $alert) {
            $symbol = $alert->asset?->symbol ?? 'N/A';
            $type = __("alerts.types.{$alert->trigger_type}", [], $locale);
            $lines[] = "• *{$symbol}* - {$type}";
        }

        $lines[] = '';
        $lines[] = $locale === 'ar'
            ? '📊 استخدم التطبيق لإدارة التنبيهات'
            : '📊 Use the app to manage alerts';

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => implode("\n", $lines),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '🔗 Open App', 'url' => config('app.url') . '/alerts'],
                ]],
            ],
        ]);

        return null;
    }
}
```

**Step 5: Create SettingsCommand**

Run:
```bash
php artisan make:telebot:command-handler SettingsCommand --no-interaction
```

Update `app/Telegram/Commands/SettingsCommand.php`:

```php
<?php

namespace App\Telegram\Commands;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CommandHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class SettingsCommand extends CommandHandler
{
    protected static function command(): string
    {
        return 'settings';
    }

    protected static function description(): string
    {
        return 'View your notification settings';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $chatId = $update->message->chat->id;
        $telegramId = (string) $update->message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => __('auth.telegram.please_login_first'),
            ]);
            return null;
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

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '⚙️ Open Settings', 'url' => config('app.url') . '/settings/alerts'],
                ]],
            ],
        ]);

        return null;
    }
}
```

**Step 6: Create LanguageCommand**

Run:
```bash
php artisan make:telebot:command-handler LanguageCommand --no-interaction
```

Update `app/Telegram/Commands/LanguageCommand.php`:

```php
<?php

namespace App\Telegram\Commands;

use WeStacks\TeleBot\Foundation\CommandHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class LanguageCommand extends CommandHandler
{
    protected static function command(): string
    {
        return 'language';
    }

    protected static function aliases(): array
    {
        return ['lang'];
    }

    protected static function description(): string
    {
        return 'Change your preferred language';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $chatId = $update->message->chat->id;

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => '🌐 Select your language / اختر لغتك:',
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => '🇬🇧 English', 'callback_data' => 'lang:en'],
                    ['text' => '🇸🇦 العربية', 'callback_data' => 'lang:ar'],
                ]],
            ],
        ]);

        return null;
    }
}
```

**Step 7: Create SnoozeCallbackHandler**

Run:
```bash
php artisan make:telebot:callback-handler SnoozeCallbackHandler --no-interaction
```

Update `app/Telegram/Handlers/SnoozeCallbackHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Models\Alert;
use App\Models\User;
use WeStacks\TeleBot\Foundation\CallbackHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class SnoozeCallbackHandler extends CallbackHandler
{
    /**
     * Regex pattern to match callback data.
     * Captures: alertId, minutes
     */
    protected static function pattern(): string
    {
        return '/^snooze:(\d+):(\d+)$/';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $callback = $update->callback_query;
        $telegramId = (string) $callback->from->id;

        // Extract matched groups from pattern
        $alertId = $this->match[1];
        $minutes = (int) $this->match[2];

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $bot->answerCallbackQuery([
                'callback_query_id' => $callback->id,
                'text' => 'User not found',
            ]);
            return null;
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->first();

        if (! $alert) {
            $bot->answerCallbackQuery([
                'callback_query_id' => $callback->id,
                'text' => 'Alert not found',
            ]);
            return null;
        }

        $alert->update([
            'snoozed_until' => now()->addMinutes($minutes),
        ]);

        $locale = $user->language ?? 'en';
        $confirmText = $locale === 'ar'
            ? "⏰ تم تأجيل التنبيه لمدة {$minutes} دقيقة"
            : "⏰ Alert snoozed for {$minutes} minutes";

        $bot->answerCallbackQuery([
            'callback_query_id' => $callback->id,
            'text' => $confirmText,
            'show_alert' => true,
        ]);

        return null;
    }
}
```

**Step 8: Create AcknowledgeCallbackHandler**

Run:
```bash
php artisan make:telebot:callback-handler AcknowledgeCallbackHandler --no-interaction
```

Update `app/Telegram/Handlers/AcknowledgeCallbackHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Models\AlertHistory;
use App\Models\User;
use WeStacks\TeleBot\Foundation\CallbackHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class AcknowledgeCallbackHandler extends CallbackHandler
{
    protected static function pattern(): string
    {
        return '/^ack:(\d+)$/';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $callback = $update->callback_query;
        $telegramId = (string) $callback->from->id;
        $historyId = $this->match[1];

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $bot->answerCallbackQuery([
                'callback_query_id' => $callback->id,
                'text' => 'User not found',
            ]);
            return null;
        }

        $history = AlertHistory::where('id', $historyId)
            ->where('user_id', $user->id)
            ->first();

        if (! $history) {
            $bot->answerCallbackQuery([
                'callback_query_id' => $callback->id,
                'text' => 'Alert not found',
            ]);
            return null;
        }

        $history->update([
            'acknowledged_at' => now(),
        ]);

        $locale = $user->language ?? 'en';
        $confirmText = $locale === 'ar' ? '✅ تم التأكيد' : '✅ Acknowledged';

        $bot->answerCallbackQuery([
            'callback_query_id' => $callback->id,
            'text' => $confirmText,
            'show_alert' => true,
        ]);

        return null;
    }
}
```

**Step 9: Create LanguageCallbackHandler**

Run:
```bash
php artisan make:telebot:callback-handler LanguageCallbackHandler --no-interaction
```

Update `app/Telegram/Handlers/LanguageCallbackHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use WeStacks\TeleBot\Foundation\CallbackHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class LanguageCallbackHandler extends CallbackHandler
{
    protected static function pattern(): string
    {
        return '/^lang:(en|ar)$/';
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $callback = $update->callback_query;
        $telegramId = (string) $callback->from->id;
        $newLang = $this->match[1];

        $user = User::where('telegram_id', $telegramId)->first();

        if ($user) {
            $user->update(['language' => $newLang]);
        }

        $confirmText = $newLang === 'ar'
            ? '✅ تم تغيير اللغة إلى العربية'
            : '✅ Language changed to English';

        $bot->answerCallbackQuery([
            'callback_query_id' => $callback->id,
            'text' => $confirmText,
            'show_alert' => true,
        ]);

        return null;
    }
}
```

**Step 10: Create ContactHandler for phone verification**

Run:
```bash
php artisan make:telebot:update-handler ContactHandler --no-interaction
```

Update `app/Telegram/Handlers/ContactHandler.php`:

```php
<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use WeStacks\TeleBot\Foundation\UpdateHandler;
use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

class ContactHandler extends UpdateHandler
{
    /**
     * Check if this handler should process the update.
     */
    public function trigger(Update $update, TeleBot $bot): bool
    {
        return isset($update->message->contact);
    }

    public function handle(TeleBot $bot, Update $update, callable $next): mixed
    {
        $message = $update->message;
        $contact = $message->contact;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;

        // Verify the contact belongs to the sender
        if ((string) $contact->user_id !== $telegramId) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => __('auth.telegram_verification.contact_mismatch'),
                'reply_markup' => ['remove_keyboard' => true],
            ]);
            return null;
        }

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $bot->sendMessage([
                'chat_id' => $chatId,
                'text' => __('auth.telegram_verification.user_not_found'),
                'reply_markup' => ['remove_keyboard' => true],
            ]);
            return null;
        }

        // Normalize and save phone
        $phone = $this->normalizePhone($contact->phone_number);
        $user->phone = $phone;
        $user->markPhoneAsVerified();

        $bot->sendMessage([
            'chat_id' => $chatId,
            'text' => __('auth.telegram_verification.success'),
            'reply_markup' => ['remove_keyboard' => true],
        ]);

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if (! str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
```

**Step 11: Update TelegramWebhookController to use TeleBot handler**

Update `app/Http/Controllers/Auth/TelegramWebhookController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Laravel\TeleBot;
use WeStacks\TeleBot\Objects\Update;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming Telegram webhook requests.
     *
     * Routes update through TeleBot's handler pipeline.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $update = Update::from($request->all());

            Log::debug('Telegram webhook received', [
                'update_id' => $update->update_id,
                'type' => $this->getUpdateType($update),
            ]);

            // Process through TeleBot's kernel handlers
            TeleBot::handle($update);

        } catch (\Exception $e) {
            Log::error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function getUpdateType(Update $update): string
    {
        if ($update->message) {
            return 'message';
        }
        if ($update->callback_query) {
            return 'callback_query';
        }

        return 'unknown';
    }
}
```

**Step 12: Commit all handler files**

```bash
git add app/Telegram/ app/Http/Controllers/Auth/TelegramWebhookController.php
git commit -m "feat: add TeleBot Kernel and handler classes for commands and callbacks"
```

**Step 13: Register bot commands with Telegram**

Run this after deployment to make commands visible in Telegram's command menu:

```bash
php artisan telebot:commands --setup
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

## Task 8: Setup Webhook Using Built-in Command

**Overview:** The `westacks/telebot-laravel` package provides built-in artisan commands for webhook management. No custom command needed!

**Available Commands:**

```bash
# Setup webhook (uses config from config/telebot.php)
php artisan telebot:webhook --setup

# Remove webhook
php artisan telebot:webhook --remove

# For local development without webhook (uses long polling)
php artisan telebot:polling
```

The webhook configuration is already in `config/telebot.php` (see Task 1), including the `secret_token` for security validation.

---

## Task 9: Add Webhook Secret Validation Middleware

**Files:**
- Create: `app/Http/Middleware/ValidateTelegramWebhook.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`

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
        $secret = config('telebot.bots.kira.webhook.secret_token');

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
use App\Http\Controllers\Auth\TelegramWebhookController;

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

**Overview:** TeleBot Laravel includes testing utilities via the `TeleBot::fake()` method, which records all API calls without actually sending them.

**Files:**
- Create: `tests/Unit/Services/TelegramBotServiceTest.php`

**Step 1: Create test file**

Run:
```bash
php artisan make:test Services/TelegramBotServiceTest --unit --no-interaction
```

**Step 2: Implement tests using TeleBot::fake()**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\TelegramBotService;
use Tests\TestCase;
use WeStacks\TeleBot\Laravel\TeleBot;
use WeStacks\TeleBot\Objects\Message;

class TelegramBotServiceTest extends TestCase
{
    protected TelegramBotService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable fake mode - no actual API calls
        TeleBot::fake();

        $this->service = app(TelegramBotService::class);
    }

    /** @test */
    public function it_sends_message_with_default_options(): void
    {
        $this->service->sendMessage('123456789', 'Hello, World!');

        // Assert that sendMessage was called with correct params
        TeleBot::assertSent('sendMessage', function ($params) {
            return $params['chat_id'] === '123456789'
                && $params['text'] === 'Hello, World!'
                && $params['parse_mode'] === 'Markdown';
        });
    }

    /** @test */
    public function it_sends_message_with_inline_keyboard(): void
    {
        $keyboard = [[
            ['text' => 'Button 1', 'callback_data' => 'action:1'],
            ['text' => 'Button 2', 'callback_data' => 'action:2'],
        ]];

        $this->service->sendMessageWithKeyboard('123456789', 'Choose:', $keyboard);

        TeleBot::assertSent('sendMessage', function ($params) {
            return $params['chat_id'] === '123456789'
                && isset($params['reply_markup']['inline_keyboard']);
        });
    }

    /** @test */
    public function it_sends_message_with_reply_keyboard(): void
    {
        $keyboard = [[
            ['text' => 'Share Phone', 'request_contact' => true],
        ]];

        $this->service->sendMessageWithReplyKeyboard('123456789', 'Share your phone:', $keyboard);

        TeleBot::assertSent('sendMessage', function ($params) {
            return isset($params['reply_markup']['keyboard'])
                && $params['reply_markup']['resize_keyboard'] === true;
        });
    }

    /** @test */
    public function it_edits_existing_message(): void
    {
        $this->service->editMessage('123456789', 999, 'Updated text');

        TeleBot::assertSent('editMessageText', function ($params) {
            return $params['chat_id'] === '123456789'
                && $params['message_id'] === 999
                && $params['text'] === 'Updated text';
        });
    }

    /** @test */
    public function it_answers_callback_query(): void
    {
        $this->service->answerCallback('callback_123', 'Action completed!', true);

        TeleBot::assertSent('answerCallbackQuery', function ($params) {
            return $params['callback_query_id'] === 'callback_123'
                && $params['text'] === 'Action completed!'
                && $params['show_alert'] === true;
        });
    }

    /** @test */
    public function it_removes_reply_keyboard(): void
    {
        $this->service->removeKeyboard('123456789', 'Keyboard removed');

        TeleBot::assertSent('sendMessage', function ($params) {
            return isset($params['reply_markup']['remove_keyboard'])
                && $params['reply_markup']['remove_keyboard'] === true;
        });
    }
}
```

**Step 3: Run test to verify**

Run:
```bash
php artisan test tests/Unit/Services/TelegramBotServiceTest.php
```

Expected: PASS

**Step 4: Commit**

```bash
git add tests/Unit/Services/TelegramBotServiceTest.php
git commit -m "test: add TelegramBotService unit tests with TeleBot::fake()"
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

**Step 2: Implement tests using TeleBot::fake()**

```php
<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use WeStacks\TeleBot\Laravel\TeleBot;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Use TeleBot fake to capture all API calls
        TeleBot::fake();
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

**Package Installed:**
- `westacks/telebot-laravel` - Laravel adapter for TeleBot with Facade, artisan commands, and notification channel

**Files Created:**
- `config/telebot.php` - TeleBot configuration
- `app/Services/TelegramBotService.php` - Wrapper service using TeleBot Facade
- `app/Services/TelegramMessageBuilder.php` - Rich message formatting
- `app/Jobs/Alerts/SendTelegramMessage.php` - Async Telegram delivery job
- `app/Telegram/KiraKernel.php` - TeleBot handler kernel
- `app/Telegram/Commands/StartCommand.php` - /start command handler
- `app/Telegram/Commands/HelpCommand.php` - /help command handler
- `app/Telegram/Commands/AlertsCommand.php` - /alerts command handler
- `app/Telegram/Commands/SettingsCommand.php` - /settings command handler
- `app/Telegram/Commands/LanguageCommand.php` - /language command handler
- `app/Telegram/Handlers/SnoozeCallbackHandler.php` - Snooze callback handler
- `app/Telegram/Handlers/AcknowledgeCallbackHandler.php` - Acknowledge callback handler
- `app/Telegram/Handlers/LanguageCallbackHandler.php` - Language change callback handler
- `app/Telegram/Handlers/ContactHandler.php` - Phone verification handler
- `app/Http/Middleware/ValidateTelegramWebhook.php` - Webhook security
- `lang/en/alerts.php` - English alert translations (telegram section)
- `lang/ar/alerts.php` - Arabic alert translations (telegram section)
- `tests/Unit/Services/TelegramBotServiceTest.php`
- `tests/Unit/Services/TelegramMessageBuilderTest.php`
- `tests/Feature/TelegramWebhookTest.php`

**Files Modified:**
- `app/Providers/AppServiceProvider.php` - Register TelegramBotService singleton
- `app/Jobs/Alerts/SendAlertNotification.php` - Use SendTelegramMessage job
- `app/Jobs/Alerts/GenerateDigest.php` - Use TelegramBotService
- `app/Http/Controllers/Auth/TelegramWebhookController.php` - Routes through TeleBot kernel
- `bootstrap/app.php` - Register webhook middleware
- `routes/web.php` - Add webhook middleware

**Environment Setup:**
```bash
# Add to .env
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_BOT_USERNAME=your_bot_username
TELEGRAM_WEBHOOK_SECRET=random_secure_string

# Install Laravel adapter
composer require westacks/telebot-laravel
php artisan telebot:install

# Set webhook
php artisan telebot:webhook --setup

# Register bot commands with Telegram
php artisan telebot:commands --setup
```

---

## Deployment Checklist

1. [ ] Install `westacks/telebot-laravel` package
2. [ ] Set environment variables (TELEGRAM_BOT_TOKEN, TELEGRAM_BOT_USERNAME, TELEGRAM_WEBHOOK_SECRET)
3. [ ] Run `php artisan telebot:install` to publish config
4. [ ] Run migrations (if any pending)
5. [ ] Run `php artisan telebot:webhook --setup` to register webhook with Telegram
6. [ ] Run `php artisan telebot:commands --setup` to register commands with Telegram's menu
7. [ ] Test bot by sending `/start` command
8. [ ] Monitor logs for any errors

---

## Complete User Flows

This section documents all user interactions with the Kira Telegram bot, from initial setup through daily usage.

### Flow 1: New User Registration & Onboarding

```
┌─────────────────────────────────────────────────────────────────┐
│                     NEW USER ONBOARDING                          │
└─────────────────────────────────────────────────────────────────┘

1. User visits Kira web app → Clicks "Login with Telegram"
   │
   ▼
2. Telegram Login Widget opens → User authorizes Kira app
   │
   ▼
3. System creates user account with:
   - telegram_id (from widget)
   - telegram_username (from widget)
   - telegram_photo_url (from widget)
   │
   ▼
4. User redirected to onboarding wizard → Prompted to verify phone
   │
   ▼
5. User clicks "Verify via Telegram Bot"
   │
   ▼
6. User opens @KiraStocksBot in Telegram → Sends /start
   │
   ▼
7. Bot detects user has telegram_id but no verified phone
   │
   ▼
8. Bot sends phone request message with ReplyKeyboard:
   ┌────────────────────────────────────────────────────┐
   │ 📱 To complete your registration, please share    │
   │ your phone number. This ensures secure delivery   │
   │ of your stock alerts.                             │
   │                                                    │
   │ ┌──────────────────────────────────────────────┐  │
   │ │    📞 Share Phone Number                     │  │
   │ └──────────────────────────────────────────────┘  │
   └────────────────────────────────────────────────────┘
   │
   ▼
9. User taps "Share Phone Number" → Telegram shares contact
   │
   ▼
10. ContactHandler processes contact:
    - Validates contact.user_id matches message.from.id
    - Normalizes phone number (adds + prefix)
    - Updates user.phone
    - Marks phone as verified (phone_verified_at)
    │
    ▼
11. Bot sends confirmation:
    ┌────────────────────────────────────────────────────┐
    │ ✅ Phone verified successfully!                    │
    │                                                    │
    │ You're all set to receive stock alerts.           │
    │                                                    │
    │ Use /help to see available commands.              │
    └────────────────────────────────────────────────────┘
    │
    ▼
12. User returns to web app → Completes remaining onboarding steps
```

### Flow 2: Returning User - Bot Interaction

```
┌─────────────────────────────────────────────────────────────────┐
│                     RETURNING USER                               │
└─────────────────────────────────────────────────────────────────┘

User opens @KiraStocksBot → Sends /start
   │
   ▼
Bot recognizes user (has telegram_id + verified phone)
   │
   ▼
Bot sends welcome back message:
┌────────────────────────────────────────────────────────────────┐
│ 👋 Welcome back, *Ahmed*!                                      │
│                                                                │
│ You're all set to receive stock alerts.                       │
│                                                                │
│ 📋 /alerts - View alerts                                       │
│ ⚙️ /settings - Settings                                        │
│ ❓ /help - Help                                                │
└────────────────────────────────────────────────────────────────┘
```

### Flow 3: Receiving Alert Notifications

```
┌─────────────────────────────────────────────────────────────────┐
│                 ALERT NOTIFICATION FLOW                          │
└─────────────────────────────────────────────────────────────────┘

1. ML Pipeline detects alert condition (e.g., target price reached)
   │
   ▼
2. Redis Pub/Sub publishes to classified_* channel
   │
   ▼
3. AlertsListen command receives message → Creates AlertHistory
   │
   ▼
4. SendAlertNotification job triggered
   │
   ├── Checks rate limits (10/hour, 50/day)
   ├── Checks quiet hours (bypass for critical)
   ├── Checks user's default_channels (includes 'telegram')
   │
   ▼
5. SendTelegramMessage job dispatched
   │
   ▼
6. TelegramMessageBuilder builds rich message based on alert type
   │
   ▼
7. TelegramBotService sends message via TeleBot Facade
   │
   ▼
8. User receives notification in Telegram:

   ┌────────────────────────────────────────────────────────────────┐
   │ 🎯 *Target Price Reached*                                     │
   │ ━━━━━━━━━━━━━━━━━━                                            │
   │                                                                │
   │ *COMI* - Commercial International Bank                        │
   │                                                                │
   │ 📈 Current Price: *52.50 EGP*                                 │
   │ 🎯 Target: 52.00 EGP                                          │
   │ 📊 Change: 4.2% today                                         │
   │                                                                │
   │ Your alert triggered at 52.00 EGP.                            │
   │                                                                │
   │ 🕐 10:34 AM · Jan 11, 2026                                    │
   │                                                                │
   │ ━━━━━━━━━━━━━━━━━━                                            │
   │                                                                │
   │ ┌────────────────────────────────────────────────────────┐    │
   │ │              📊 View Stock                             │    │
   │ └────────────────────────────────────────────────────────┘    │
   │ ┌───────────────────┐ ┌───────────────────┐                   │
   │ │   ⏰ Snooze 1h    │ │   ⏰ Snooze 4h    │                   │
   │ └───────────────────┘ └───────────────────┘                   │
   │ ┌───────────────────┐ ┌───────────────────┐                   │
   │ │  ✅ Acknowledge   │ │    ⚙️ Manage     │                   │
   │ └───────────────────┘ └───────────────────┘                   │
   └────────────────────────────────────────────────────────────────┘
```

### Flow 4: Alert Action Buttons

```
┌─────────────────────────────────────────────────────────────────┐
│                     ALERT ACTIONS                                │
└─────────────────────────────────────────────────────────────────┘

[View Stock Button]
   User taps "📊 View Stock"
   │
   ▼
   Opens Kira web app at /assets/{symbol} in browser
   Shows full stock details, charts, analysis

[Snooze Button]
   User taps "⏰ Snooze 1h" or "⏰ Snooze 4h"
   │
   ▼
   SnoozeCallbackHandler receives callback_data: "snooze:{alertId}:{minutes}"
   │
   ├── Validates user owns the alert
   ├── Updates alert.snoozed_until = now + minutes
   │
   ▼
   Bot shows toast notification:
   ┌────────────────────────────────┐
   │ ⏰ Alert snoozed for 60 min   │
   └────────────────────────────────┘

   During snooze period:
   - Alert remains active but notifications paused
   - User can still receive other alerts
   - After snooze expires, alert resumes normally

[Acknowledge Button]
   User taps "✅ Acknowledge"
   │
   ▼
   AcknowledgeCallbackHandler receives callback_data: "ack:{historyId}"
   │
   ├── Validates user owns the alert history
   ├── Updates alert_history.acknowledged_at = now
   │
   ▼
   Bot shows toast notification:
   ┌────────────────────────────────┐
   │ ✅ Acknowledged                │
   └────────────────────────────────┘

   Effects of acknowledgment:
   - Stops escalation chain for this alert trigger
   - Records engagement metrics
   - Alert remains active for future triggers

[Manage Button]
   User taps "⚙️ Manage"
   │
   ▼
   Opens Kira web app at /alerts/{alertId}
   User can edit, disable, or delete the alert
```

### Flow 5: Bot Commands

```
┌─────────────────────────────────────────────────────────────────┐
│                     BOT COMMANDS                                 │
└─────────────────────────────────────────────────────────────────┘

/start
   │
   ├─ New user without telegram_id → "Please login through the app first"
   ├─ User with telegram_id but no verified phone → Phone request keyboard
   └─ Verified user → Welcome back message with command list

/help
   │
   ▼
   Displays help message in user's language:
   ┌────────────────────────────────────────────────────────────────┐
   │ ❓ *Kira Bot Help*                                            │
   │ ━━━━━━━━━━━━━━━━━━                                            │
   │                                                                │
   │ *Available Commands:*                                         │
   │                                                                │
   │ 📋 /alerts - View your active alerts                          │
   │ ⚙️ /settings - View your notification settings                │
   │ 🌐 /language - Change language                                │
   │ ❓ /help - Show this help message                             │
   │                                                                │
   │ *Alert Actions:*                                              │
   │ When you receive an alert, you can:                           │
   │ • Tap "View Stock" to see details                             │
   │ • Tap "Snooze" to pause the alert                             │
   │ • Tap "Acknowledge" to confirm receipt                        │
   │                                                                │
   │ *Need more help?*                                             │
   │ Visit our app for full features.                              │
   └────────────────────────────────────────────────────────────────┘

/alerts
   │
   ├─ No active alerts → "📭 No active alerts. Use the app to create new alerts."
   │
   └─ Has alerts → List of active alerts:
      ┌────────────────────────────────────────────────────────────────┐
      │ 📋 *Your Active Alerts:*                                      │
      │ ━━━━━━━━━━━━━━━━━━                                            │
      │ • *COMI* - Target Price                                       │
      │ • *HRHO* - Breakout Alert                                     │
      │ • *EFIH* - AI Prediction                                      │
      │                                                                │
      │ 📊 Use the app to manage alerts                               │
      │                                                                │
      │ ┌────────────────────────────────────────────────────────┐    │
      │ │                🔗 Open App                             │    │
      │ └────────────────────────────────────────────────────────┘    │
      └────────────────────────────────────────────────────────────────┘

/settings
   │
   ▼
   Displays current settings:
   ┌────────────────────────────────────────────────────────────────┐
   │ ⚙️ *Your Settings*                                            │
   │ ━━━━━━━━━━━━━━━━━━                                            │
   │                                                                │
   │ 🌐 Language: English                                          │
   │ 🌙 Quiet Hours: 23:00 - 07:00                                 │
   │ 📊 Max alerts/hour: 10                                        │
   │ 📊 Max alerts/day: 50                                         │
   │                                                                │
   │ Use the app to modify settings.                               │
   │                                                                │
   │ ┌────────────────────────────────────────────────────────┐    │
   │ │              ⚙️ Open Settings                          │    │
   │ └────────────────────────────────────────────────────────┘    │
   └────────────────────────────────────────────────────────────────┘

/language (or /lang)
   │
   ▼
   Displays language selection:
   ┌────────────────────────────────────────────────────────────────┐
   │ 🌐 Select your language / اختر لغتك:                          │
   │                                                                │
   │ ┌───────────────────┐ ┌───────────────────┐                   │
   │ │  🇬🇧 English      │ │  🇸🇦 العربية       │                   │
   │ └───────────────────┘ └───────────────────┘                   │
   └────────────────────────────────────────────────────────────────┘
   │
   User taps language button
   │
   ▼
   LanguageCallbackHandler receives callback_data: "lang:ar" or "lang:en"
   │
   ├── Updates user.language
   │
   ▼
   Bot shows toast notification:
   ┌─────────────────────────────────────┐
   │ ✅ تم تغيير اللغة إلى العربية        │
   └─────────────────────────────────────┘
```

### Flow 6: Escalation Handling

```
┌─────────────────────────────────────────────────────────────────┐
│                     ESCALATION FLOW                              │
└─────────────────────────────────────────────────────────────────┘

1. Critical/High alert sent to user via Telegram
   │
   ▼
2. ProcessEscalation job scheduled (runs every 5 min)
   │
   ▼
3. Job checks for unacknowledged critical alerts
   │
   ├── Alert acknowledged? → No escalation needed
   │
   └── Alert NOT acknowledged after threshold?
       │
       ▼
4. Escalation Level 1 (after 15 min):
   ┌────────────────────────────────────────────────────────────────┐
   │ 🚨 *REMINDER: Unacknowledged Alert*                           │
   │ ━━━━━━━━━━━━━━━━━━                                            │
   │                                                                │
   │ ⚠️ You have an unacknowledged critical alert for *COMI*.     │
   │                                                                │
   │ Original alert: Target Price Reached (52.50 EGP)              │
   │ Sent: 15 minutes ago                                          │
   │                                                                │
   │ Please acknowledge or take action.                            │
   │                                                                │
   │ ┌────────────────────────────────────────────────────────┐    │
   │ │              ✅ Acknowledge Now                        │    │
   │ └────────────────────────────────────────────────────────┘    │
   └────────────────────────────────────────────────────────────────┘
   │
   ▼
5. Still not acknowledged after 30 min? → Escalation Level 2
   (Higher urgency message)
   │
   ▼
6. Still not acknowledged after 60 min? → Escalation Level 3
   (May trigger alternative channels: SMS, email, push)
```

### Flow 7: Digest Notifications

```
┌─────────────────────────────────────────────────────────────────┐
│                     DIGEST FLOW                                  │
└─────────────────────────────────────────────────────────────────┘

1. User configures digest preference in app:
   - Daily digest at 8:00 AM
   - Or Weekly digest on Sundays
   │
   ▼
2. GenerateDigest job runs at scheduled time
   │
   ▼
3. Job collects all alerts from period grouped by asset
   │
   ▼
4. Telegram digest sent:
   ┌────────────────────────────────────────────────────────────────┐
   │ 📊 *Daily Alert Digest*                                       │
   │ ━━━━━━━━━━━━━━━━━━                                            │
   │ Jan 11, 2026                                                  │
   │                                                                │
   │ *Summary:* 8 alerts across 5 stocks                           │
   │                                                                │
   │ 📈 *COMI* (3 alerts)                                          │
   │ • Target Price: 52.50 EGP ✅                                  │
   │ • Breakout Confirmed                                          │
   │ • AI Prediction: Bullish                                      │
   │                                                                │
   │ 📉 *HRHO* (2 alerts)                                          │
   │ • Daily Change: -5.2%                                         │
   │ • RSI Oversold Signal                                         │
   │                                                                │
   │ 📈 *EFIH* (1 alert)                                           │
   │ • Pattern: Double Bottom                                       │
   │                                                                │
   │ ... and 2 more                                                │
   │                                                                │
   │ ┌────────────────────────────────────────────────────────┐    │
   │ │              📊 View Full Report                       │    │
   │ └────────────────────────────────────────────────────────┘    │
   └────────────────────────────────────────────────────────────────┘
```

### Flow 8: Quiet Hours Behavior

```
┌─────────────────────────────────────────────────────────────────┐
│                     QUIET HOURS                                  │
└─────────────────────────────────────────────────────────────────┘

User sets quiet hours: 23:00 - 07:00 (Cairo timezone)

During quiet hours:
┌──────────────────────────────────────────────────────────────────┐
│                                                                  │
│  LOW/MEDIUM priority alerts:                                     │
│  ├── Queued until quiet hours end                               │
│  └── Delivered at 07:01 AM                                      │
│                                                                  │
│  HIGH priority alerts:                                           │
│  ├── May be held briefly                                        │
│  └── Delivered within configured delay                          │
│                                                                  │
│  CRITICAL priority alerts:                                       │
│  ├── Bypass quiet hours                                         │
│  └── Delivered immediately                                       │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### Flow 9: Alert Types & Their Telegram Presentations

```
┌─────────────────────────────────────────────────────────────────┐
│                     ALERT TYPE MESSAGES                          │
└─────────────────────────────────────────────────────────────────┘

PRICE-BASED ALERTS:

┌─ target_price ─────────────────────────────────────────────────┐
│ 🎯 *Target Price Reached*                                      │
│ Price crossed your configured threshold                        │
│ Shows: current price, target, % change, direction emoji        │
└────────────────────────────────────────────────────────────────┘

┌─ breakout ─────────────────────────────────────────────────────┐
│ 🚀 *Breakout Confirmed*                                        │
│ Price broke through resistance/support with volume             │
│ Shows: current price, breakout level, volume ratio             │
└────────────────────────────────────────────────────────────────┘

┌─ zone ─────────────────────────────────────────────────────────┐
│ 📍 *Zone Alert*                                                │
│ Price entered/exited a configured zone                         │
│ Shows: zone boundaries, direction                              │
└────────────────────────────────────────────────────────────────┘

┌─ gap ──────────────────────────────────────────────────────────┐
│ 🕳 *Gap Detected*                                              │
│ Significant price gap from previous close                      │
│ Shows: gap %, direction (up/down)                              │
└────────────────────────────────────────────────────────────────┘

┌─ 52week ───────────────────────────────────────────────────────┐
│ 🏆/⚠️ *52-Week High/Low*                                       │
│ Stock reached new 52-week extreme                              │
│ Shows: current price, type (high/low)                          │
└────────────────────────────────────────────────────────────────┘

┌─ daily_change ─────────────────────────────────────────────────┐
│ 📈/📉 *Big Move Alert*                                         │
│ Significant daily price change threshold crossed               │
│ Shows: % change, direction                                     │
└────────────────────────────────────────────────────────────────┘

┌─ entry_return ─────────────────────────────────────────────────┐
│ 🔄 *Back to Entry Price*                                       │
│ Price returned to user's recorded entry point                  │
│ Shows: current price, entry price, break-even note             │
└────────────────────────────────────────────────────────────────┘

INTELLIGENCE-BASED ALERTS:

┌─ prediction ───────────────────────────────────────────────────┐
│ 🔮 *AI Prediction Alert*                                       │
│ Kira AI made a directional prediction                          │
│ Shows: direction, horizon, confidence %                        │
└────────────────────────────────────────────────────────────────┘

┌─ signal ───────────────────────────────────────────────────────┐
│ 📊 *Technical Signal Detected*                                 │
│ Technical indicator generated a signal                         │
│ Shows: indicator name, signal type, strength %, value          │
└────────────────────────────────────────────────────────────────┘

┌─ anomaly ──────────────────────────────────────────────────────┐
│ ⚠️/🚨 *Market Anomaly Detected*                                │
│ Unusual market behavior detected by ML                         │
│ Shows: anomaly type, severity, confidence, reasons list        │
└────────────────────────────────────────────────────────────────┘

┌─ pattern ──────────────────────────────────────────────────────┐
│ 📐 *Chart Pattern Confirmed*                                   │
│ Technical chart pattern identified                             │
│ Shows: pattern type, status, confidence, target, bias          │
└────────────────────────────────────────────────────────────────┘

┌─ recommendation ───────────────────────────────────────────────┐
│ ⭐ *Recommendation Updated*                                    │
│ Analyst rating changed for the stock                           │
│ Shows: new rating, previous rating, score, upgrade/downgrade   │
└────────────────────────────────────────────────────────────────┘

┌─ compound_intelligence ────────────────────────────────────────┐
│ ⭐ *Multiple Signals Aligned!*                                 │
│ Multiple conditions met simultaneously (high conviction)       │
│ Shows: conditions checklist, combined confidence               │
└────────────────────────────────────────────────────────────────┘
```

### Flow 10: Error Handling & Edge Cases

```
┌─────────────────────────────────────────────────────────────────┐
│                     ERROR HANDLING                               │
└─────────────────────────────────────────────────────────────────┘

[User Blocks Bot]
   TeleBot API returns error 403 or "bot was blocked"
   │
   ▼
   SendTelegramMessage job:
   ├── Marks notification as failed
   ├── Clears user.telegram_id (removes Telegram channel)
   └── User can re-link via web app anytime

[Rate Limited by Telegram]
   TeleBot API returns error 429 with retry_after
   │
   ▼
   SendTelegramMessage job:
   ├── Extracts retry_after from error message
   └── Releases job back to queue with delay

[Invalid Chat ID]
   TeleBot API returns "chat not found"
   │
   ▼
   Same handling as blocked bot

[Network/Server Error]
   Connection timeout or 5xx error
   │
   ▼
   Job retries with exponential backoff:
   ├── Attempt 1: immediate
   ├── Attempt 2: +10 seconds
   ├── Attempt 3: +60 seconds
   └── Attempt 4: +300 seconds (final)

[User Not Found in Database]
   Webhook receives message from unknown telegram_id
   │
   ▼
   Bot responds: "Please login through the Kira app first"
```

### RTL (Arabic) Support

All messages support Arabic (RTL) display:
- Message text uses Arabic translations from `lang/ar/alerts.php`
- Asset names use `name_ar` field when available
- Dates formatted using Arabic locale
- Button labels translated (عرض السهم، تأجيل، تأكيد، إدارة)
- Currency shown as "ج.م" (Egyptian Pound)

---

## Related Documents

- [Kira Alert System Design](./2026-01-10-kira-alert-system-design.md)
- [Kira Alert Notification Examples](./2026-01-10-kira-alert-notification-examples.md)
- [Kira Alert System Test Scenarios](./2026-01-11-kira-alert-system-test-scenarios.md)
- [Registration & Onboarding Implementation](./2025-12-16-registration-onboarding-implementation.md)
- [Settings & Trading Market Preferences Design](./2025-12-16-settings-trading-market-preferences-design.md)
