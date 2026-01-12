<?php

namespace App\Telegram\Handlers\Alerts;

use App\Models\Alert;
use App\Models\User;
use App\Telegram\Services\AlertKeyboardBuilder;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for listing alerts.
 *
 * Callback patterns:
 * - alert:list - Show active alerts
 * - alert:list:page:{n} - Paginate active alerts
 * - alert:list:triggered - Show triggered today
 */
class AlertListHandler extends CallbackHandler
{
    protected string $match = '/^alert:list/';

    private const PER_PAGE = 5;

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
        $subAction = $parts[2] ?? null;
        $value = $parts[3] ?? null;
        $locale = $user->language ?? 'en';

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        return match ($subAction) {
            null => $this->showActiveAlerts($chatId, $messageId, $user, $locale, 1),
            'page' => $this->showActiveAlerts($chatId, $messageId, $user, $locale, (int) ($value ?? 1)),
            'triggered' => $this->showTriggeredToday($chatId, $messageId, $user, $locale),
            default => $this->showActiveAlerts($chatId, $messageId, $user, $locale, 1),
        };
    }

    private function showActiveAlerts(int $chatId, int $messageId, User $user, string $locale, int $page): mixed
    {
        $builder = new AlertKeyboardBuilder;

        $query = Alert::where('user_id', $user->id)
            ->active()
            ->with('asset')
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * self::PER_PAGE;

        $alerts = $query->skip($offset)->take(self::PER_PAGE)->get();

        if ($alerts->isEmpty()) {
            $text = $locale === 'ar'
                ? "📊 *التنبيهات النشطة*\n\n📭 لا توجد تنبيهات نشطة."
                : "📊 *Active Alerts*\n\n📭 No active alerts.";

            $keyboard = [[[
                'text' => $locale === 'ar' ? '➕ إنشاء تنبيه' : '➕ Create Alert',
                'callback_data' => 'alert:create',
            ]], [[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'alert:menu',
            ]]];
        } else {
            // Build alert list text
            $lines = [];
            $lines[] = $locale === 'ar'
                ? "📊 *التنبيهات النشطة ({$total})*"
                : "📊 *Active Alerts ({$total})*";
            $lines[] = '';

            $startIndex = $offset;
            foreach ($alerts as $index => $alert) {
                $num = $startIndex + $index + 1;
                $line = $builder->formatAlertLine($alert, $locale);
                $lines[] = "{$num}. {$line}";
            }

            $text = implode("\n", $lines);
            $keyboard = $builder->buildActiveAlertsList($alerts, $page, $totalPages, $locale);
        }

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

    private function showTriggeredToday(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $alerts = Alert::where('user_id', $user->id)
            ->whereDate('last_triggered_at', today())
            ->with('asset')
            ->orderBy('last_triggered_at', 'desc')
            ->get();

        $count = $alerts->count();

        if ($alerts->isEmpty()) {
            $text = $locale === 'ar'
                ? "🔔 *تم تفعيلها اليوم*\n\n📭 لم يتم تفعيل أي تنبيهات اليوم."
                : "🔔 *Triggered Today*\n\n📭 No alerts triggered today.";
        } else {
            $lines = [];
            $lines[] = $locale === 'ar'
                ? "🔔 *تم تفعيلها اليوم ({$count})*"
                : "🔔 *Triggered Today ({$count})*";
            $lines[] = '';

            $builder = new AlertKeyboardBuilder;
            foreach ($alerts as $index => $alert) {
                $num = $index + 1;
                $time = $alert->last_triggered_at->format('H:i');
                $line = $builder->formatAlertLine($alert, $locale);
                $lines[] = "{$num}. {$line} @ {$time}";
            }

            $text = implode("\n", $lines);
        }

        $keyboard = [[[
            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
            'callback_data' => 'alert:menu',
        ]]];

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

    private function answerWithError(string $message): null
    {
        $this->answerCallbackQuery([
            'text' => $message,
            'show_alert' => true,
        ]);

        return null;
    }
}
