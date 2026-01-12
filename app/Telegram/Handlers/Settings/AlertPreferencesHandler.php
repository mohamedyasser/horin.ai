<?php

namespace App\Telegram\Handlers\Settings;

use App\Models\User;
use App\Telegram\Services\SettingsKeyboardBuilder;
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
            'channels' => $this->handleChannelsMenu($user, $subAction, $chatId, $messageId, $locale),
            'channel' => $this->handleChannel($user, $subAction, $chatId, $messageId, $locale),
            'limits' => $this->handleLimitsMenu($user, $subAction, $chatId, $messageId, $locale),
            'max' => $this->handleMax($user, $subAction, $value, $chatId, $messageId, $locale),
            'quiet' => $this->handleQuiet($user, $subAction, $chatId, $messageId, $locale),
            default => $this->showAlertsSettings($chatId, $messageId, $user, $locale),
        };
    }

    private function showAlertsSettings(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'ما الذي تريد تغييره؟'
            : 'What would you like to change?';

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildAlertPreferencesMenu($user, $locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleChannelsMenu(User $user, ?string $action, int $chatId, int $messageId, string $locale): mixed
    {
        if ($action === 'select') {
            return $this->showChannelsSelector($chatId, $messageId, $user, $locale);
        }

        return $this->showAlertsSettings($chatId, $messageId, $user, $locale);
    }

    private function showChannelsSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'اختر قنوات الإشعارات:'
            : 'Select notification channels:';

        $prefs = $user->getAlertPreferences();
        $channels = $prefs->channels ?? ['telegram', 'in_app'];

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildChannelsSelector($channels, $locale),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleLimitsMenu(User $user, ?string $action, int $chatId, int $messageId, string $locale): mixed
    {
        if ($action === 'select') {
            return $this->showLimitsSelector($chatId, $messageId, $user, $locale);
        }

        return $this->showAlertsSettings($chatId, $messageId, $user, $locale);
    }

    private function showLimitsSelector(int $chatId, int $messageId, User $user, string $locale): mixed
    {
        $builder = new SettingsKeyboardBuilder;

        $text = $locale === 'ar'
            ? 'حدد الحد الأقصى للتنبيهات:'
            : 'Set alert limits:';

        $prefs = $user->getAlertPreferences();
        $maxHour = $prefs->max_alerts_per_hour ?? 10;
        $maxDay = $prefs->max_alerts_per_day ?? 25;

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildLimitsSelector($maxHour, $maxDay, $locale),
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

        $builder = new SettingsKeyboardBuilder;
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

        $channelLabel = $builder->getChannelLabel($channel, $locale);

        $this->answerCallbackQuery([
            'text' => $action === 'enabled'
                ? ($locale === 'ar' ? "✅ تم تفعيل: {$channelLabel}" : "✅ Enabled: {$channelLabel}")
                : ($locale === 'ar' ? "❌ تم إيقاف: {$channelLabel}" : "❌ Disabled: {$channelLabel}"),
            'show_alert' => false,
        ]);

        // Stay on channels selector screen
        return $this->showChannelsSelector($chatId, $messageId, $user->fresh(), $locale);
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

        // Stay on limits selector screen
        return $this->showLimitsSelector($chatId, $messageId, $user->fresh(), $locale);
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

    private function answerWithError(string $message): null
    {
        $this->answerCallbackQuery([
            'text' => $message,
            'show_alert' => true,
        ]);

        return null;
    }
}
