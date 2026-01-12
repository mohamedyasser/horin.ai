<?php

namespace App\Telegram\Handlers\Alerts;

use App\Models\AlertHistory;
use App\Models\User;
use App\Telegram\Services\AlertKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for alert history and acknowledgement.
 *
 * Callback patterns:
 * - alert:history - Show recent alert history
 * - alert:history:page:{n} - Paginate history
 * - alert:history:ack:{id} - Acknowledge a triggered alert
 */
class AlertHistoryHandler extends CallbackHandler
{
    protected string $match = '/^alert:history/';

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
            null => $this->showHistory($chatId, $messageId, $user, $locale, 1),
            'page' => $this->showHistory($chatId, $messageId, $user, $locale, (int) ($value ?? 1)),
            'ack' => $this->acknowledgeHistory($value, $chatId, $messageId, $user, $locale),
            default => $this->showHistory($chatId, $messageId, $user, $locale, 1),
        };
    }

    private function showHistory(int $chatId, int $messageId, User $user, string $locale, int $page): mixed
    {
        $builder = new AlertKeyboardBuilder;

        $query = AlertHistory::where('user_id', $user->id)
            ->with(['alert.asset'])
            ->orderBy('triggered_at', 'desc');

        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * self::PER_PAGE;

        $history = $query->skip($offset)->take(self::PER_PAGE)->get();

        if ($history->isEmpty()) {
            $text = $locale === 'ar'
                ? "📜 *سجل التنبيهات*\n\n📭 لا يوجد سجل تنبيهات."
                : "📜 *Alert History*\n\n📭 No alert history.";

            $keyboard = [[[
                'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                'callback_data' => 'alert:menu',
            ]]];
        } else {
            $lines = [];
            $lines[] = $locale === 'ar'
                ? "📜 *سجل التنبيهات ({$total})*"
                : "📜 *Alert History ({$total})*";
            $lines[] = '';

            foreach ($history as $item) {
                $alert = $item->alert;
                $asset = $alert?->asset;
                $symbol = $asset?->symbol ?? 'N/A';
                $time = $item->triggered_at->format('d/m H:i');
                $ackIcon = $item->acknowledged_at ? '✅' : '🔔';

                // Format trigger value
                $valueStr = '';
                if ($item->trigger_value) {
                    $valueStr = is_numeric($item->trigger_value)
                        ? number_format($item->trigger_value, 2)
                        : $item->trigger_value;
                }

                $line = "{$ackIcon} {$symbol}";
                if ($valueStr) {
                    $line .= " @ {$valueStr}";
                }
                $line .= " - {$time}";

                $lines[] = $line;
            }

            $text = implode("\n", $lines);
            $keyboard = $builder->buildHistoryList($history, $page, $totalPages, $locale);
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

    private function acknowledgeHistory(?string $historyId, int $chatId, int $messageId, User $user, string $locale): mixed
    {
        if (! $historyId) {
            return $this->answerWithError('Invalid history item');
        }

        $history = AlertHistory::where('id', $historyId)
            ->where('user_id', $user->id)
            ->first();

        if (! $history) {
            return $this->answerWithError($locale === 'ar' ? 'السجل غير موجود' : 'History not found');
        }

        if ($history->acknowledged_at) {
            return $this->answerWithError($locale === 'ar' ? 'تمت القراءة مسبقاً' : 'Already acknowledged');
        }

        $history->acknowledge();

        Log::info('Alert history acknowledged via Telegram', [
            'history_id' => $history->id,
            'user_id' => $user->id,
        ]);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? '✅ تمت القراءة' : '✅ Acknowledged',
            'show_alert' => false,
        ]);

        // Refresh the history list
        return $this->showHistory($chatId, $messageId, $user, $locale, 1);
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
