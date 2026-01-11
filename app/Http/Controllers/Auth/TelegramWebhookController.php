<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Laravel\TeleBot;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming Telegram webhook requests.
     *
     * Routes the update through the TeleBot kernel which dispatches
     * to the appropriate handler based on update type.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $update = $request->all();

            Log::debug('Telegram webhook received', [
                'update_id' => $update['update_id'] ?? null,
                'type' => $this->getUpdateType($update),
            ]);

            // Process through TeleBot kernel
            TeleBot::handleUpdate($update);

            return response()->json(['ok' => true]);

        } catch (\Exception $e) {
            Log::error('Telegram webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return ok to Telegram to prevent retries
            return response()->json(['ok' => true]);
        }
    }

    /**
     * Get the update type for logging.
     */
    private function getUpdateType(array $update): string
    {
        if (isset($update['message']['text'])) {
            $text = $update['message']['text'];
            if (str_starts_with($text, '/')) {
                return 'command:'.explode(' ', $text)[0];
            }

            return 'message';
        }

        if (isset($update['message']['contact'])) {
            return 'contact';
        }

        if (isset($update['callback_query'])) {
            return 'callback:'.(explode(':', $update['callback_query']['data'] ?? '')[0] ?? 'unknown');
        }

        return 'unknown';
    }
}
