<?php

namespace Tests\Unit\Services;

use App\Services\TelegramBotService;
use Tests\TestCase;
use WeStacks\TeleBot\Laravel\TeleBot;

class TelegramBotServiceTest extends TestCase
{
    protected TelegramBotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        TeleBot::fake();
        $this->service = app(TelegramBotService::class);
    }

    public function test_it_sends_message_with_default_options(): void
    {
        $this->service->sendMessage('123456789', 'Hello, World!');

        TeleBot::assertSent('sendMessage', function ($params) {
            return $params['chat_id'] === '123456789'
                && $params['text'] === 'Hello, World!'
                && $params['parse_mode'] === 'Markdown';
        });
    }

    public function test_it_sends_message_with_inline_keyboard(): void
    {
        $keyboard = [[
            ['text' => 'Button 1', 'callback_data' => 'action:1'],
            ['text' => 'Button 2', 'callback_data' => 'action:2'],
        ]];

        $this->service->sendMessageWithKeyboard('123456789', 'Choose:', $keyboard);

        TeleBot::assertSent('sendMessage', function ($params) {
            return $params['chat_id'] === '123456789'
                && isset($params['reply_markup']['inline_keyboard']);
        });
    }

    public function test_it_sends_message_with_reply_keyboard(): void
    {
        $keyboard = [[
            ['text' => 'Share Phone', 'request_contact' => true],
        ]];

        $this->service->sendMessageWithReplyKeyboard('123456789', 'Share your phone:', $keyboard);

        TeleBot::assertSent('sendMessage', function ($params) {
            return isset($params['reply_markup']['keyboard'])
                && $params['reply_markup']['resize_keyboard'] === true;
        });
    }

    public function test_it_edits_existing_message(): void
    {
        $this->service->editMessage('123456789', 999, 'Updated text');

        TeleBot::assertSent('editMessageText', function ($params) {
            return $params['chat_id'] === '123456789'
                && $params['message_id'] === 999
                && $params['text'] === 'Updated text';
        });
    }

    public function test_it_answers_callback_query(): void
    {
        $this->service->answerCallback('callback_123', 'Action completed!', true);

        TeleBot::assertSent('answerCallbackQuery', function ($params) {
            return $params['callback_query_id'] === 'callback_123'
                && $params['text'] === 'Action completed!'
                && $params['show_alert'] === true;
        });
    }

    public function test_it_removes_reply_keyboard(): void
    {
        $this->service->removeKeyboard('123456789', 'Keyboard removed');

        TeleBot::assertSent('sendMessage', function ($params) {
            return isset($params['reply_markup']['remove_keyboard'])
                && $params['reply_markup']['remove_keyboard'] === true;
        });
    }
}
