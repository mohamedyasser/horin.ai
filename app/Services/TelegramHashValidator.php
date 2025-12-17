<?php

namespace App\Services;

class TelegramHashValidator
{
    /**
     * Validate Telegram Login widget data.
     *
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data): bool
    {
        if (! isset($data['hash'], $data['auth_date'])) {
            return false;
        }

        // Check auth_date is not too old
        $authTimeout = config('telegram.auth_timeout', 86400);
        if ((time() - (int) $data['auth_date']) > $authTimeout) {
            return false;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        // Build data-check-string
        ksort($data);
        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        // Create secret key from bot token
        $secretKey = hash('sha256', config('telegram.bot_token'), true);

        // Compute hash
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($computedHash, $hash);
    }
}
