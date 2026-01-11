<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class TokenBucketRateLimiter
{
    private const KEY_PREFIX = 'rate_limit:';

    public function __construct(
        private readonly int $capacity,
        private readonly float $refillRate, // tokens per second
    ) {}

    /**
     * Attempt to consume tokens
     */
    public function attempt(string $key, int $tokens = 1): bool
    {
        $fullKey = self::KEY_PREFIX.$key;
        $now = microtime(true);

        // Lua script for atomic token bucket operation
        $script = <<<'LUA'
            local key = KEYS[1]
            local capacity = tonumber(ARGV[1])
            local refill_rate = tonumber(ARGV[2])
            local now = tonumber(ARGV[3])
            local tokens_needed = tonumber(ARGV[4])

            local bucket = redis.call('HMGET', key, 'tokens', 'last_update')
            local current_tokens = tonumber(bucket[1]) or capacity
            local last_update = tonumber(bucket[2]) or now

            -- Calculate tokens to add based on time elapsed
            local elapsed = now - last_update
            local tokens_to_add = elapsed * refill_rate
            current_tokens = math.min(capacity, current_tokens + tokens_to_add)

            -- Check if enough tokens
            if current_tokens >= tokens_needed then
                current_tokens = current_tokens - tokens_needed
                redis.call('HMSET', key, 'tokens', current_tokens, 'last_update', now)
                redis.call('EXPIRE', key, 86400)
                return 1
            else
                redis.call('HMSET', key, 'tokens', current_tokens, 'last_update', now)
                redis.call('EXPIRE', key, 86400)
                return 0
            end
        LUA;

        $result = Redis::eval(
            $script,
            1, // number of keys
            $fullKey,
            $this->capacity,
            $this->refillRate,
            $now,
            $tokens
        );

        return (bool) $result;
    }

    /**
     * Get remaining tokens
     */
    public function remaining(string $key): int
    {
        $fullKey = self::KEY_PREFIX.$key;
        $tokens = Redis::hget($fullKey, 'tokens');

        return $tokens !== null ? (int) $tokens : $this->capacity;
    }

    /**
     * Reset the bucket
     */
    public function reset(string $key): void
    {
        $fullKey = self::KEY_PREFIX.$key;
        Redis::del($fullKey);
    }

    /**
     * Create a rate limiter for user notifications (10 per hour)
     */
    public static function forUserNotifications(): self
    {
        return new self(
            capacity: 10,        // Max 10 tokens
            refillRate: 0.00278  // 10/3600 = ~10 per hour
        );
    }

    /**
     * Create a rate limiter for daily alerts (25 per day)
     */
    public static function forDailyAlerts(): self
    {
        return new self(
            capacity: 25,         // Max 25 tokens
            refillRate: 0.000289  // 25/86400 = ~25 per day
        );
    }

    /**
     * Create a rate limiter for hourly notifications (10 per hour)
     */
    public static function forHourlyNotifications(): self
    {
        return new self(
            capacity: 10,        // Max 10 tokens
            refillRate: 0.00278  // 10/3600 = ~10 per hour
        );
    }
}
