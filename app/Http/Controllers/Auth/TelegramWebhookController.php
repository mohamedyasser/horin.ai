<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\TeleBot;

class TelegramWebhookController extends Controller
{
    public function __construct(
        private TeleBot $bot
    ) {
        $this->bot = new TeleBot(config('telegram.bot_token'));
    }

    /**
     * Handle incoming Telegram webhook requests.
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate webhook secret token
        if (config('telegram.webhook_secret')) {
            $expectedToken = config('telegram.webhook_secret');
            $receivedToken = $request->header('X-Telegram-Bot-Api-Secret-Token');
            
            if (! $receivedToken || ! hash_equals($expectedToken, $receivedToken)) {
                Log::warning('Invalid Telegram webhook token', [
                    'ip' => $request->ip(),
                ]);
                abort(403, 'Invalid webhook token');
            }
        }

        $update = $request->all();

        Log::info('Telegram webhook received', ['update' => $update]);

        if (isset($update['message']['contact'])) {
            $this->handleContact($update['message']);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle contact message for phone verification.
     */
    protected function handleContact(array $message): void
    {
        $contact = $message['contact'];
        $from = $message['from'];
        $chatId = $message['chat']['id'];

        if ($contact['user_id'] !== $from['id']) {
            $this->sendMessage(
                $chatId,
                __('auth.telegram_verification.contact_mismatch')
            );

            return;
        }

        $user = User::query()->where('telegram_id', $from['id'])->first();

        if (! $user) {
            $this->sendMessage(
                $chatId,
                __('auth.telegram_verification.user_not_found')
            );

            return;
        }

        $normalizedPhone = $this->normalizePhone($contact['phone_number']);

        $user->phone = $normalizedPhone;
        $user->markPhoneAsVerified();

        $this->sendMessage(
            $chatId,
            __('auth.telegram_verification.success')
        );
    }

    /**
     * Send a message to a Telegram chat.
     */
    protected function sendMessage(int $chatId, string $text): void
    {
        try {
            $this->bot->sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'text' => $text,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalize phone number to ensure it starts with +.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        return $phone;
    }
}
