<?php

namespace App\Telegram\Handlers\Alerts;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use App\Telegram\Services\AlertKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for alert creation flow.
 *
 * Callback patterns:
 * - alert:create - Start creation / show type selector
 * - alert:create:type:{type} - Select alert type
 * - alert:create:asset:{id} - Select asset
 * - alert:create:asset:search - Trigger asset search
 * - alert:create:trigger:{type} - Select trigger type
 * - alert:create:direction:{dir} - Select direction
 * - alert:create:confirm - Confirm and create alert
 * - alert:menu - Return to main menu
 */
class AlertCreateHandler extends CallbackHandler
{
    protected string $match = '/^alert:(create|menu)/';

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
        $action = $parts[1] ?? null;
        $subAction = $parts[2] ?? null;
        $value = $parts[3] ?? null;
        $locale = $user->language ?? 'en';

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        // Return to main menu
        if ($action === 'menu') {
            return $this->showMainMenu($chatId, $messageId, $user, $locale);
        }

        // Handle creation flow
        return match ($subAction) {
            null => $this->showTypeSelector($chatId, $messageId, $user, $locale),
            'type' => $this->handleTypeSelection($user, $value, $chatId, $messageId, $locale),
            'asset' => $this->handleAssetSelection($user, $value, $chatId, $messageId, $locale),
            'trigger' => $this->handleTriggerSelection($user, $value, $chatId, $messageId, $locale),
            'direction' => $this->handleDirectionSelection($user, $value, $chatId, $messageId, $locale),
            'confirm' => $this->handleConfirm($user, $chatId, $messageId, $locale),
            default => $this->showTypeSelector($chatId, $messageId, $user, $locale),
        };
    }

    private function showMainMenu(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        // Clear any draft
        $user->update(['telegram_alert_draft' => null]);

        $builder = new AlertKeyboardBuilder;

        $activeCount = Alert::where('user_id', $user->id)->active()->count();
        $triggeredToday = Alert::where('user_id', $user->id)
            ->whereDate('last_triggered_at', today())
            ->count();

        $statsLine = $locale === 'ar'
            ? "نشط: {$activeCount} | تم تفعيلها اليوم: {$triggeredToday}"
            : "Active: {$activeCount} | Triggered Today: {$triggeredToday}";

        $text = $locale === 'ar'
            ? "📋 *تنبيهاتك*\n\n{$statsLine}"
            : "📋 *Your Alerts*\n\n{$statsLine}";

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildMainMenu($user, $locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function showTypeSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        // Clear any previous draft
        $user->update(['telegram_alert_draft' => ['step' => 'type']]);

        $builder = new AlertKeyboardBuilder;

        $text = $locale === 'ar'
            ? "📊 *إنشاء تنبيه*\n\nما نوع التنبيه؟"
            : "📊 *Create Alert*\n\nWhat type of alert?";

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildAlertTypeSelector($locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleTypeSelection(User $user, ?string $type, int $chatId, int $messageId, string $locale): mixed
    {
        if (! $type || ! in_array($type, ['price', 'signal', 'prediction'])) {
            return $this->answerWithError('Invalid alert type');
        }

        // Update draft
        $draft = $user->telegram_alert_draft ?? [];
        $draft['step'] = 'asset';
        $draft['type'] = $type;
        $user->update(['telegram_alert_draft' => $draft]);

        return $this->showAssetSelector($chatId, $messageId, $user, $locale);
    }

    private function showAssetSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new AlertKeyboardBuilder;
        $draft = $user->telegram_alert_draft ?? [];
        $typeLabel = $this->getTypeLabel($draft['type'] ?? 'price', $locale);

        // Get user's portfolio and watchlist assets
        $portfolioAssets = $this->getPortfolioAssets($user);
        $watchlistAssets = $this->getWatchlistAssets($user);

        $text = $locale === 'ar'
            ? "📊 *{$typeLabel}*\n\nاختر الأصل:"
            : "📊 *{$typeLabel}*\n\nSelect asset:";

        // Add sections if they have items
        if ($portfolioAssets->isNotEmpty()) {
            $text .= $locale === 'ar' ? "\n\n📂 *محفظتك:*" : "\n\n📂 *Your Portfolio:*";
        }
        if ($watchlistAssets->isNotEmpty()) {
            $text .= $locale === 'ar' ? "\n\n⭐ *قائمة المتابعة:*" : "\n\n⭐ *Your Watchlist:*";
        }

        $keyboard = $builder->buildAssetSelector($portfolioAssets, $watchlistAssets, $locale);

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

    private function handleAssetSelection(User $user, ?string $assetIdOrAction, int $chatId, int $messageId, string $locale): mixed
    {
        if ($assetIdOrAction === 'search') {
            return $this->promptAssetSearch($chatId, $user, $locale);
        }

        $asset = Asset::with(['cachedPrice', 'latestPrice'])->find($assetIdOrAction);
        if (! $asset) {
            return $this->answerWithError('Asset not found');
        }

        // Update draft
        $draft = $user->telegram_alert_draft ?? [];
        $draft['step'] = 'trigger';
        $draft['asset_id'] = $asset->id;
        $draft['asset_symbol'] = $asset->symbol;
        $draft['asset_name'] = $locale === 'ar' ? ($asset->name_ar ?: $asset->name) : $asset->name;
        $draft['current_price'] = $asset->cachedPrice?->price ?? $asset->latestPrice?->price;
        $user->update(['telegram_alert_draft' => $draft]);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? "✓ تم اختيار: {$asset->symbol}" : "✓ Selected: {$asset->symbol}",
            'show_alert' => false,
        ]);

        return $this->showTriggerSelector($chatId, $messageId, $user, $locale);
    }

    private function promptAssetSearch(int $chatId, User $user, string $locale): mixed
    {
        // Set awaiting input state
        $user->update(['telegram_awaiting_input' => 'alert_asset_search']);

        $text = $locale === 'ar'
            ? '🔍 أدخل رمز أو اسم الأصل:'
            : '🔍 Enter asset symbol or name:';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'force_reply' => true,
                'selective' => true,
            ],
        ]);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? 'اكتب للبحث' : 'Type to search',
            'show_alert' => false,
        ]);

        return null;
    }

    private function showTriggerSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new AlertKeyboardBuilder;
        $draft = $user->telegram_alert_draft ?? [];

        $symbol = $draft['asset_symbol'] ?? 'N/A';
        $alertType = $draft['type'] ?? 'price';

        $text = $locale === 'ar'
            ? "📊 *تنبيه سعر لـ {$symbol}*\n\nما الذي يجب أن يفعّل هذا التنبيه؟"
            : "📊 *Price Alert for {$symbol}*\n\nWhat should trigger this alert?";

        $keyboard = $builder->buildTriggerTypeSelector($alertType, $locale);

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

    private function handleTriggerSelection(User $user, ?string $triggerType, int $chatId, int $messageId, string $locale): mixed
    {
        $validTriggers = ['target_price', 'daily_change', 'breakout', 'signal', 'prediction'];
        if (! $triggerType || ! in_array($triggerType, $validTriggers)) {
            return $this->answerWithError('Invalid trigger type');
        }

        // Update draft
        $draft = $user->telegram_alert_draft ?? [];
        $draft['step'] = 'parameter';
        $draft['trigger_type'] = $triggerType;
        $user->update(['telegram_alert_draft' => $draft]);

        // Signal and prediction alerts have simpler flows - go directly to direction
        if (in_array($triggerType, ['signal', 'prediction'])) {
            // Set default parameters for signal/prediction alerts
            $draft['parameters'] = match ($triggerType) {
                'signal' => ['min_strength' => 0.7],
                'prediction' => ['min_confidence' => 0.75, 'direction' => 'up'],
                default => [],
            };
            $user->update(['telegram_alert_draft' => $draft]);

            return $this->showSignalPredictionOptions($chatId, $messageId, $user, $locale, $triggerType);
        }

        // Prompt for parameter input
        return $this->promptParameterInput($chatId, $user, $locale);
    }

    private function showSignalPredictionOptions(int $chatId, int $messageId, User $user, string $locale, string $triggerType): mixed
    {
        $draft = $user->telegram_alert_draft ?? [];
        $symbol = $draft['asset_symbol'] ?? 'N/A';

        if ($triggerType === 'signal') {
            $text = $locale === 'ar'
                ? "📉 *تنبيه إشارة فنية لـ {$symbol}*\n\nستتلقى إشعارات عند اكتشاف إشارات فنية قوية:\n\n• إشارات RSI\n• تقاطعات MACD\n• أنماط السعر\n\nاختر اتجاه الإشارة:"
                : "📉 *Technical Signal Alert for {$symbol}*\n\nYou'll receive notifications when strong signals are detected:\n\n• RSI signals\n• MACD crossovers\n• Price patterns\n\nSelect signal direction:";
        } else {
            $text = $locale === 'ar'
                ? "🔮 *تنبيه توقع ذكي لـ {$symbol}*\n\nستتلقى إشعارات بناءً على توقعات الذكاء الاصطناعي.\n\nاختر اتجاه التوقع المطلوب:"
                : "🔮 *AI Prediction Alert for {$symbol}*\n\nYou'll receive notifications based on AI price predictions.\n\nSelect desired prediction direction:";
        }

        $keyboard = [
            [[
                'text' => $locale === 'ar' ? '⬆️ صعود' : '⬆️ Bullish',
                'callback_data' => 'alert:create:direction:above',
            ]],
            [[
                'text' => $locale === 'ar' ? '⬇️ هبوط' : '⬇️ Bearish',
                'callback_data' => 'alert:create:direction:below',
            ]],
            [[
                'text' => $locale === 'ar' ? '↕️ أي اتجاه' : '↕️ Both Directions',
                'callback_data' => 'alert:create:direction:both',
            ]],
            [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'alert:create',
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

    private function promptParameterInput(int $chatId, User $user, string $locale): mixed
    {
        $draft = $user->telegram_alert_draft ?? [];
        $triggerType = $draft['trigger_type'] ?? 'target_price';
        $symbol = $draft['asset_symbol'] ?? 'N/A';
        $currentPrice = isset($draft['current_price']) ? number_format($draft['current_price'], 2) : 'N/A';

        // Set awaiting input based on trigger type
        $inputType = match ($triggerType) {
            'target_price' => 'alert_target_price',
            'daily_change' => 'alert_percentage',
            'breakout' => 'alert_target_price',
            default => 'alert_target_price',
        };

        $user->update(['telegram_awaiting_input' => $inputType]);

        $text = match ($triggerType) {
            'target_price' => $locale === 'ar'
                ? "🎯 *تنبيه سعر مستهدف لـ {$symbol}*\n\nالسعر الحالي: {$currentPrice}\n\nأدخل السعر المستهدف:"
                : "🎯 *Target Price Alert for {$symbol}*\n\nCurrent price: {$currentPrice}\n\nEnter your target price:",
            'daily_change' => $locale === 'ar'
                ? "📊 *تنبيه تغير يومي لـ {$symbol}*\n\nأدخل نسبة التغير (مثال: 5):"
                : "📊 *Daily Change Alert for {$symbol}*\n\nEnter change percentage (e.g., 5):",
            'breakout' => $locale === 'ar'
                ? "📈 *تنبيه اختراق لـ {$symbol}*\n\nالسعر الحالي: {$currentPrice}\n\nأدخل مستوى الاختراق:"
                : "📈 *Breakout Alert for {$symbol}*\n\nCurrent price: {$currentPrice}\n\nEnter breakout level:",
            default => '',
        };

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'force_reply' => true,
                'selective' => true,
            ],
        ]);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? 'أدخل القيمة' : 'Enter value',
            'show_alert' => false,
        ]);

        return null;
    }

    private function handleDirectionSelection(User $user, ?string $direction, int $chatId, int $messageId, string $locale): mixed
    {
        if (! $direction || ! in_array($direction, ['above', 'below', 'both'])) {
            return $this->answerWithError('Invalid direction');
        }

        // Update draft
        $draft = $user->telegram_alert_draft ?? [];
        $draft['step'] = 'confirm';
        $draft['direction'] = $direction;
        $user->update(['telegram_alert_draft' => $draft]);

        return $this->showConfirmation($chatId, $messageId, $user, $locale);
    }

    private function showConfirmation(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new AlertKeyboardBuilder;
        $draft = $user->telegram_alert_draft ?? [];

        $symbol = $draft['asset_symbol'] ?? 'N/A';
        $assetName = $draft['asset_name'] ?? '';
        $triggerType = $draft['trigger_type'] ?? 'target_price';
        $direction = $draft['direction'] ?? 'above';
        $parameters = $draft['parameters'] ?? [];

        // Format parameter display
        $paramDisplay = '';
        if ($triggerType === 'target_price' && isset($parameters['target_price'])) {
            $target = number_format($parameters['target_price'], 2);
            $paramDisplay = $locale === 'ar' ? "🎯 السعر المستهدف: {$target}" : "🎯 Target Price: {$target}";
        } elseif ($triggerType === 'daily_change' && isset($parameters['threshold_percent'])) {
            $pct = $parameters['threshold_percent'];
            $paramDisplay = $locale === 'ar' ? "📊 نسبة التغير: {$pct}%" : "📊 Change: {$pct}%";
        } elseif ($triggerType === 'signal') {
            $strength = ($parameters['min_strength'] ?? 0.7) * 100;
            $paramDisplay = $locale === 'ar' ? "📉 إشارات فنية (قوة ≥ {$strength}%)" : "📉 Technical signals (strength ≥ {$strength}%)";
        } elseif ($triggerType === 'prediction') {
            $confidence = ($parameters['min_confidence'] ?? 0.75) * 100;
            $paramDisplay = $locale === 'ar' ? "🔮 توقعات AI (ثقة ≥ {$confidence}%)" : "🔮 AI predictions (confidence ≥ {$confidence}%)";
        }

        // Direction display
        $directionIcon = match ($direction) {
            'above' => '⬆️',
            'below' => '⬇️',
            'both' => '↕️',
            default => '',
        };
        $directionLabel = match ($direction) {
            'above' => $locale === 'ar' ? 'أعلى من' : 'Above',
            'below' => $locale === 'ar' ? 'أقل من' : 'Below',
            'both' => $locale === 'ar' ? 'أي اتجاه' : 'Either Direction',
            default => '',
        };

        $text = $locale === 'ar'
            ? "✅ *تأكيد التنبيه*\n\n📊 {$symbol} - {$assetName}\n{$paramDisplay}\n{$directionIcon} الاتجاه: {$directionLabel}\n🔔 القنوات: Telegram, In-App"
            : "✅ *Confirm Alert*\n\n📊 {$symbol} - {$assetName}\n{$paramDisplay}\n{$directionIcon} Direction: {$directionLabel}\n🔔 Channels: Telegram, In-App";

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildConfirmation($locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleConfirm(User $user, int $chatId, int $messageId, string $locale): mixed
    {
        $draft = $user->telegram_alert_draft ?? [];

        if (empty($draft['asset_id']) || empty($draft['trigger_type']) || empty($draft['direction'])) {
            return $this->answerWithError($locale === 'ar' ? 'بيانات غير مكتملة' : 'Incomplete data');
        }

        // Create the alert
        try {
            $alert = Alert::create([
                'user_id' => $user->id,
                'asset_id' => $draft['asset_id'],
                'type' => $draft['type'] ?? 'price',
                'trigger_type' => $draft['trigger_type'],
                'scope' => 'single_asset',
                'direction' => $draft['direction'],
                'condition_logic' => 'single',
                'parameters' => $draft['parameters'] ?? [],
                'status' => 'active',
                'priority' => $this->inferPriority($draft),
                'is_recurring' => false,
                'cooldown_minutes' => 240,
                'delivery_config' => ['channels' => ['telegram', 'in_app']],
            ]);

            Log::info('Alert created via Telegram', [
                'user_id' => $user->id,
                'alert_id' => $alert->id,
            ]);

            // Clear draft
            $user->update(['telegram_alert_draft' => null]);

            // Show success message
            $builder = new AlertKeyboardBuilder;
            $symbol = $draft['asset_symbol'] ?? 'N/A';
            $triggerType = $draft['trigger_type'] ?? 'target_price';
            $targetPrice = $draft['parameters']['target_price'] ?? null;

            $successText = match ($triggerType) {
                'signal' => $locale === 'ar'
                    ? "✅ *تم إنشاء التنبيه!*\n\nسيتم إعلامك عند اكتشاف إشارات فنية لـ {$symbol}."
                    : "✅ *Alert created!*\n\nYou'll be notified when technical signals are detected for {$symbol}.",
                'prediction' => $locale === 'ar'
                    ? "✅ *تم إنشاء التنبيه!*\n\nسيتم إعلامك عند صدور توقعات AI لـ {$symbol}."
                    : "✅ *Alert created!*\n\nYou'll be notified when AI predictions are made for {$symbol}.",
                default => $locale === 'ar'
                    ? "✅ *تم إنشاء التنبيه!*\n\nسيتم إعلامك عندما يصل {$symbol} إلى السعر المستهدف."
                    : "✅ *Alert created!*\n\nYou'll be notified when {$symbol} reaches your target.",
            };

            if ($targetPrice) {
                $formattedPrice = number_format($targetPrice, 2);
                $successText = $locale === 'ar'
                    ? "✅ *تم إنشاء التنبيه!*\n\nسيتم إعلامك عندما يصل {$symbol} إلى {$formattedPrice}."
                    : "✅ *Alert created!*\n\nYou'll be notified when {$symbol} reaches {$formattedPrice}.";
            }

            $this->editMessageText([
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $successText,
                'parse_mode' => 'Markdown',
                'reply_markup' => [
                    'inline_keyboard' => $builder->buildCreationSuccess($locale),
                ],
            ]);

            $this->answerCallbackQuery([
                'text' => $locale === 'ar' ? '✅ تم الإنشاء' : '✅ Created',
                'show_alert' => false,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create alert via Telegram', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->answerWithError($locale === 'ar' ? 'فشل في إنشاء التنبيه' : 'Failed to create alert');
        }

        return null;
    }

    private function inferPriority(array $draft): string
    {
        // If target price is within 2% of current price, high priority
        if (isset($draft['parameters']['target_price']) && isset($draft['current_price'])) {
            $target = $draft['parameters']['target_price'];
            $current = $draft['current_price'];
            if ($current > 0) {
                $diff = abs($target - $current) / $current;
                if ($diff <= 0.02) {
                    return 'high';
                }
            }
        }

        return 'medium';
    }

    private function getTypeLabel(string $type, string $locale): string
    {
        $types = [
            'price' => ['en' => 'Price Alert', 'ar' => 'تنبيه السعر'],
            'signal' => ['en' => 'Signal Alert', 'ar' => 'تنبيه إشارة'],
            'prediction' => ['en' => 'Prediction Alert', 'ar' => 'تنبيه توقع'],
        ];

        return $types[$type][$locale] ?? $type;
    }

    private function getPortfolioAssets(User $user): \Illuminate\Support\Collection
    {
        // Get assets from user's portfolio assets
        try {
            return $user->portfolios()
                ->with('portfolioAssets.asset')
                ->get()
                ->flatMap(fn ($portfolio) => $portfolio->portfolioAssets->pluck('asset'))
                ->filter()
                ->unique('id')
                ->values();
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function getWatchlistAssets(User $user): \Illuminate\Support\Collection
    {
        try {
            return $user->wishlistAssets()
                ->select('assets.id', 'symbol', 'name_en', 'name_ar')
                ->withPivot('created_at')
                ->with('cachedPrice:pid,price')
                ->get()
                ->map(fn ($asset) => (object) [
                    'id' => $asset->id,
                    'symbol' => $asset->symbol,
                    'name' => $asset->name_en,
                    'name_ar' => $asset->name_ar,
                    'last_price' => $asset->cachedPrice?->price,
                ]);
        } catch (\Exception $e) {
            return collect();
        }
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
