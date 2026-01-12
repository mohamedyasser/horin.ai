<?php

namespace App\Telegram\Keyboards;

use App\Models\User;

class MainMenuKeyboard
{
    /**
     * Get the default keyboard for a user based on their state.
     */
    public static function forUser(?User $user, ?string $locale = null): array
    {
        if (! $user) {
            return self::languageKeyboard();
        }

        if (! $user->hasVerifiedPhone()) {
            return self::phoneVerificationKeyboard($user->language ?? $locale ?? 'en');
        }

        if (! $user->hasCompletedOnboarding()) {
            return self::onboardingKeyboard($user->language ?? 'en');
        }

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
     * Get the "I don't understand" message with default keyboard.
     */
    public static function getUnknownInputResponse(?User $user, ?string $locale = null): array
    {
        if (! $user) {
            return [
                'text' => "👋 Welcome! / مرحباً!\n\nPlease select your language:\nيرجى اختيار لغتك:",
                'reply_markup' => self::languageKeyboard(),
            ];
        }

        $locale = $locale ?? $user->language ?? 'en';

        $message = $locale === 'ar'
            ? 'عذراً، لم أفهم ذلك. يرجى اختيار أحد الخيارات من القائمة أدناه:'
            : "Sorry, I didn't understand that. Please choose from the options below:";

        return [
            'text' => $message,
            'reply_markup' => self::forUser($user, $locale),
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
