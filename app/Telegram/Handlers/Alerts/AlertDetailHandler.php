<?php

namespace App\Telegram\Handlers\Alerts;

use App\Models\Alert;
use App\Models\User;
use App\Telegram\Services\AlertKeyboardBuilder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for viewing and managing individual alerts.
 *
 * Callback patterns:
 * - alert:view:{id} - View alert details
 * - alert:snooze:{id} - Show snooze options
 * - alert:snooze:{id}:{preset} - Apply snooze preset
 * - alert:unsnooze:{id} - Remove snooze
 * - alert:toggle:{id} - Toggle active/paused
 * - alert:delete:{id} - Show delete confirmation
 * - alert:delete:{id}:confirm - Execute delete
 */
class AlertDetailHandler extends CallbackHandler
{
    protected string $match = '/^alert:(view|snooze|unsnooze|toggle|delete)/';

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
        $alertId = $parts[2] ?? null;
        $extra = $parts[3] ?? null;
        $locale = $user->language ?? 'en';

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        if (! $alertId) {
            return $this->answerWithError('Invalid alert');
        }

        $alert = Alert::where('id', $alertId)
            ->where('user_id', $user->id)
            ->with('asset')
            ->first();

        if (! $alert) {
            return $this->answerWithError($locale === 'ar' ? 'التنبيه غير موجود' : 'Alert not found');
        }

        return match ($action) {
            'view' => $this->showAlertDetails($chatId, $messageId, $alert, $locale),
            'snooze' => $extra
                ? $this->applySnooze($alert, $extra, $chatId, $messageId, $locale)
                : $this->showSnoozeOptions($chatId, $messageId, $alert, $locale),
            'unsnooze' => $this->removeSnooze($alert, $chatId, $messageId, $locale),
            'toggle' => $this->toggleStatus($alert, $chatId, $messageId, $locale),
            'delete' => $extra === 'confirm'
                ? $this->executeDelete($alert, $chatId, $messageId, $locale)
                : $this->showDeleteConfirmation($chatId, $messageId, $alert, $locale),
            default => $this->showAlertDetails($chatId, $messageId, $alert, $locale),
        };
    }

    private function showAlertDetails(int $chatId, int $messageId, Alert $alert, string $locale): mixed
    {
        $builder = new AlertKeyboardBuilder;

        $text = $builder->formatAlertDetails($alert, $locale);
        $keyboard = $builder->buildAlertActions($alert, $locale);

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

    private function showSnoozeOptions(int $chatId, int $messageId, Alert $alert, string $locale): mixed
    {
        $builder = new AlertKeyboardBuilder;

        $symbol = $alert->asset?->symbol ?? 'N/A';
        $text = $locale === 'ar'
            ? "😴 *تأجيل التنبيه*\n\n{$symbol}\n\nلكم من الوقت؟"
            : "😴 *Snooze Alert*\n\n{$symbol}\n\nFor how long?";

        $keyboard = $builder->buildSnoozeOptions($alert->id, $locale);

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

    private function applySnooze(Alert $alert, string $preset, int $chatId, int $messageId, string $locale): mixed
    {
        $snoozedUntil = match ($preset) {
            '1h' => Carbon::now()->addHour(),
            '4h' => Carbon::now()->addHours(4),
            '1d' => Carbon::now()->addDay(),
            'until_market_close' => $this->getNextMarketClose(),
            'until_market_open' => $this->getNextMarketOpen(),
            default => Carbon::now()->addHour(),
        };

        $alert->update(['snoozed_until' => $snoozedUntil]);

        Log::info('Alert snoozed via Telegram', [
            'alert_id' => $alert->id,
            'snoozed_until' => $snoozedUntil,
            'preset' => $preset,
        ]);

        $timeStr = $snoozedUntil->format('H:i');
        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? "✅ تم التأجيل حتى {$timeStr}"
                : "✅ Snoozed until {$timeStr}",
            'show_alert' => false,
        ]);

        // Refresh and show updated details
        return $this->showAlertDetails($chatId, $messageId, $alert->fresh(), $locale);
    }

    private function removeSnooze(Alert $alert, int $chatId, int $messageId, string $locale): mixed
    {
        $alert->update(['snoozed_until' => null]);

        Log::info('Alert unsnnoozed via Telegram', [
            'alert_id' => $alert->id,
        ]);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? '✅ تم إلغاء التأجيل' : '✅ Unsnnoozed',
            'show_alert' => false,
        ]);

        return $this->showAlertDetails($chatId, $messageId, $alert->fresh(), $locale);
    }

    private function toggleStatus(Alert $alert, int $chatId, int $messageId, string $locale): mixed
    {
        $newStatus = $alert->status === 'active' ? 'paused' : 'active';
        $alert->update(['status' => $newStatus]);

        Log::info('Alert status toggled via Telegram', [
            'alert_id' => $alert->id,
            'new_status' => $newStatus,
        ]);

        $statusLabel = match ($newStatus) {
            'active' => $locale === 'ar' ? 'تم التفعيل' : 'Activated',
            'paused' => $locale === 'ar' ? 'تم الإيقاف' : 'Paused',
            default => $newStatus,
        };

        $this->answerCallbackQuery([
            'text' => "✅ {$statusLabel}",
            'show_alert' => false,
        ]);

        return $this->showAlertDetails($chatId, $messageId, $alert->fresh(), $locale);
    }

    private function showDeleteConfirmation(int $chatId, int $messageId, Alert $alert, string $locale): mixed
    {
        $builder = new AlertKeyboardBuilder;

        $line = $builder->formatAlertLine($alert, $locale);

        $text = $locale === 'ar'
            ? "🗑️ *حذف التنبيه؟*\n\nهل أنت متأكد أنك تريد حذف هذا التنبيه؟\n\n{$line}"
            : "🗑️ *Delete Alert?*\n\nAre you sure you want to delete this alert?\n\n{$line}";

        $keyboard = $builder->buildDeleteConfirmation($alert->id, $locale);

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

    private function executeDelete(Alert $alert, int $chatId, int $messageId, string $locale): mixed
    {
        $builder = new AlertKeyboardBuilder;
        $symbol = $alert->asset?->symbol ?? 'N/A';

        $alert->delete();

        Log::info('Alert deleted via Telegram', [
            'alert_id' => $alert->id,
        ]);

        $text = $locale === 'ar'
            ? "🗑️ *تم الحذف*\n\nتم حذف تنبيه {$symbol} بنجاح."
            : "🗑️ *Deleted*\n\nAlert for {$symbol} has been deleted.";

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildDeleteSuccess($locale),
            ],
        ]);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? '✅ تم الحذف' : '✅ Deleted',
            'show_alert' => false,
        ]);

        return null;
    }

    /**
     * Get the next market close time (Egypt Stock Exchange).
     * Trading hours: 10:00 AM - 2:30 PM, Sunday-Thursday.
     */
    private function getNextMarketClose(): Carbon
    {
        $cairo = new \DateTimeZone('Africa/Cairo');
        $now = Carbon::now($cairo);

        // Market close time
        $closeTime = Carbon::today($cairo)->setTime(14, 30);

        // If today is a trading day and before close
        if (! in_array($now->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY]) && $now->lt($closeTime)) {
            return $closeTime;
        }

        // Find next trading day
        $nextDay = $now->copy()->addDay();
        while (in_array($nextDay->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY])) {
            $nextDay->addDay();
        }

        return $nextDay->setTime(14, 30);
    }

    /**
     * Get the next market open time (Egypt Stock Exchange).
     */
    private function getNextMarketOpen(): Carbon
    {
        $cairo = new \DateTimeZone('Africa/Cairo');
        $now = Carbon::now($cairo);

        // Market open time
        $openTime = Carbon::today($cairo)->setTime(10, 0);

        // If today is a trading day and before open
        if (! in_array($now->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY]) && $now->lt($openTime)) {
            return $openTime;
        }

        // Find next trading day
        $nextDay = $now->copy()->addDay();
        while (in_array($nextDay->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY])) {
            $nextDay->addDay();
        }

        return $nextDay->setTime(10, 0);
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
