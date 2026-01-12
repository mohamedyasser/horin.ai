<?php

namespace App\Telegram\Handlers\Buttons;

use WeStacks\TeleBot\Objects\Update;
use WeStacks\TeleBot\TeleBot;

interface ButtonHandlerInterface
{
    /**
     * Initialize the handler with bot and update instances.
     */
    public function __construct(TeleBot $bot, Update $update);

    /**
     * Get the list of action keys this handler supports.
     *
     * @return array<string>
     */
    public static function supportedActions(): array;
}
