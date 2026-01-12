<?php

namespace App\Telegram;

use App\Telegram\Commands\AlertsCommand;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\LanguageCommand;
use App\Telegram\Commands\OnboardingCommand;
use App\Telegram\Commands\SettingsCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Handlers\ContactHandler;
use App\Telegram\Handlers\KeyboardButtonHandler;
use App\Telegram\Handlers\OnboardingTextHandler;
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

            // Onboarding text handler (for button presses during onboarding)
            OnboardingTextHandler::class,

            // Text input handler (for name change, country search)
            TextInputHandler::class,

            // Keyboard button handler (maps button text to commands, fallback for unknown input)
            KeyboardButtonHandler::class,

            // Contact handler for phone verification (should be last)
            ContactHandler::class,
        ]);
    }
}
