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
use WeStacks\TeleBot\Foundation\Kernel;

class HorinKernel extends Kernel
{
    /**
     * Registered update handlers.
     * Order matters - handlers are processed in sequence.
     */
    protected array $handlers = [
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
    ];
}
