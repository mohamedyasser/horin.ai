<?php

namespace App\Telegram;

use App\Telegram\Commands\AlertsCommand;
use App\Telegram\Commands\HelpCommand;
use App\Telegram\Commands\LanguageCommand;
use App\Telegram\Commands\SettingsCommand;
use App\Telegram\Commands\StartCommand;
use App\Telegram\Handlers\AcknowledgeCallbackHandler;
use App\Telegram\Handlers\ContactHandler;
use App\Telegram\Handlers\LanguageCallbackHandler;
use App\Telegram\Handlers\SnoozeCallbackHandler;
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

            // Callback handlers
            SnoozeCallbackHandler::class,
            AcknowledgeCallbackHandler::class,
            LanguageCallbackHandler::class,

            // Contact handler for phone verification
            ContactHandler::class,
        ]);
    }
}
