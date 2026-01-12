<?php

namespace App\Telegram\Handlers;

use App\Models\Country;
use App\Models\User;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\UpdateHandler;

/**
 * Handler for text input responses when user is awaiting input.
 *
 * This handles:
 * - Name change (telegram_awaiting_input = 'name')
 * - Country search (telegram_awaiting_input = 'country_search')
 */
class TextInputHandler extends UpdateHandler
{
    public function trigger(): bool
    {
        // Must be a text message (not command) with no entities
        if (! isset($this->update->message?->text)) {
            return false;
        }

        $text = $this->update->message->text;

        // Ignore commands
        if (str_starts_with($text, '/')) {
            return false;
        }

        // Check if user is awaiting input
        $telegramId = (string) $this->update->message->from->id;
        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user || ! $user->telegram_awaiting_input) {
            return false;
        }

        return true;
    }

    public function handle(): mixed
    {
        $message = $this->update->message;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;
        $text = trim($message->text);

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return null;
        }

        $awaitingInput = $user->telegram_awaiting_input;
        $locale = $user->language ?? 'en';

        // Clear awaiting input state first
        $user->update(['telegram_awaiting_input' => null]);

        return match ($awaitingInput) {
            'name' => $this->handleNameInput($user, $text, $chatId, $locale),
            'country_search' => $this->handleCountrySearch($user, $text, $chatId, $locale),
            default => null,
        };
    }

    private function handleNameInput(User $user, string $name, int $chatId, string $locale): mixed
    {
        // Validate name
        $name = trim($name);

        if (mb_strlen($name) < 2) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ الاسم قصير جدا. يجب أن يكون حرفين على الأقل.'
                    : '❌ Name is too short. Must be at least 2 characters.',
            ]);

            return null;
        }

        if (mb_strlen($name) > 100) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ الاسم طويل جدا. يجب ألا يتجاوز 100 حرف.'
                    : '❌ Name is too long. Must be 100 characters or less.',
            ]);

            return null;
        }

        $user->update(['name' => $name]);

        Log::info('Name updated via Telegram', [
            'user_id' => $user->id,
            'name' => $name,
        ]);

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $locale === 'ar'
                ? "✅ تم تحديث الاسم إلى: {$name}"
                : "✅ Name updated to: {$name}",
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $locale === 'ar' ? '⬅️ رجوع للإعدادات' : '⬅️ Back to Settings',
                        'callback_data' => 'set:profile',
                    ],
                ]],
            ],
        ]);

        return null;
    }

    private function handleCountrySearch(User $user, string $query, int $chatId, string $locale): mixed
    {
        // Search for country by name
        $query = trim($query);

        if (mb_strlen($query) < 2) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? '❌ يرجى إدخال حرفين على الأقل للبحث.'
                    : '❌ Please enter at least 2 characters to search.',
            ]);

            return null;
        }

        // Search in both English and Arabic names
        $countries = Country::where('name_en', 'LIKE', "%{$query}%")
            ->orWhere('name_ar', 'LIKE', "%{$query}%")
            ->limit(5)
            ->get();

        if ($countries->isEmpty()) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => $locale === 'ar'
                    ? "❌ لم يتم العثور على دول بهذا الاسم: {$query}"
                    : "❌ No countries found matching: {$query}",
                'reply_markup' => [
                    'inline_keyboard' => [[
                        [
                            'text' => $locale === 'ar' ? '🔍 بحث مرة أخرى' : '🔍 Search Again',
                            'callback_data' => 'ob:country:search',
                        ],
                        [
                            'text' => $locale === 'ar' ? '⬅️ رجوع' : '⬅️ Back',
                            'callback_data' => 'ob:step3:back',
                        ],
                    ]],
                ],
            ]);

            return null;
        }

        $text = $locale === 'ar'
            ? "🔍 نتائج البحث عن: {$query}"
            : "🔍 Search results for: {$query}";

        $keyboard = [];

        foreach ($countries as $country) {
            $name = $locale === 'ar' ? $country->name_ar : $country->name_en;
            $keyboard[] = [[
                'text' => "🏳️ {$name}",
                'callback_data' => "ob:country:{$country->id}",
            ]];
        }

        // Add search again and cancel buttons
        $keyboard[] = [
            [
                'text' => $locale === 'ar' ? '🔍 بحث مرة أخرى' : '🔍 Search Again',
                'callback_data' => 'ob:country:search',
            ],
            [
                'text' => $locale === 'ar' ? '⬅️ إلغاء' : '⬅️ Cancel',
                'callback_data' => 'ob:step3:back',
            ],
        ];

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => $keyboard,
            ],
        ]);

        return null;
    }
}
