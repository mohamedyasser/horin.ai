<?php

namespace App\Console\Commands;

use App\Jobs\Alerts\ProcessIntelligenceAlerts;
use App\Services\AlertCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use MessagePack\MessagePack;
use RedisException;

class AlertsListen extends Command
{
    protected $signature = 'alerts:listen
        {--channels=* : Specific channels to listen to (default: all)}
        {--dry-run : Process messages without dispatching jobs}';

    protected $description = 'Listen to Redis channels for ML pipeline signals and dispatch alert processing jobs';

    private int $reconnectAttempts = 0;

    private const MAX_RECONNECT_DELAY = 30;

    private bool $shouldRun = true;

    /**
     * MessagePack-encoded channels from ML pipeline
     */
    private array $messagePackChannels = [
        // Priority channels (from signal-classification)
        'classified_critical',
        'classified_high',
        'classified_medium',
        'classified_low',
        'classified_info',
        // Action channels (from signal-classification)
        'action_strong_buy',
        'action_buy',
        'action_hold',
        'action_sell',
        'action_strong_sell',
        'action_wait',
        'action_monitor',
        'action_take_profit',
        'action_stop_loss',
        // Signal channels (from signal-detection)
        'pattern_updates',
        'detected_signals',
        'processed_signals',
        // Technical analysis channels (from technical-analysis)
        'technical_indicators',
        // Execution channels (from signal-consumers)
        'execution_results',
        // Anomaly channels (from anomaly - can be MessagePack)
        'anomaly_alerts',
    ];

    /**
     * JSON-encoded channels
     */
    private array $jsonChannels = [
        // Recommendation channels (from recommendation)
        'trading_recommendations',
        // Prediction channels (from forecasting)
        'price_predictions',
        // Real-time price updates (from price-collector)
        'price_updates',
    ];

    public function __construct(
        private readonly AlertCacheService $cacheService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Alert Listener...');

        // Register signal handlers for graceful shutdown
        $this->registerSignalHandlers();

        // Warm up the alert cache
        $this->cacheService->cacheActiveAlerts();
        $this->info('Alert cache warmed up');

        while ($this->shouldRun) {
            try {
                $this->subscribeToChannels();
            } catch (RedisException $e) {
                $this->handleDisconnection($e);
            } catch (\Throwable $e) {
                Log::error('Alert listener error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                sleep(5);
            }
        }

        $this->info('Alert Listener stopped gracefully');

        return Command::SUCCESS;
    }

    private function registerSignalHandlers(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, function () {
                $this->info('Received SIGTERM, shutting down...');
                $this->shouldRun = false;
            });

            pcntl_signal(SIGINT, function () {
                $this->info('Received SIGINT, shutting down...');
                $this->shouldRun = false;
            });
        }
    }

    private function subscribeToChannels(): void
    {
        $channels = $this->getChannelsToSubscribe();

        $this->info('Subscribing to channels: '.implode(', ', $channels));

        // Use a dedicated Redis connection for pub/sub
        $redis = Redis::connection('pubsub')->client();

        // Reset reconnection counter on successful connection
        $this->reconnectAttempts = 0;

        // Record connection metric
        $this->recordMetric('redis.subscriber.connected', 1);

        $redis->subscribe($channels, function ($redis, $channel, $message) {
            $this->processMessage($channel, $message);
        });
    }

    private function getChannelsToSubscribe(): array
    {
        $specifiedChannels = $this->option('channels');

        if (! empty($specifiedChannels)) {
            return $specifiedChannels;
        }

        return array_merge($this->messagePackChannels, $this->jsonChannels);
    }

    private function processMessage(string $channel, string $message): void
    {
        $startTime = microtime(true);

        try {
            // Decode message based on channel type
            $data = $this->decodeMessage($channel, $message);

            if ($data === null) {
                Log::warning('Failed to decode message', [
                    'channel' => $channel,
                    'message_length' => strlen($message),
                ]);

                return;
            }

            // Extract asset ID from message
            $pid = $this->extractPid($data);

            if (! $pid) {
                Log::debug('No pid in message', ['channel' => $channel]);

                return;
            }

            if ($this->option('dry-run')) {
                $this->info("Would process alert for pid {$pid} from channel {$channel}");

                return;
            }

            // Dispatch processing job - it will handle asset resolution and alert matching
            ProcessIntelligenceAlerts::dispatch($data, $channel);

            // Record metrics
            $latencyMs = (microtime(true) - $startTime) * 1000;
            $this->recordMetric('alerts.listener.dispatch_ms', $latencyMs, ['channel' => $channel]);

            Log::debug('Alert processing job dispatched', [
                'channel' => $channel,
                'pid' => $pid,
                'latency_ms' => round($latencyMs, 2),
            ]);

        } catch (\Throwable $e) {
            Log::error('Error processing message', [
                'channel' => $channel,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->recordMetric('alerts.listener.errors', 1, ['channel' => $channel]);
        }
    }

    private function decodeMessage(string $channel, string $message): ?array
    {
        if (in_array($channel, $this->jsonChannels)) {
            $decoded = json_decode($message, true);

            return is_array($decoded) ? $decoded : null;
        }

        // MessagePack decoding
        try {
            if (class_exists(MessagePack::class)) {
                return MessagePack::unpack($message);
            }

            // Fallback to msgpack extension if available
            if (function_exists('msgpack_unpack')) {
                return msgpack_unpack($message);
            }

            // Try JSON as ultimate fallback
            $decoded = json_decode($message, true);

            return is_array($decoded) ? $decoded : null;

        } catch (\Throwable $e) {
            // Try JSON as fallback (some channels may switch encoding)
            $decoded = json_decode($message, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            Log::error('Failed to decode MessagePack', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractPid(array $data): ?string
    {
        // Different channels use different keys for asset ID
        // - pid: signal-classification, signal-detection, pattern-detection, technical-analysis
        // - symbol: anomaly service
        // - asset_id: some internal formats
        // - predictions may have nested structure
        return $data['pid']
            ?? $data['symbol']
            ?? $data['asset_id']
            ?? $data['prediction']['pid']
            ?? null;
    }

    private function handleDisconnection(RedisException $e): void
    {
        $this->reconnectAttempts++;

        $delay = min(
            pow(2, $this->reconnectAttempts),
            self::MAX_RECONNECT_DELAY
        );

        $this->warn("Redis disconnected, reconnecting in {$delay}s (attempt {$this->reconnectAttempts})");

        Log::warning('Redis subscriber disconnected', [
            'attempt' => $this->reconnectAttempts,
            'error' => $e->getMessage(),
            'delay_seconds' => $delay,
        ]);

        // Record disconnection metric
        $this->recordMetric('redis.subscriber.connected', 0);
        $this->recordMetric('redis.subscriber.reconnects', 1);

        // Alert ops if too many failures
        if ($this->reconnectAttempts >= 5) {
            $this->alertOpsTeam('Redis subscriber failing', $e);
        }

        sleep($delay);
    }

    private function alertOpsTeam(string $message, \Throwable $e): void
    {
        Log::critical($message, [
            'error' => $e->getMessage(),
            'reconnect_attempts' => $this->reconnectAttempts,
        ]);

        // Could also send Telegram/Slack notification to ops channel
    }

    private function recordMetric(string $name, float $value, array $labels = []): void
    {
        // Integrate with your metrics system (Prometheus, StatsD, etc.)
        // For now, just log in debug mode
        if (config('app.debug')) {
            Log::debug("Metric: {$name}", ['value' => $value, 'labels' => $labels]);
        }
    }
}
