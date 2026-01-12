<?php

namespace App\Telegram\Services;

use App\Models\User;

class DefaultKeyboardBuilder
{
    /**
     * Get the default keyboard for a user based on their state.
     */
    public static function forUser(?User $user, ?string $locale = null): array
    {
        // No user or not verified - show phone verification keyboard
        if (! $user || ! $user->hasVerifiedPhone()) {
            return self::phoneVerificationKeyboard($locale ?? 'en');
        }

        // User not onboarded - show onboarding keyboard
        if (! $user->hasCompletedOnboarding()) {
            return self::onboardingKeyboard($user->language ?? 'en');
        }

        // Fully verified user - show main menu
        return self::mainMenuKeyboard($user->language ?? 'en');
    }

    /**
     * Phone verification keyboard - for unverified users.
     */
    public static function phoneVerificationKeyboard(string $locale): array
    {
        $buttonText = $locale === 'ar' ? '📱 مشاركة رقم الهاتف' : '📱 Share Phone Number';

        return [
            'keyboard' => [[
                [
                    'text' => $buttonText,
                    'request_contact' => true,
                ],
            ]],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Onboarding keyboard - for users in onboarding.
     */
    public static function onboardingKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '▶️ متابعة الإعداد']],
                    [['text' => '❓ مساعدة']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '▶️ Continue Setup']],
                [['text' => '❓ Help']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Main menu keyboard - for fully verified users.
     */
    public static function mainMenuKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📋 التنبيهات'], ['text' => '➕ تنبيه جديد']],
                    [['text' => '⚙️ الإعدادات'], ['text' => '❓ مساعدة']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📋 Alerts'], ['text' => '➕ New Alert']],
                [['text' => '⚙️ Settings'], ['text' => '❓ Help']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Get the "I don't understand" message with default keyboard.
     */
    public static function getUnknownInputResponse(?User $user, ?string $locale = null): array
    {
        $locale = $locale ?? $user?->language ?? 'en';

        $message = $locale === 'ar'
            ? 'عذراً، لم أفهم ذلك. يرجى اختيار أحد الخيارات من القائمة أدناه:'
            : "Sorry, I didn't understand that. Please choose from the options below:";

        return [
            'text' => $message,
            'reply_markup' => self::forUser($user, $locale),
        ];
    }

    /**
     * Settings menu keyboard.
     */
    public static function settingsKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '👤 الملف الشخصي'], ['text' => '📊 التداول']],
                    [['text' => '🌍 الأسواق'], ['text' => '🔔 إعدادات التنبيهات']],
                    [['text' => '🌐 اللغة']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '👤 Profile'], ['text' => '📊 Trading']],
                [['text' => '🌍 Markets'], ['text' => '🔔 Alert Settings']],
                [['text' => '🌐 Language']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Alerts menu keyboard.
     */
    public static function alertsKeyboard(string $locale): array
    {
        if ($locale === 'ar') {
            return [
                'keyboard' => [
                    [['text' => '📋 تنبيهاتي'], ['text' => '➕ تنبيه جديد']],
                    [['text' => '📜 السجل']],
                    [['text' => '◀️ رجوع']],
                ],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        return [
            'keyboard' => [
                [['text' => '📋 My Alerts'], ['text' => '➕ New Alert']],
                [['text' => '📜 History']],
                [['text' => '◀️ Back']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Language selection keyboard.
     */
    public static function languageKeyboard(): array
    {
        return [
            'keyboard' => [
                [['text' => '🇬🇧 English'], ['text' => '🇸🇦 العربية']],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /**
     * Remove keyboard (for special cases).
     */
    public static function removeKeyboard(): array
    {
        return [
            'remove_keyboard' => true,
        ];
    }
}
