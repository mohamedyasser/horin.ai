<?php

namespace App\Telegram\Handlers;

use App\Models\Alert;
use App\Models\User;
use App\Telegram\Keyboards\AlertsKeyboard;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

class AlertManageCallbackHandler extends CallbackHandler
{
    protected string $match = '/^alert_manage:(.+)$/';

    public function handle(): mixed
    {
        $callbackQuery = $this->update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;
        $chatId = $callbackQuery->message->chat->id;

        // Parse callback data: alert_manage:{alertId}
        $parts = explode(':', $data);
        if (count($parts) !== 2) {
            return $this->answerWithError('Invalid callback data');
        }

        $alertId = $parts[1];

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError('User not found');
        }

        $locale = $user->language ?? 'en';

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->with('asset')
            ->first();

        if (! $alert) {
            return $this->answerWithError($locale === 'ar' ? 'التنبيه غير موجود' : 'Alert not found');
        }

        // Store the viewing alert ID in user's draft
        $draft = $user->telegram_alert_draft ?? [];
        $draft['viewing_alert_id'] = $alertId;
        $user->update(['telegram_alert_draft' => $draft]);

        Log::info('Alert selected for management via Telegram', [
            'alert_id' => $alertId,
            'user_id' => $user->id,
        ]);

        // Build alert detail message
        $symbol = str_replace('_', '\\_', $alert->asset?->symbol ?? 'N/A');
        $status = $alert->status;
        $isSnoozed = $alert->isSnoozed();

        $statusLabels = [
            'active' => ['en' => 'Active', 'ar' => 'نشط', 'icon' => '✅'],
            'paused' => ['en' => 'Paused', 'ar' => 'متوقف', 'icon' => '⏸️'],
            'triggered' => ['en' => 'Triggered', 'ar' => 'تم تفعيله', 'icon' => '🔔'],
        ];

        $statusLabel = $statusLabels[$status][$locale] ?? $status;
        $statusIcon = $statusLabels[$status]['icon'] ?? '📊';

        $triggerLabels = [
            'target_price' => ['en' => 'Target Price', 'ar' => 'سعر مستهدف'],
            'daily_change' => ['en' => 'Daily Change', 'ar' => 'تغير يومي'],
            'breakout' => ['en' => 'Price Breakout', 'ar' => 'اختراق سعر'],
        ];

        $triggerLabel = $triggerLabels[$alert->trigger_type][$locale] ?? str_replace('_', ' ', $alert->trigger_type);

        $directionLabels = [
            'above' => ['en' => 'Above', 'ar' => 'أعلى من', 'icon' => '⬆️'],
            'below' => ['en' => 'Below', 'ar' => 'أقل من', 'icon' => '⬇️'],
            'both' => ['en' => 'Either', 'ar' => 'أي اتجاه', 'icon' => '↕️'],
        ];

        $direction = $alert->direction ?? 'both';
        $dirLabel = $directionLabels[$direction][$locale] ?? $direction;
        $dirIcon = $directionLabels[$direction]['icon'] ?? '';

        $params = $alert->parameters ?? [];
        $value = $params['target_price'] ?? $params['threshold_percent'] ?? null;
        $valueStr = $value !== null ? number_format((float) $value, 2) : 'N/A';
        if (isset($params['threshold_percent'])) {
            $valueStr .= '%';
        }

        $snoozedText = '';
        if ($isSnoozed) {
            $snoozedUntil = $alert->snoozed_until->format('M d, H:i');
            $snoozedText = $locale === 'ar'
                ? "\n😴 مؤجل حتى: {$snoozedUntil}"
                : "\n😴 Snoozed until: {$snoozedUntil}";
        }

        $text = $locale === 'ar'
            ? "📊 *تفاصيل التنبيه*\n\n📈 الأصل: {$symbol}\n📋 النوع: {$triggerLabel}\n🎯 القيمة: {$valueStr}\n{$dirIcon} الاتجاه: {$dirLabel}\n{$statusIcon} الحالة: {$statusLabel}{$snoozedText}\n\nاختر إجراء:"
            : "📊 *Alert Details*\n\n📈 Asset: {$symbol}\n📋 Type: {$triggerLabel}\n🎯 Value: {$valueStr}\n{$dirIcon} Direction: {$dirLabel}\n{$statusIcon} Status: {$statusLabel}{$snoozedText}\n\nChoose an action:";

        // Answer callback first
        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? 'تم تحديد التنبيه' : 'Alert selected',
            'show_alert' => false,
        ]);

        // Send message with action keyboard
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => AlertsKeyboard::alertActionsKeyboard($status, $isSnoozed, $locale),
        ]);

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
