<?php

namespace App\Telegram\Services;

use App\Models\User;
use App\Telegram\Keyboards\AlertsKeyboard;
use App\Telegram\Keyboards\MainMenuKeyboard;
use App\Telegram\Keyboards\MarketsKeyboard;
use App\Telegram\Keyboards\SettingsKeyboard;
use App\Telegram\Keyboards\TradingKeyboard;

/**
 * Facade for keyboard builders.
 * Delegates to domain-specific keyboard classes in App\Telegram\Keyboards.
 */
class DefaultKeyboardBuilder
{
    // === Main Menu Keyboards (delegates to MainMenuKeyboard) ===

    public static function forUser(?User $user, ?string $locale = null): array
    {
        return MainMenuKeyboard::forUser($user, $locale);
    }

    public static function phoneVerificationKeyboard(string $locale): array
    {
        return MainMenuKeyboard::phoneVerificationKeyboard($locale);
    }

    public static function onboardingKeyboard(string $locale): array
    {
        return MainMenuKeyboard::onboardingKeyboard($locale);
    }

    public static function mainMenuKeyboard(string $locale): array
    {
        return MainMenuKeyboard::mainMenuKeyboard($locale);
    }

    public static function languageKeyboard(): array
    {
        return MainMenuKeyboard::languageKeyboard();
    }

    public static function getUnknownInputResponse(?User $user, ?string $locale = null): array
    {
        return MainMenuKeyboard::getUnknownInputResponse($user, $locale);
    }

    public static function removeKeyboard(): array
    {
        return MainMenuKeyboard::removeKeyboard();
    }

    // === Settings Keyboards (delegates to SettingsKeyboard) ===

    public static function settingsKeyboard(string $locale): array
    {
        return SettingsKeyboard::menu($locale);
    }

    public static function profileKeyboard(string $locale): array
    {
        return SettingsKeyboard::profileKeyboard($locale);
    }

    public static function alertPreferencesKeyboard(string $locale): array
    {
        return SettingsKeyboard::alertPreferencesKeyboard($locale);
    }

    public static function channelsKeyboard(array $enabledChannels, string $locale): array
    {
        return SettingsKeyboard::channelsKeyboard($enabledChannels, $locale);
    }

    public static function limitsKeyboard(int $maxHour, int $maxDay, string $locale): array
    {
        return SettingsKeyboard::limitsKeyboard($maxHour, $maxDay, $locale);
    }

    // === Trading Keyboards (delegates to TradingKeyboard) ===

    public static function tradingKeyboard(string $locale): array
    {
        return TradingKeyboard::menu($locale);
    }

    public static function experienceKeyboard(string $locale): array
    {
        return TradingKeyboard::experienceKeyboard($locale);
    }

    public static function riskKeyboard(string $locale): array
    {
        return TradingKeyboard::riskKeyboard($locale);
    }

    public static function goalKeyboard(string $locale): array
    {
        return TradingKeyboard::goalKeyboard($locale);
    }

    public static function styleKeyboard(string $locale): array
    {
        return TradingKeyboard::styleKeyboard($locale);
    }

    // === Markets Keyboards (delegates to MarketsKeyboard) ===

    public static function marketsSettingsKeyboard(string $locale): array
    {
        return MarketsKeyboard::menu($locale);
    }

    // === Alerts Keyboards (delegates to AlertsKeyboard) ===

    public static function alertsKeyboard(string $locale): array
    {
        return AlertsKeyboard::menu($locale);
    }

    public static function alertTypeKeyboard(string $locale): array
    {
        return AlertsKeyboard::alertTypeKeyboard($locale);
    }

    public static function alertTriggerKeyboard(string $alertType, string $locale): array
    {
        return AlertsKeyboard::alertTriggerKeyboard($alertType, $locale);
    }

    public static function alertDirectionKeyboard(string $locale): array
    {
        return AlertsKeyboard::alertDirectionKeyboard($locale);
    }

    public static function alertConfirmKeyboard(string $locale): array
    {
        return AlertsKeyboard::alertConfirmKeyboard($locale);
    }

    public static function alertSuccessKeyboard(string $locale): array
    {
        return AlertsKeyboard::alertSuccessKeyboard($locale);
    }

    public static function alertActionsKeyboard(string $status, bool $isSnoozed, string $locale): array
    {
        return AlertsKeyboard::alertActionsKeyboard($status, $isSnoozed, $locale);
    }

    public static function snoozeOptionsKeyboard(string $locale): array
    {
        return AlertsKeyboard::snoozeOptionsKeyboard($locale);
    }

    public static function deleteConfirmKeyboard(string $locale): array
    {
        return AlertsKeyboard::deleteConfirmKeyboard($locale);
    }

    public static function cancelKeyboard(string $locale): array
    {
        return AlertsKeyboard::cancelKeyboard($locale);
    }

    public static function assetSearchKeyboard(string $locale): array
    {
        return AlertsKeyboard::assetSearchKeyboard($locale);
    }

    public static function anomalyTypeKeyboard(string $locale): array
    {
        return AlertsKeyboard::anomalyTypeKeyboard($locale);
    }

    public static function patternTypeKeyboard(string $locale): array
    {
        return AlertsKeyboard::patternTypeKeyboard($locale);
    }

    public static function recommendationTypeKeyboard(string $locale): array
    {
        return AlertsKeyboard::recommendationTypeKeyboard($locale);
    }

    public static function confidenceLevelKeyboard(string $locale): array
    {
        return AlertsKeyboard::confidenceLevelKeyboard($locale);
    }
}
