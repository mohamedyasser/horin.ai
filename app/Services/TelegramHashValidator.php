<?php

namespace App\Services;

class TelegramHashValidator
{
    /**
     * Validate Telegram Login widget data.
     *
     * Implements Telegram's authentication verification algorithm:
     * 1. Check data contains required fields (hash, auth_date)
     * 2. Verify auth_date is not too old (prevent replay attacks)
     * 3. Build data-check-string from sorted parameters
     * 4. Compute HMAC-SHA256 using SHA256(bot_token) as secret key
     * 5. Compare computed hash with provided hash using timing-safe comparison
     *
     * @see https://core.telegram.org/widgets/login#checking-authorization
     *
     * @param  array<string, mixed>  $data  Authentication data from Telegram widget
     */
    public function validate(array $data): bool
    {
        // Ensure required fields are present
        if (! isset($data['hash'], $data['auth_date'])) {
            return false;
        }

        // Prevent replay attacks by checking timestamp freshness
        $authTimeout = config('telegram.auth_timeout', 86400);
        if ((time() - (int) $data['auth_date']) > $authTimeout) {
            return false;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        // Build data-check-string: sorted key=value pairs joined by newlines
        // Example: "auth_date=1234567890\nfirst_name=John\nid=123456789"
        ksort($data);
        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        // Create secret key: SHA256 hash of bot token (binary format)
        $secretKey = hash('sha256', config('telegram.bot_token'), true);

        // Compute HMAC-SHA256 of data-check-string using secret key
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // Timing-safe comparison to prevent timing attacks
        return hash_equals($computedHash, $hash);
    }
}
