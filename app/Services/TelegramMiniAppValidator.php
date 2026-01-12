<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class TelegramMiniAppValidator
{
    /**
     * Validate Telegram Mini App init data.
     *
     * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
     *
     * @return array<string, mixed>|false Returns parsed data with user info or false if invalid
     */
    public function validate(string $initData, ?int $maxAge = null): array|false
    {
        if (empty($initData)) {
            return false;
        }

        $maxAge ??= config('telegram.auth_timeout', 86400);

        // Parse URL-encoded init data
        parse_str($initData, $data);

        if (! isset($data['hash'], $data['auth_date'])) {
            Log::debug('Mini App validation failed: missing hash or auth_date');

            return false;
        }

        // Check auth_date freshness
        $authDate = (int) $data['auth_date'];
        if ((time() - $authDate) > $maxAge) {
            Log::debug('Mini App validation failed: auth_date too old', [
                'auth_date' => $authDate,
                'max_age' => $maxAge,
            ]);

            return false;
        }

        $receivedHash = $data['hash'];
        unset($data['hash']);

        // Build data-check-string (sorted alphabetically, joined with newlines)
        ksort($data);
        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        // Create secret key: HMAC_SHA256(bot_token, "WebAppData")
        $botToken = config('telegram.bot_token');
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        // Compute hash: HMAC_SHA256(data_check_string, secret_key)
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($computedHash, $receivedHash)) {
            Log::debug('Mini App validation failed: hash mismatch');

            return false;
        }

        // Parse user JSON if present
        if (isset($data['user'])) {
            $data['user'] = json_decode($data['user'], true);
        }

        return $data;
    }

    /**
     * Extract user data from validated init data.
     *
     * @param  array<string, mixed>  $validatedData
     * @return array<string, mixed>|null
     */
    public function extractUser(array $validatedData): ?array
    {
        return $validatedData['user'] ?? null;
    }
}
