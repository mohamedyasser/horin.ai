<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Alert Preferences settings.
 *
 * Callback patterns:
 * - set:alerts - Show alert settings
 * - set:alerts:channel:{ch} - Toggle notification channel
 * - set:alerts:max:hour:{n} - Set max alerts per hour
 * - set:alerts:max:day:{n} - Set max alerts per day
 * - set:alerts:quiet:toggle - Toggle quiet hours
 */
class AlertPreferencesHandler extends CallbackHandler
{
    protected string $match = '/^set:alerts/';

    private const CHANNELS = ['telegram', 'email', 'push', 'in_app'];

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
        $action = $parts[2] ?? null;
        $subAction = $parts[3] ?? null;
        $value = $parts[4] ?? null;
        $locale = $user->language ?? 'en';

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        // Show main alerts settings view
        if ($action === null) {
            return $this->showAlertsSettings($chatId, $messageId, $user, $locale);
        }

        return match ($action) {
            'channel' => $this->handleChannel($user, $subAction, $chatId, $messageId, $locale),
            'max' => $this->handleMax($user, $subAction, $value, $chatId, $messageId, $locale),
            'quiet' => $this->handleQuiet($user, $subAction, $chatId, $messageId, $locale),
            default => $this->showAlertsSettings($chatId, $messageId, $user, $locale),
        };
    }

    private function showAlertsSettings(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $prefs = $user->getAlertPreferences();

        $channels = $prefs->channels ?? ['telegram', 'in_app'];
        $quietStart = $prefs->quiet_hours_start ?? '23:00';
        $quietEnd = $prefs->quiet_hours_end ?? '07:00';
        $maxHour = $prefs->max_alerts_per_hour ?? 10;
        $maxDay = $prefs->max_alerts_per_day ?? 25;

        // Check which channels are enabled
        $telegramEnabled = in_array('telegram', $channels, true);
        $emailEnabled = in_array('email', $channels, true);
        $pushEnabled = in_array('push', $channels, true);
        $inAppEnabled = in_array('in_app', $channels, true);

        if ($locale === 'ar') {
            $text = <<<MSG
🔔 *تفضيلات التنبيهات*
━━━━━━━━━━━━━━━━━━

*قنوات الإشعارات:*
📱 تيليجرام: {$this->getStatus($telegramEnabled, $locale)}
📧 البريد: {$this->getStatus($emailEnabled, $locale)}
🔔 الإشعارات: {$this->getStatus($pushEnabled, $locale)}
📲 التطبيق: {$this->getStatus($inAppEnabled, $locale)}

*الحدود:*
⏰ الحد الأقصى/ساعة: {$maxHour}
📅 الحد الأقصى/يوم: {$maxDay}

*ساعات الهدوء:*
🌙 {$quietStart} - {$quietEnd}
MSG;
        } else {
            $text = <<<MSG
🔔 *Alert Preferences*
━━━━━━━━━━━━━━━━━━

*Notification Channels:*
📱 Telegram: {$this->getStatus($telegramEnabled, $locale)}
📧 Email: {$this->getStatus($emailEnabled, $locale)}
🔔 Push: {$this->getStatus($pushEnabled, $locale)}
📲 In-App: {$this->getStatus($inAppEnabled, $locale)}

*Limits:*
⏰ Max/Hour: {$maxHour}
📅 Max/Day: {$maxDay}

*Quiet Hours:*
🌙 {$quietStart} - {$quietEnd}
MSG;
        }

        $keyboard = [
            // Channel toggles row 1
            [
                [
                    'text' => ($telegramEnabled ? '✅' : '⬜').' '.($locale === 'ar' ? 'تيليجرام' : 'Telegram'),
                    'callback_data' => 'set:alerts:channel:telegram',
                ],
                [
                    'text' => ($emailEnabled ? '✅' : '⬜').' '.($locale === 'ar' ? 'البريد' : 'Email'),
                    'callback_data' => 'set:alerts:channel:email',
                ],
            ],
            // Channel toggles row 2
            [
                [
                    'text' => ($pushEnabled ? '✅' : '⬜').' '.($locale === 'ar' ? 'الإشعارات' : 'Push'),
                    'callback_data' => 'set:alerts:channel:push',
                ],
                [
                    'text' => ($inAppEnabled ? '✅' : '⬜').' '.($locale === 'ar' ? 'التطبيق' : 'In-App'),
                    'callback_data' => 'set:alerts:channel:in_app',
                ],
            ],
            // Max per hour controls
            [
                [
                    'text' => '➖',
                    'callback_data' => 'set:alerts:max:hour:'.max(1, $maxHour - 5),
                ],
                [
                    'text' => $locale === 'ar' ? "⏰ {$maxHour}/ساعة" : "⏰ {$maxHour}/hr",
                    'callback_data' => 'noop',
                ],
                [
                    'text' => '➕',
                    'callback_data' => 'set:alerts:max:hour:'.min(50, $maxHour + 5),
                ],
            ],
            // Max per day controls
            [
                [
                    'text' => '➖',
                    'callback_data' => 'set:alerts:max:day:'.max(5, $maxDay - 10),
                ],
                [
                    'text' => $locale === 'ar' ? "📅 {$maxDay}/يوم" : "📅 {$maxDay}/day",
                    'callback_data' => 'noop',
                ],
                [
                    'text' => '➕',
                    'callback_data' => 'set:alerts:max:day:'.min(100, $maxDay + 10),
                ],
            ],
            // Open app for more settings
            [
                [
                    'text' => $locale === 'ar' ? '⚙️ إعدادات متقدمة' : '⚙️ Advanced Settings',
                    'web_app' => ['url' => config('app.url').'/settings/notifications'],
                ],
            ],
            // Back button
            [
                [
                    'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                    'callback_data' => 'set:menu',
                ],
            ],
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

    private function handleChannel(User $user, ?string $channel, int $chatId, int $messageId, string $locale): mixed
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            return $this->answerWithError('Invalid channel');
        }

        $prefs = $user->getAlertPreferences();
        $channels = $prefs->channels ?? ['telegram', 'in_app'];

        // Toggle channel
        if (in_array($channel, $channels, true)) {
            // Don't allow disabling all channels
            if (count($channels) <= 1) {
                return $this->answerWithError(
                    $locale === 'ar'
                        ? 'يجب أن يكون لديك قناة واحدة على الأقل'
                        : 'You must have at least one channel enabled'
                );
            }
            $channels = array_values(array_diff($channels, [$channel]));
            $action = 'disabled';
        } else {
            $channels[] = $channel;
            $action = 'enabled';
        }

        $prefs->update(['channels' => $channels]);

        Log::info("Settings: Alert channel {$action} via Telegram", [
            'user_id' => $user->id,
            'channel' => $channel,
        ]);

        $channelNames = [
            'telegram' => $locale === 'ar' ? 'تيليجرام' : 'Telegram',
            'email' => $locale === 'ar' ? 'البريد' : 'Email',
            'push' => $locale === 'ar' ? 'الإشعارات' : 'Push',
            'in_app' => $locale === 'ar' ? 'التطبيق' : 'In-App',
        ];

        $this->answerCallbackQuery([
            'text' => $action === 'enabled'
                ? ($locale === 'ar' ? "✅ تم تفعيل: {$channelNames[$channel]}" : "✅ Enabled: {$channelNames[$channel]}")
                : ($locale === 'ar' ? "❌ تم إيقاف: {$channelNames[$channel]}" : "❌ Disabled: {$channelNames[$channel]}"),
            'show_alert' => false,
        ]);

        return $this->showAlertsSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function handleMax(User $user, ?string $type, ?string $value, int $chatId, int $messageId, string $locale): mixed
    {
        $prefs = $user->getAlertPreferences();
        $newValue = (int) $value;

        if ($type === 'hour') {
            $newValue = max(1, min(50, $newValue));
            $prefs->update(['max_alerts_per_hour' => $newValue]);

            Log::info('Settings: Max alerts per hour updated via Telegram', [
                'user_id' => $user->id,
                'max_alerts_per_hour' => $newValue,
            ]);
        } elseif ($type === 'day') {
            $newValue = max(5, min(100, $newValue));
            $prefs->update(['max_alerts_per_day' => $newValue]);

            Log::info('Settings: Max alerts per day updated via Telegram', [
                'user_id' => $user->id,
                'max_alerts_per_day' => $newValue,
            ]);
        } else {
            return $this->answerWithError('Invalid max type');
        }

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? '✓ تم التحديث' : '✓ Updated',
            'show_alert' => false,
        ]);

        return $this->showAlertsSettings($chatId, $messageId, $user->fresh(), $locale);
    }

    private function handleQuiet(User $user, ?string $action, int $chatId, int $messageId, string $locale): mixed
    {
        // Quiet hours management requires app for now
        $this->answerCallbackQuery([
            'text' => $locale === 'ar'
                ? 'استخدم التطبيق لتعديل ساعات الهدوء'
                : 'Use the app to modify quiet hours',
            'show_alert' => true,
        ]);

        return null;
    }

    private function getStatus(bool $enabled, string $locale): string
    {
        if ($locale === 'ar') {
            return $enabled ? '✅ مفعل' : '❌ معطل';
        }

        return $enabled ? '✅ On' : '❌ Off';
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
