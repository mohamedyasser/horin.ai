# Task 04: Redis Channel Listener

**Priority:** P0 (Critical Path)
**Effort:** 2 days
**Dependencies:** Task 02, Task 03

---

## Objective

Create the long-running Laravel Artisan command that subscribes to all ML pipeline Redis channels and dispatches alert processing jobs.

---

## Checklist

- [ ] Create `AlertsListen` Artisan command
- [ ] Implement Redis Pub/Sub subscription
- [ ] Add MessagePack decoder for binary channels
- [ ] Add JSON decoder for JSON channels
- [ ] Implement channel routing logic
- [ ] Add reconnection with exponential backoff
- [ ] Add health check endpoint
- [ ] Add graceful shutdown handling
- [ ] Create Supervisor configuration
- [ ] Test with real Redis messages

---

## Channel Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                    CHANNELS TO SUBSCRIBE                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  Priority Channels (MessagePack):                                   │
│  • classified_critical   • classified_high    • classified_medium   │
│  • classified_low        • classified_info                          │
│                                                                      │
│  Action Channels (MessagePack):                                     │
│  • action_strong_buy     • action_buy         • action_hold         │
│  • action_sell           • action_strong_sell • action_wait         │
│  • action_monitor        • action_take_profit • action_stop_loss    │
│                                                                      │
│  Pattern & Anomaly Channels:                                        │
│  • pattern_updates (MessagePack)                                    │
│  • anomaly_alerts (JSON)                                            │
│  • detected_signals (MessagePack)                                   │
│  • processed_signals (MessagePack)                                  │
│                                                                      │
│  Recommendation Channel:                                            │
│  • trading_recommendations (JSON)                                   │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## AlertsListen Command

```bash
php artisan make:command AlertsListen
```

```php
<?php

namespace App\Console\Commands;

use App\Jobs\Alerts\ProcessIntelligenceAlerts;
use App\Services\AlertCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use MessagePack\MessagePack;
use Redis as PhpRedis;
use RedisException;

class AlertsListen extends Command
{
    protected $signature = 'alerts:listen
        {--channels=* : Specific channels to listen to (default: all)}
        {--dry-run : Process messages without dispatching jobs}';

    protected $description = 'Listen to Redis channels for alert processing';

    private int $reconnectAttempts = 0;
    private const MAX_RECONNECT_DELAY = 30;
    private bool $shouldRun = true;

    /**
     * All channels grouped by encoding type
     */
    private array $messagePackChannels = [
        // Priority channels
        'classified_critical',
        'classified_high',
        'classified_medium',
        'classified_low',
        'classified_info',
        // Action channels
        'action_strong_buy',
        'action_buy',
        'action_hold',
        'action_sell',
        'action_strong_sell',
        'action_wait',
        'action_monitor',
        'action_take_profit',
        'action_stop_loss',
        // Signal channels
        'pattern_updates',
        'detected_signals',
        'processed_signals',
    ];

    private array $jsonChannels = [
        'anomaly_alerts',
        'trading_recommendations',
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

        $this->info('Subscribing to channels: ' . implode(', ', $channels));

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

        if (!empty($specifiedChannels)) {
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
            $assetId = $this->extractAssetId($data);

            if (!$assetId) {
                Log::debug('No asset ID in message', ['channel' => $channel]);
                return;
            }

            // Quick check: does this asset have any active alerts?
            if (!$this->cacheService->hasActiveAlerts($assetId)) {
                return; // Skip processing - no alerts for this asset
            }

            // Determine alert type from channel
            $alertType = $this->mapChannelToAlertType($channel);

            if ($this->option('dry-run')) {
                $this->info("Would process {$alertType} alert for asset {$assetId}");
                return;
            }

            // Dispatch processing job
            ProcessIntelligenceAlerts::dispatch($alertType, $assetId, $data, $channel);

            // Record metrics
            $latencyMs = (microtime(true) - $startTime) * 1000;
            $this->recordMetric('alerts.processing.duration_ms', $latencyMs, ['type' => $alertType]);

            Log::debug('Alert processing job dispatched', [
                'channel' => $channel,
                'asset_id' => $assetId,
                'alert_type' => $alertType,
                'latency_ms' => $latencyMs,
            ]);

        } catch (\Throwable $e) {
            Log::error('Error processing message', [
                'channel' => $channel,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->recordMetric('alerts.processing.errors', 1, ['channel' => $channel]);
        }
    }

    private function decodeMessage(string $channel, string $message): ?array
    {
        if (in_array($channel, $this->jsonChannels)) {
            return json_decode($message, true);
        }

        // MessagePack decoding
        try {
            return MessagePack::unpack($message);
        } catch (\Throwable $e) {
            // Try JSON as fallback (some channels may switch encoding)
            $decoded = json_decode($message, true);
            if ($decoded !== null) {
                return $decoded;
            }

            Log::error('Failed to decode MessagePack', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function extractAssetId(array $data): ?string
    {
        // Different channels use different keys for asset ID
        return $data['pid'] ?? $data['asset_id'] ?? $data['symbol'] ?? null;
    }

    private function mapChannelToAlertType(string $channel): string
    {
        // Priority channels -> signal alerts
        if (str_starts_with($channel, 'classified_')) {
            return 'signal';
        }

        // Action channels -> recommendation alerts
        if (str_starts_with($channel, 'action_')) {
            return 'recommendation';
        }

        // Specific channels
        return match ($channel) {
            'pattern_updates' => 'pattern',
            'anomaly_alerts' => 'anomaly',
            'detected_signals', 'processed_signals' => 'signal',
            'trading_recommendations' => 'recommendation',
            default => 'signal',
        };
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
        // For now, just log
        Log::debug("Metric: {$name}", ['value' => $value, 'labels' => $labels]);
    }
}
```

---

## Redis Configuration

Add dedicated pub/sub connection in `config/database.php`:

```php
'redis' => [
    // ... existing connections

    'pubsub' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'read_timeout' => -1, // Infinite timeout for pub/sub
    ],
],
```

---

## MessagePack Dependency

Install MessagePack PHP extension or library:

```bash
# Option 1: PECL extension (faster)
pecl install msgpack

# Option 2: Composer package (pure PHP)
composer require rybakit/msgpack
```

Add to `composer.json` if using rybakit:

```json
{
    "require": {
        "rybakit/msgpack": "^0.8"
    }
}
```

---

## Health Check Command

```bash
php artisan make:command AlertsHealthCheck
```

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AlertsHealthCheck extends Command
{
    protected $signature = 'alerts:health-check';
    protected $description = 'Check alert system health';

    public function handle(): int
    {
        $issues = [];
        $this->info('Running Alert System Health Check...');

        // Check 1: Redis subscriber process running
        $this->task('Redis Subscriber Running', function () use (&$issues) {
            $running = $this->isRedisSubscriberRunning();
            if (!$running) {
                $issues[] = 'Redis subscriber not running';
            }
            return $running;
        });

        // Check 2: Redis connection
        $this->task('Redis Connection', function () use (&$issues) {
            try {
                Redis::ping();
                return true;
            } catch (\Exception $e) {
                $issues[] = 'Redis connection failed: ' . $e->getMessage();
                return false;
            }
        });

        // Check 3: Queue depth
        $this->task('Queue Depth', function () use (&$issues) {
            $queueDepth = Queue::size('alerts');
            if ($queueDepth > 1000) {
                $issues[] = "Alert queue depth high: {$queueDepth}";
                return false;
            }
            return true;
        });

        // Check 4: Recent activity
        $this->task('Recent Activity', function () use (&$issues) {
            $recentAlerts = \App\Models\AlertHistory::where('triggered_at', '>', now()->subHours(24))
                ->count();
            $this->info("  Alerts in last 24h: {$recentAlerts}");
            return true;
        });

        // Summary
        $this->newLine();
        if (count($issues) > 0) {
            $this->error('Issues found:');
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }
            return Command::FAILURE;
        }

        $this->info('All checks passed!');
        return Command::SUCCESS;
    }

    private function isRedisSubscriberRunning(): bool
    {
        // Check if the alerts:listen process is running
        $output = shell_exec('ps aux | grep "alerts:listen" | grep -v grep');
        return !empty($output);
    }

    private function task(string $name, callable $callback): void
    {
        $this->output->write("  {$name}... ");

        try {
            $result = $callback();
            $this->output->writeln($result ? '<info>OK</info>' : '<error>FAIL</error>');
        } catch (\Exception $e) {
            $this->output->writeln('<error>ERROR</error>');
        }
    }
}
```

---

## Supervisor Configuration

Create `/etc/supervisor/conf.d/alerts-listener.conf`:

```ini
[program:alerts-listener]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan alerts:listen
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/app/storage/logs/alerts-listener.log
stopwaitsecs=30
```

Update supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start alerts-listener:*
```

---

## Systemd Alternative

Create `/etc/systemd/system/alerts-listener.service`:

```ini
[Unit]
Description=Kira Alerts Redis Listener
After=network.target redis.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/app
ExecStart=/usr/bin/php artisan alerts:listen
Restart=always
RestartSec=5
StandardOutput=append:/var/www/app/storage/logs/alerts-listener.log
StandardError=append:/var/www/app/storage/logs/alerts-listener.log

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl enable alerts-listener
sudo systemctl start alerts-listener
```

---

## Message Schemas

### Classified Signal (MessagePack)

```python
{
    'id': str,                    # Classification ID (UUID)
    'pid': str,                   # Product/Asset ID
    'original_signal': {
        'id': str,
        'indicator': str,         # RSI, MACD, Bollinger, etc.
        'signal_type': str,       # oversold, bullish_cross, etc.
        'strength': float,        # 0.0-1.0
        'value': dict,
        'confidence': float,
        'price': float,
        'volume': float
    },
    'category': str,              # strong_reversal, breakout, etc.
    'priority': int,              # 1-5
    'action': str,                # strong_buy, buy, hold, sell, etc.
    'confidence': float,
    'risk_score': float,
    'reward_score': float,
    'risk_reward_ratio': float,
    'timestamp': float,
    'metadata': dict
}
```

### Pattern Update (MessagePack)

```python
{
    'pid': str,
    'timestamp': float,
    'patterns': [
        {
            'type': str,          # head_shoulders, double_bottom, etc.
            'confidence': float,
            'start_idx': int,
            'end_idx': int,
            'support': float,
            'resistance': float,
            'target': float,
            'metadata': dict
        }
    ],
    'count': int
}
```

### Anomaly Alert (JSON)

```json
{
    "pid": "asset-uuid",
    "score": 0.85,
    "types": ["price_spike", "volume_surge"],
    "reasons": ["Price moved 5% in 10 minutes"],
    "timestamp": 1704883200.0,
    "price": 52.50,
    "metadata": {}
}
```

### Trading Recommendation (JSON)

```json
{
    "event": "recommendations_updated",
    "count": 150,
    "by_action": {
        "STRONG_BUY": 5,
        "BUY": 25,
        "ACCUMULATE": 30,
        "HOLD": 50,
        "REDUCE": 20,
        "SELL": 15,
        "STRONG_SELL": 3,
        "AVOID": 2
    },
    "urgent_count": 8,
    "timestamp": "2026-01-10T10:45:00+02:00"
}
```

---

## Testing

### Manual Testing

```bash
# Start listener in dry-run mode
php artisan alerts:listen --dry-run

# In another terminal, publish test message
redis-cli PUBLISH classified_high '{"pid": "test-asset-id", "priority": 2}'
```

### Integration Test

```php
<?php

namespace Tests\Feature\Alerts;

use App\Console\Commands\AlertsListen;
use App\Jobs\Alerts\ProcessIntelligenceAlerts;
use App\Models\Alert;
use App\Models\Asset;
use App\Services\AlertCacheService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AlertsListenTest extends TestCase
{
    /** @test */
    public function it_dispatches_job_when_alert_exists_for_asset(): void
    {
        Queue::fake();

        $asset = Asset::factory()->create();
        Alert::factory()->create([
            'asset_id' => $asset->id,
            'type' => 'signal',
            'status' => 'active',
        ]);

        // Warm up cache
        app(AlertCacheService::class)->cacheActiveAlerts();

        // Simulate message processing
        $command = app(AlertsListen::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        $message = json_encode(['pid' => $asset->id, 'priority' => 2]);
        $method->invoke($command, 'classified_high', $message);

        Queue::assertPushed(ProcessIntelligenceAlerts::class, function ($job) use ($asset) {
            return $job->assetId === $asset->id;
        });
    }

    /** @test */
    public function it_skips_processing_when_no_alert_exists(): void
    {
        Queue::fake();

        $asset = Asset::factory()->create();
        // No alerts created

        app(AlertCacheService::class)->cacheActiveAlerts();

        $command = app(AlertsListen::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('processMessage');
        $method->setAccessible(true);

        $message = json_encode(['pid' => $asset->id, 'priority' => 2]);
        $method->invoke($command, 'classified_high', $message);

        Queue::assertNotPushed(ProcessIntelligenceAlerts::class);
    }
}
```

---

## Monitoring Commands

```bash
# Check listener status
sudo supervisorctl status alerts-listener

# View logs
tail -f storage/logs/alerts-listener.log

# Run health check
php artisan alerts:health-check

# Check queue depth
php artisan queue:monitor alerts --max=1000
```

---

## Troubleshooting

### Listener Not Starting

1. Check Redis connection:
   ```bash
   redis-cli ping
   ```

2. Check PHP extensions:
   ```bash
   php -m | grep -E "(redis|msgpack)"
   ```

3. Check logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Messages Not Processing

1. Verify channels in Redis:
   ```bash
   redis-cli PUBSUB CHANNELS
   ```

2. Check if alerts exist:
   ```bash
   php artisan tinker
   >>> Alert::where('status', 'active')->count()
   ```

3. Check cache:
   ```bash
   redis-cli SMEMBERS active_alert_assets
   ```

---

## Next Task

Proceed to [Task 05: Notification System](./05-notifications.md)
