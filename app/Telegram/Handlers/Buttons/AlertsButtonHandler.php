<?php

namespace App\Telegram\Handlers\Buttons;

use App\Models\Alert;
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

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            return null;
        }

        $user->update(['telegram_alert_draft' => ['step' => 'type']]);

        $text = $locale === 'ar'
            ? "📊 *إنشاء تنبيه*\n\nما نوع التنبيه؟"
            : "📊 *Create Alert*\n\nWhat type of alert?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertTypeKeyboard($locale),
        ]);

        return null;
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
        } else {
            $alertLines = $alerts->map(function ($alert) {
                $symbol = $alert->asset?->symbol ?? 'N/A';
                $condition = $alert->condition ?? 'N/A';
                $value = $alert->target_value ?? 'N/A';

                return "• {$symbol}: {$condition} {$value}";
            })->join("\n");

            $text = $locale === 'ar'
                ? "📋 *تنبيهاتك النشطة*\n\n{$alertLines}"
                : "📋 *Your Active Alerts*\n\n{$alertLines}";
        }

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);

        return null;
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
                $symbol = $alert->asset?->symbol ?? 'N/A';
                $date = $alert->last_triggered_at?->format('M d, H:i') ?? 'N/A';

                return "• {$symbol} - {$date}";
            })->join("\n");

            $text = $locale === 'ar'
                ? "📜 *سجل التنبيهات*\n\n{$alertLines}"
                : "📜 *Alert History*\n\n{$alertLines}";
        }

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);

        return null;
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

    private function selectAlertType(int $chatId, ?User $user, string $locale, string $type): mixed
    {
        if (! $user) {
            $text = $locale === 'ar'
                ? '❌ يرجى التسجيل أولاً باستخدام /start'
                : '❌ Please register first using /start';

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);

            return null;
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

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::cancelKeyboard($locale),
        ]);

        return null;
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
        $symbol = $draft['asset_symbol'] ?? 'N/A';

        if ($triggerType === 'target_price' || $triggerType === 'breakout') {
            $user->update(['telegram_awaiting_input' => 'alert_target_price']);

            $text = $locale === 'ar'
                ? "🎯 *{$label}*\n\n📊 الأصل: {$symbol}\n\nأدخل السعر المستهدف:"
                : "🎯 *{$label}*\n\n📊 Asset: {$symbol}\n\nEnter the target price:";
        } else {
            $user->update(['telegram_awaiting_input' => 'alert_percentage']);

            $text = $locale === 'ar'
                ? "📊 *{$label}*\n\n📊 الأصل: {$symbol}\n\nأدخل نسبة التغير (مثال: 5):"
                : "📊 *{$label}*\n\n📊 Asset: {$symbol}\n\nEnter the change percentage (e.g., 5):";
        }

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::cancelKeyboard($locale),
        ]);

        return null;
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

        $symbol = $draft['asset_symbol'] ?? 'N/A';
        $triggerType = $draft['trigger_type'] ?? 'target_price';
        $value = $draft['parameters']['target_price'] ?? $draft['parameters']['threshold_percent'] ?? 'N/A';

        $valueStr = is_numeric($value) ? number_format($value, 2) : $value;
        if ($triggerType === 'daily_change') {
            $valueStr .= '%';
        }

        $text = $locale === 'ar'
            ? "✅ *تأكيد التنبيه*\n\n📊 الأصل: {$symbol}\n📈 النوع: {$triggerType}\n🎯 القيمة: {$valueStr}\n{$dirIcon} الاتجاه: {$dirLabel}\n\nهل تريد إنشاء هذا التنبيه؟"
            : "✅ *Confirm Alert*\n\n📊 Asset: {$symbol}\n📈 Type: {$triggerType}\n🎯 Value: {$valueStr}\n{$dirIcon} Direction: {$dirLabel}\n\nCreate this alert?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertConfirmKeyboard($locale),
        ]);

        return null;
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

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);

            return null;
        }

        $alertData = [
            'user_id' => $user->id,
            'asset_id' => $draft['asset_id'],
            'type' => $draft['type'] ?? 'price',
            'trigger_type' => $draft['trigger_type'],
            'condition' => $draft['direction'] ?? 'above',
            'is_active' => true,
        ];

        if (isset($draft['parameters']['target_price'])) {
            $alertData['target_value'] = $draft['parameters']['target_price'];
        }
        if (isset($draft['parameters']['threshold_percent'])) {
            $alertData['threshold_percent'] = $draft['parameters']['threshold_percent'];
        }

        $alert = Alert::create($alertData);

        Log::info('Alert created via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'type' => $alert->type,
        ]);

        $user->update(['telegram_alert_draft' => null]);

        $symbol = $draft['asset_symbol'] ?? 'N/A';

        $text = $locale === 'ar'
            ? "✅ *تم إنشاء التنبيه*\n\n📊 الأصل: {$symbol}\n\nسيتم إشعارك عند تحقق الشرط."
            : "✅ *Alert Created*\n\n📊 Asset: {$symbol}\n\nYou'll be notified when the condition is met.";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertSuccessKeyboard($locale),
        ]);

        return null;
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

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::snoozeOptionsKeyboard($locale),
        ]);

        return null;
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

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);

            return null;
        }

        $alert->update(['snoozed_until' => null]);

        Log::info('Alert unsnoozed via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
        ]);

        $text = $locale === 'ar'
            ? '✅ تم إلغاء تأجيل التنبيه'
            : '✅ Alert unsnoozed';

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);

        return null;
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

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);

            return null;
        }

        $newStatus = ! $alert->is_active;
        $alert->update(['is_active' => $newStatus]);

        Log::info('Alert status toggled via Telegram', [
            'alert_id' => $alert->id,
            'user_id' => $user->id,
            'is_active' => $newStatus,
        ]);

        $text = $newStatus
            ? ($locale === 'ar' ? '✅ تم تفعيل التنبيه' : '✅ Alert resumed')
            : ($locale === 'ar' ? '⏸️ تم إيقاف التنبيه مؤقتاً' : '⏸️ Alert paused');

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);

        return null;
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

        $symbol = $alert->asset?->symbol ?? 'N/A';

        $text = $locale === 'ar'
            ? "🗑️ *حذف التنبيه*\n\n📊 الأصل: {$symbol}\n\n⚠️ هل أنت متأكد من حذف هذا التنبيه؟"
            : "🗑️ *Delete Alert*\n\n📊 Asset: {$symbol}\n\n⚠️ Are you sure you want to delete this alert?";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::deleteConfirmKeyboard($locale),
        ]);

        return null;
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

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);

            return null;
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

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);

        return null;
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

            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => AlertsKeyboard::menu($locale),
            ]);

            return null;
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

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => AlertsKeyboard::menu($locale),
        ]);

        return null;
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

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::cancelKeyboard($locale),
        ]);

        return null;
    }

    private function goBackToMainMenu(int $chatId, ?User $user, string $locale): mixed
    {
        $text = $locale === 'ar'
            ? "🏠 *القائمة الرئيسية*\n\nاختر من القائمة أدناه:"
            : "🏠 *Main Menu*\n\nChoose from the menu below:";

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => MainMenuKeyboard::forUser($user, $locale),
        ]);

        return null;
    }
}
