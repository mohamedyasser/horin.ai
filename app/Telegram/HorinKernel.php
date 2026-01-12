<?php

namespace App\Telegram;

use App\Telegram\Commands\AlertsCommand;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\LanguageCommand;
use App\Telegram\Commands\OnboardingCommand;
use App\Telegram\Commands\SettingsCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Handlers\AcknowledgeCallbackHandler;
use App\Telegram\Handlers\Alerts\AlertCreateHandler;
use App\Telegram\Handlers\Alerts\AlertDetailHandler;
use App\Telegram\Handlers\Alerts\AlertHistoryHandler;
use App\Telegram\Handlers\Alerts\AlertListHandler;
use App\Telegram\Handlers\ContactHandler;
use App\Telegram\Handlers\LanguageCallbackHandler;
use App\Telegram\Handlers\Onboarding\Step1Handler;
use App\Telegram\Handlers\Onboarding\Step2Handler;
use App\Telegram\Handlers\Onboarding\Step3Handler;
use App\Telegram\Handlers\Onboarding\Step4Handler;
use App\Telegram\Handlers\Settings\AlertPreferencesHandler;
use App\Telegram\Handlers\Settings\MarketPreferencesHandler;
use App\Telegram\Handlers\Settings\ProfileHandler;
use App\Telegram\Handlers\Settings\SettingsMenuHandler;
use App\Telegram\Handlers\Settings\TradingProfileHandler;
use App\Telegram\Handlers\SnoozeCallbackHandler;
use App\Telegram\Handlers\TextInputHandler;
use WeStacks\TeleBot\Kernel;

class HorinKernel extends Kernel
{
    /**
     * Create a new Horin kernel instance.
     * Handlers are processed in sequence - order matters.
     */
    public function __construct()
    {
        parent::__construct([
            // Commands (processed first)
            StartCommand::class,
            HelpCommand::class,
            AlertsCommand::class,
            SettingsCommand::class,
            LanguageCommand::class,
            OnboardingCommand::class,

            // Callback handlers - Onboarding
            Step1Handler::class,
            Step2Handler::class,
            Step3Handler::class,
            Step4Handler::class,

            // Callback handlers - Settings
            SettingsMenuHandler::class,
            ProfileHandler::class,
            TradingProfileHandler::class,
            MarketPreferencesHandler::class,
            AlertPreferencesHandler::class,

            // Callback handlers - Alerts (full management)
            AlertCreateHandler::class,
            AlertListHandler::class,
            AlertDetailHandler::class,
            AlertHistoryHandler::class,
            // Legacy alert callbacks
            SnoozeCallbackHandler::class,
            AcknowledgeCallbackHandler::class,
            LanguageCallbackHandler::class,

            // Text input handler (for name change, country search)
            TextInputHandler::class,

            // Contact handler for phone verification (should be last)
            ContactHandler::class,
        ]);
    }
}
