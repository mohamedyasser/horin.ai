<?php

namespace App\Telegram\Handlers;

use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for no-operation callbacks.
 *
 * This handles clicks on buttons that are meant to display information
 * but not trigger any action (like labels or static display buttons).
 */
class NoopHandler extends CallbackHandler
{
    protected string $match = '/^noop$/';

    public function handle(): mixed
    {
        // Just acknowledge the callback without doing anything
        $this->answerCallbackQuery(['text' => '']);

        return null;
    }
}
