# Task 09: Operations & Monitoring

**Priority:** P2
**Effort:** 2 days
**Dependencies:** Tasks 01-05

---

## Objective

Set up operational tooling including metrics collection, health checks, data retention cleanup, admin dashboard, and alerting for the ops team.

---

## Checklist

- [ ] Create AlertMetricsService for metrics collection
- [ ] Create health check command
- [ ] Create data cleanup jobs
- [ ] Create admin statistics endpoint
- [ ] Set up internal ops alerts
- [ ] Create Grafana dashboard (optional)
- [ ] Document runbooks
- [ ] Set up log aggregation

---

## AlertMetricsService

```php
<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AlertMetricsService
{
    private const METRICS_PREFIX = 'metrics:alerts:';

    /**
     * Record alert triggered metric
     */
    public function recordAlertTriggered(Alert $alert): void
    {
        $this->incrementCounter('triggered.total', [
            'type' => $alert->type,
            'trigger_type' => $alert->trigger_type,
            'priority' => $alert->priority,
        ]);

        $this->incrementCounter("triggered.by_type.{$alert->type}");
        $this->incrementCounter("triggered.by_priority.{$alert->priority}");
    }

    /**
     * Record notification sent metric
     */
    public function recordNotificationSent(AlertNotification $notification): void
    {
        $this->incrementCounter('notifications.sent.total', [
            'channel' => $notification->channel,
            'priority' => $notification->priority,
        ]);

        $this->incrementCounter("notifications.sent.by_channel.{$notification->channel}");
    }

    /**
     * Record notification failed metric
     */
    public function recordNotificationFailed(AlertNotification $notification, string $reason): void
    {
        $this->incrementCounter('notifications.failed.total', [
            'channel' => $notification->channel,
            'reason' => $this->normalizeReason($reason),
        ]);

        Log::warning('Notification failed', [
            'notification_id' => $notification->id,
            'channel' => $notification->channel,
            'reason' => $reason,
        ]);
    }

    /**
     * Record processing latency
     */
    public function recordProcessingLatency(string $type, float $durationMs): void
    {
        $bucket = $this->getLatencyBucket($durationMs);

        $this->incrementCounter("processing.latency.{$type}.{$bucket}");

        // Store for percentile calculation
        Redis::lpush(self::METRICS_PREFIX . "latency:{$type}", $durationMs);
        Redis::ltrim(self::METRICS_PREFIX . "latency:{$type}", 0, 999); // Keep last 1000
    }

    /**
     * Get current metrics snapshot
     */
    public function getMetricsSnapshot(): array
    {
        $now = now();
        $hourAgo = $now->copy()->subHour();
        $dayAgo = $now->copy()->subDay();

        return [
            'alerts' => [
                'active_count' => Alert::where('status', 'active')->count(),
                'triggered_last_hour' => AlertHistory::where('triggered_at', '>=', $hourAgo)->count(),
                'triggered_last_day' => AlertHistory::where('triggered_at', '>=', $dayAgo)->count(),
                'by_type' => Alert::where('status', 'active')
                    ->select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'by_status' => Alert::select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status'),
            ],
            'notifications' => [
                'sent_last_hour' => AlertNotification::where('sent_at', '>=', $hourAgo)->count(),
                'sent_last_day' => AlertNotification::where('sent_at', '>=', $dayAgo)->count(),
                'pending' => AlertNotification::where('status', 'pending')->count(),
                'failed_last_hour' => AlertNotification::where('status', 'failed')
                    ->where('created_at', '>=', $hourAgo)
                    ->count(),
                'by_channel' => AlertNotification::where('sent_at', '>=', $dayAgo)
                    ->select('channel', DB::raw('count(*) as count'))
                    ->groupBy('channel')
                    ->pluck('count', 'channel'),
            ],
            'performance' => [
                'p50_latency_ms' => $this->getPercentileLatency('price', 50),
                'p95_latency_ms' => $this->getPercentileLatency('price', 95),
                'p99_latency_ms' => $this->getPercentileLatency('price', 99),
            ],
            'queue' => [
                'alerts_depth' => $this->getQueueDepth('alerts'),
                'notifications_depth' => $this->getQueueDepth('notifications'),
            ],
            'redis_subscriber' => [
                'connected' => $this->isRedisSubscriberConnected(),
                'last_message_at' => Cache::get('alerts:last_message_at'),
            ],
        ];
    }

    /**
     * Get failure rate for a period
     */
    public function getNotificationFailureRate(int $minutes = 60): float
    {
        $since = now()->subMinutes($minutes);

        $total = AlertNotification::where('created_at', '>=', $since)->count();
        $failed = AlertNotification::where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        return $failed / $total;
    }

    /**
     * Get percentile latency
     */
    public function getPercentileLatency(string $type, int $percentile): ?float
    {
        $key = self::METRICS_PREFIX . "latency:{$type}";
        $values = Redis::lrange($key, 0, -1);

        if (empty($values)) {
            return null;
        }

        $values = array_map('floatval', $values);
        sort($values);

        $index = ceil(count($values) * ($percentile / 100)) - 1;
        return $values[$index] ?? null;
    }

    private function incrementCounter(string $metric, array $labels = []): void
    {
        $key = self::METRICS_PREFIX . "counter:{$metric}";

        if (!empty($labels)) {
            $labelString = http_build_query($labels);
            $key .= ":{$labelString}";
        }

        Redis::incr($key);

        // Also increment hourly bucket for time-series
        $hourKey = $key . ':' . now()->format('Y-m-d-H');
        Redis::incr($hourKey);
        Redis::expire($hourKey, 86400 * 7); // Keep for 7 days
    }

    private function getLatencyBucket(float $ms): string
    {
        if ($ms < 10) return 'lt10';
        if ($ms < 50) return 'lt50';
        if ($ms < 100) return 'lt100';
        if ($ms < 500) return 'lt500';
        if ($ms < 1000) return 'lt1000';
        if ($ms < 5000) return 'lt5000';
        return 'gt5000';
    }

    private function normalizeReason(string $reason): string
    {
        // Normalize error reasons for grouping
        if (str_contains($reason, 'rate limit')) return 'rate_limited';
        if (str_contains($reason, 'timeout')) return 'timeout';
        if (str_contains($reason, 'invalid token')) return 'invalid_token';
        if (str_contains($reason, 'blocked')) return 'blocked';
        return 'other';
    }

    private function getQueueDepth(string $queue): int
    {
        return \Illuminate\Support\Facades\Queue::size($queue);
    }

    private function isRedisSubscriberConnected(): bool
    {
        $lastHeartbeat = Cache::get('alerts:subscriber_heartbeat');

        if (!$lastHeartbeat) {
            return false;
        }

        return now()->diffInSeconds($lastHeartbeat) < 60;
    }
}
```

---

## Health Check Command

```php
<?php

namespace App\Console\Commands;

use App\Services\AlertMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AlertSystemHealthCheck extends Command
{
    protected $signature = 'alerts:health-check
        {--alert : Send alert to ops if issues found}
        {--json : Output as JSON}';

    protected $description = 'Check alert system health and optionally alert ops team';

    private array $issues = [];
    private array $warnings = [];

    public function __construct(
        private readonly AlertMetricsService $metrics
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->checkDatabaseConnection();
        $this->checkRedisConnection();
        $this->checkRedisSubscriber();
        $this->checkQueueDepth();
        $this->checkNotificationFailureRate();
        $this->checkProcessingLatency();
        $this->checkRecentActivity();

        if ($this->option('json')) {
            $this->outputJson();
        } else {
            $this->outputText();
        }

        if ($this->option('alert') && count($this->issues) > 0) {
            $this->alertOpsTeam();
        }

        return count($this->issues) > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function checkDatabaseConnection(): void
    {
        try {
            DB::select('SELECT 1');
            $this->info('Database connection: OK');
        } catch (\Exception $e) {
            $this->addIssue('Database connection failed: ' . $e->getMessage());
        }
    }

    private function checkRedisConnection(): void
    {
        try {
            Redis::ping();
            $this->info('Redis connection: OK');
        } catch (\Exception $e) {
            $this->addIssue('Redis connection failed: ' . $e->getMessage());
        }
    }

    private function checkRedisSubscriber(): void
    {
        $lastHeartbeat = Cache::get('alerts:subscriber_heartbeat');

        if (!$lastHeartbeat) {
            $this->addIssue('Redis subscriber not running (no heartbeat)');
            return;
        }

        $secondsAgo = now()->diffInSeconds($lastHeartbeat);

        if ($secondsAgo > 60) {
            $this->addIssue("Redis subscriber stale (last heartbeat {$secondsAgo}s ago)");
        } elseif ($secondsAgo > 30) {
            $this->addWarning("Redis subscriber delayed (last heartbeat {$secondsAgo}s ago)");
        } else {
            $this->info('Redis subscriber: OK');
        }
    }

    private function checkQueueDepth(): void
    {
        $alertsDepth = Queue::size('alerts');
        $notificationsDepth = Queue::size('notifications');

        if ($alertsDepth > 1000) {
            $this->addIssue("Alerts queue depth critical: {$alertsDepth}");
        } elseif ($alertsDepth > 500) {
            $this->addWarning("Alerts queue depth elevated: {$alertsDepth}");
        } else {
            $this->info("Alerts queue depth: {$alertsDepth}");
        }

        if ($notificationsDepth > 1000) {
            $this->addIssue("Notifications queue depth critical: {$notificationsDepth}");
        } elseif ($notificationsDepth > 500) {
            $this->addWarning("Notifications queue depth elevated: {$notificationsDepth}");
        } else {
            $this->info("Notifications queue depth: {$notificationsDepth}");
        }
    }

    private function checkNotificationFailureRate(): void
    {
        $failureRate = $this->metrics->getNotificationFailureRate(60);

        if ($failureRate > 0.10) {
            $this->addIssue("Notification failure rate critical: " . number_format($failureRate * 100, 1) . "%");
        } elseif ($failureRate > 0.05) {
            $this->addWarning("Notification failure rate elevated: " . number_format($failureRate * 100, 1) . "%");
        } else {
            $this->info("Notification failure rate: " . number_format($failureRate * 100, 1) . "%");
        }
    }

    private function checkProcessingLatency(): void
    {
        $p99 = $this->metrics->getPercentileLatency('price', 99);

        if ($p99 === null) {
            $this->addWarning("No latency data available");
            return;
        }

        if ($p99 > 5000) {
            $this->addIssue("P99 latency critical: {$p99}ms");
        } elseif ($p99 > 1000) {
            $this->addWarning("P99 latency elevated: {$p99}ms");
        } else {
            $this->info("P99 latency: {$p99}ms");
        }
    }

    private function checkRecentActivity(): void
    {
        $recentAlerts = \App\Models\AlertHistory::where('triggered_at', '>', now()->subHours(24))
            ->count();

        $this->info("Alerts triggered (24h): {$recentAlerts}");

        // Check if suspiciously low during market hours
        if ($this->isMarketHours() && $recentAlerts === 0) {
            $this->addWarning("No alerts triggered during market hours");
        }
    }

    private function isMarketHours(): bool
    {
        $cairo = new \DateTimeZone('Africa/Cairo');
        $now = now()->setTimezone($cairo);

        $dayOfWeek = (int) $now->format('N');
        if ($dayOfWeek >= 6) return false;

        $time = $now->format('H:i');
        return $time >= '10:00' && $time <= '14:30';
    }

    private function addIssue(string $message): void
    {
        $this->issues[] = $message;
        $this->error("ISSUE: {$message}");
    }

    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
        $this->warn("WARNING: {$message}");
    }

    private function outputJson(): void
    {
        $this->line(json_encode([
            'status' => count($this->issues) === 0 ? 'healthy' : 'unhealthy',
            'issues' => $this->issues,
            'warnings' => $this->warnings,
            'timestamp' => now()->toISOString(),
        ], JSON_PRETTY_PRINT));
    }

    private function outputText(): void
    {
        $this->newLine();

        if (count($this->issues) === 0 && count($this->warnings) === 0) {
            $this->info('All systems healthy!');
        } else {
            if (count($this->issues) > 0) {
                $this->error("Found " . count($this->issues) . " issue(s)");
            }
            if (count($this->warnings) > 0) {
                $this->warn("Found " . count($this->warnings) . " warning(s)");
            }
        }
    }

    private function alertOpsTeam(): void
    {
        $message = "Alert System Issues:\n\n";
        foreach ($this->issues as $issue) {
            $message .= "- {$issue}\n";
        }

        // Send to ops Telegram channel or Slack
        // For now, just log
        \Illuminate\Support\Facades\Log::critical('Alert system health check failed', [
            'issues' => $this->issues,
            'warnings' => $this->warnings,
        ]);

        // Could also send to Telegram ops channel
        // app(TelegramBotService::class)->sendToOpsChannel($message);
    }
}
```

---

## Data Cleanup Jobs

### CleanupAlertHistory Job

```php
<?php

namespace App\Jobs;

use App\Models\AlertHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupAlertHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $retentionDays = 90
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->retentionDays);

        $deleted = AlertHistory::where('triggered_at', '<', $cutoff)
            ->delete();

        Log::info("Cleaned up {$deleted} alert history records", [
            'retention_days' => $this->retentionDays,
            'cutoff_date' => $cutoff->toDateString(),
        ]);
    }
}
```

### CleanupNotifications Job

```php
<?php

namespace App\Jobs;

use App\Models\AlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $retentionDays = 30
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->retentionDays);

        // Only delete read or failed notifications
        $deleted = AlertNotification::where('created_at', '<', $cutoff)
            ->whereIn('status', ['read', 'failed'])
            ->delete();

        Log::info("Cleaned up {$deleted} notification records", [
            'retention_days' => $this->retentionDays,
            'cutoff_date' => $cutoff->toDateString(),
        ]);
    }
}
```

### CleanupBacktestResults Job

```php
<?php

namespace App\Jobs;

use App\Models\AlertBacktestResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupBacktestResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $retentionDays = 7
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->retentionDays);

        $deleted = AlertBacktestResult::where('completed_at', '<', $cutoff)
            ->delete();

        Log::info("Cleaned up {$deleted} backtest result records", [
            'retention_days' => $this->retentionDays,
            'cutoff_date' => $cutoff->toDateString(),
        ]);
    }
}
```

### Cleanup Schedule

Add to `routes/console.php`:

```php
use App\Jobs\CleanupAlertHistory;
use App\Jobs\CleanupNotifications;
use App\Jobs\CleanupBacktestResults;
use Illuminate\Support\Facades\Schedule;

// Daily cleanup at 3 AM Cairo time
Schedule::job(new CleanupAlertHistory(days: 90))
    ->dailyAt('03:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping();

Schedule::job(new CleanupNotifications(days: 30))
    ->dailyAt('03:15')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping();

Schedule::job(new CleanupBacktestResults(days: 7))
    ->dailyAt('03:30')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping();

// Health check every 5 minutes
Schedule::command('alerts:health-check --alert')
    ->everyFiveMinutes()
    ->withoutOverlapping();
```

---

## Admin Statistics Endpoint

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AlertMetricsService;
use Illuminate\Http\JsonResponse;

class AlertStatisticsController extends Controller
{
    public function __construct(
        private readonly AlertMetricsService $metrics
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->metrics->getMetricsSnapshot());
    }

    public function health(): JsonResponse
    {
        $metrics = $this->metrics->getMetricsSnapshot();

        $status = 'healthy';
        $issues = [];

        // Check critical thresholds
        if (!$metrics['redis_subscriber']['connected']) {
            $status = 'unhealthy';
            $issues[] = 'Redis subscriber not connected';
        }

        if ($metrics['queue']['alerts_depth'] > 1000) {
            $status = 'unhealthy';
            $issues[] = 'Alert queue depth critical';
        }

        if ($metrics['performance']['p99_latency_ms'] > 5000) {
            $status = 'degraded';
            $issues[] = 'High processing latency';
        }

        $failureRate = $metrics['notifications']['failed_last_hour'] /
            max($metrics['notifications']['sent_last_hour'], 1);

        if ($failureRate > 0.10) {
            $status = 'degraded';
            $issues[] = 'High notification failure rate';
        }

        return response()->json([
            'status' => $status,
            'issues' => $issues,
            'metrics' => $metrics,
            'checked_at' => now()->toISOString(),
        ]);
    }
}
```

### Admin Routes

```php
// routes/web.php (admin section)

Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('alerts/statistics', [AlertStatisticsController::class, 'index'])
        ->name('admin.alerts.statistics');

    Route::get('alerts/health', [AlertStatisticsController::class, 'health'])
        ->name('admin.alerts.health');
});
```

---

## Internal Ops Alerts

### OpsAlertService

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class OpsAlertService
{
    private string $slackWebhook;
    private string $opsTelegramChatId;

    public function __construct()
    {
        $this->slackWebhook = config('services.slack.ops_webhook');
        $this->opsTelegramChatId = config('services.telegram.ops_chat_id');
    }

    public function alert(string $title, string $message, string $severity = 'warning'): void
    {
        $emoji = match ($severity) {
            'critical' => '🚨',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '🔔',
        };

        // Log first
        $logLevel = $severity === 'critical' ? 'critical' : ($severity === 'warning' ? 'warning' : 'info');
        Log::$logLevel("[OPS ALERT] {$title}", ['message' => $message]);

        // Send to Slack
        if ($this->slackWebhook) {
            $this->sendSlack("{$emoji} *{$title}*\n{$message}");
        }

        // Send to Telegram ops channel
        if ($this->opsTelegramChatId) {
            $this->sendTelegram("{$emoji} *{$title}*\n\n{$message}");
        }
    }

    public function alertIfThreshold(string $metric, float $value, float $threshold, string $title): void
    {
        if ($value > $threshold) {
            $this->alert(
                $title,
                "Current: {$value}, Threshold: {$threshold}",
                'warning'
            );
        }
    }

    private function sendSlack(string $message): void
    {
        try {
            Http::post($this->slackWebhook, [
                'text' => $message,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Slack ops alert', ['error' => $e->getMessage()]);
        }
    }

    private function sendTelegram(string $message): void
    {
        try {
            app(\App\Services\TelegramBotService::class)->sendMessage(
                $this->opsTelegramChatId,
                $message,
                ['parse_mode' => 'Markdown']
            );
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram ops alert', ['error' => $e->getMessage()]);
        }
    }
}
```

---

## Prometheus Metrics Export (Optional)

```php
<?php

namespace App\Http\Controllers;

use App\Services\AlertMetricsService;
use Illuminate\Http\Response;

class PrometheusMetricsController extends Controller
{
    public function __construct(
        private readonly AlertMetricsService $metrics
    ) {}

    public function __invoke(): Response
    {
        $data = $this->metrics->getMetricsSnapshot();

        $output = [];

        // Gauges
        $output[] = "# HELP kira_alerts_active_total Number of active alerts";
        $output[] = "# TYPE kira_alerts_active_total gauge";
        $output[] = "kira_alerts_active_total {$data['alerts']['active_count']}";

        $output[] = "# HELP kira_alerts_triggered_last_hour Alerts triggered in last hour";
        $output[] = "# TYPE kira_alerts_triggered_last_hour gauge";
        $output[] = "kira_alerts_triggered_last_hour {$data['alerts']['triggered_last_hour']}";

        $output[] = "# HELP kira_notifications_pending_total Pending notifications";
        $output[] = "# TYPE kira_notifications_pending_total gauge";
        $output[] = "kira_notifications_pending_total {$data['notifications']['pending']}";

        $output[] = "# HELP kira_queue_depth Queue depth by queue name";
        $output[] = "# TYPE kira_queue_depth gauge";
        $output[] = "kira_queue_depth{queue=\"alerts\"} {$data['queue']['alerts_depth']}";
        $output[] = "kira_queue_depth{queue=\"notifications\"} {$data['queue']['notifications_depth']}";

        // Latency
        if ($data['performance']['p50_latency_ms']) {
            $output[] = "# HELP kira_processing_latency_ms Processing latency percentiles";
            $output[] = "# TYPE kira_processing_latency_ms gauge";
            $output[] = "kira_processing_latency_ms{quantile=\"0.5\"} {$data['performance']['p50_latency_ms']}";
            $output[] = "kira_processing_latency_ms{quantile=\"0.95\"} {$data['performance']['p95_latency_ms']}";
            $output[] = "kira_processing_latency_ms{quantile=\"0.99\"} {$data['performance']['p99_latency_ms']}";
        }

        // Redis subscriber
        $subscriberConnected = $data['redis_subscriber']['connected'] ? 1 : 0;
        $output[] = "# HELP kira_redis_subscriber_connected Redis subscriber connection status";
        $output[] = "# TYPE kira_redis_subscriber_connected gauge";
        $output[] = "kira_redis_subscriber_connected {$subscriberConnected}";

        return response(implode("\n", $output), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
```

Add route:

```php
Route::get('/metrics', PrometheusMetricsController::class);
```

---

## Grafana Dashboard JSON (Sample)

```json
{
  "title": "Kira Alert System",
  "panels": [
    {
      "title": "Active Alerts",
      "type": "stat",
      "targets": [
        {
          "expr": "kira_alerts_active_total",
          "legendFormat": "Active"
        }
      ]
    },
    {
      "title": "Alerts Triggered (1h)",
      "type": "graph",
      "targets": [
        {
          "expr": "rate(kira_alerts_triggered_last_hour[5m])",
          "legendFormat": "Rate"
        }
      ]
    },
    {
      "title": "Processing Latency",
      "type": "graph",
      "targets": [
        {
          "expr": "kira_processing_latency_ms{quantile=\"0.5\"}",
          "legendFormat": "p50"
        },
        {
          "expr": "kira_processing_latency_ms{quantile=\"0.95\"}",
          "legendFormat": "p95"
        },
        {
          "expr": "kira_processing_latency_ms{quantile=\"0.99\"}",
          "legendFormat": "p99"
        }
      ]
    },
    {
      "title": "Queue Depth",
      "type": "graph",
      "targets": [
        {
          "expr": "kira_queue_depth",
          "legendFormat": "{{queue}}"
        }
      ]
    },
    {
      "title": "Redis Subscriber Status",
      "type": "stat",
      "targets": [
        {
          "expr": "kira_redis_subscriber_connected",
          "legendFormat": "Connected"
        }
      ],
      "options": {
        "colorMode": "background",
        "thresholds": {
          "mode": "absolute",
          "steps": [
            { "color": "red", "value": 0 },
            { "color": "green", "value": 1 }
          ]
        }
      }
    }
  ]
}
```

---

## Runbook Documentation

Create `docs/runbooks/alert-system.md`:

```markdown
# Alert System Runbook

## Quick Health Check

```bash
php artisan alerts:health-check
```

## Common Issues and Solutions

### Redis Subscriber Not Running

**Symptoms:**
- No new alerts triggering
- Health check shows subscriber disconnected

**Solution:**
```bash
# Check process
ps aux | grep "alerts:listen"

# Restart via supervisor
sudo supervisorctl restart alerts-listener

# Check logs
tail -f storage/logs/alerts-listener.log
```

### High Queue Depth

**Symptoms:**
- Delayed notifications
- Queue depth > 500

**Solution:**
```bash
# Check queue workers
php artisan queue:monitor alerts,notifications

# Scale workers
sudo supervisorctl start alerts-worker:*

# Clear stuck jobs (caution!)
php artisan queue:clear alerts
```

### High Notification Failure Rate

**Symptoms:**
- Failure rate > 5%
- Users not receiving notifications

**Solution:**
1. Check Telegram API status
2. Review failed_notifications table
3. Check rate limits

```bash
php artisan tinker
>>> FailedNotification::latest()->take(10)->get()
```

### High Processing Latency

**Symptoms:**
- P99 latency > 1000ms
- Delayed alert triggers

**Solution:**
1. Check database query performance
2. Review cache hit rate
3. Scale Redis

```bash
# Check slow queries
tail -f storage/logs/laravel.log | grep -i slow

# Warm cache
php artisan alerts:warm-cache
```

## Scaling Procedures

### Adding Queue Workers

```bash
# Edit supervisor config
sudo vim /etc/supervisor/conf.d/alerts-worker.conf

# Increase numprocs
numprocs=4

# Reload
sudo supervisorctl reread
sudo supervisorctl update
```

### Database Optimization

```sql
-- Check index usage
SELECT * FROM pg_stat_user_indexes WHERE relname = 'alerts';

-- Analyze table
ANALYZE alerts;
```

## Emergency Procedures

### Stop All Alert Processing

```bash
php artisan down --message="Alert system maintenance"
sudo supervisorctl stop alerts-listener:*
sudo supervisorctl stop alerts-worker:*
```

### Resume Processing

```bash
sudo supervisorctl start alerts-listener:*
sudo supervisorctl start alerts-worker:*
php artisan up
```
```

---

## Configuration Reference

Add to `config/alerts.php`:

```php
<?php

return [
    // Data retention
    'retention' => [
        'alert_history_days' => env('ALERT_HISTORY_RETENTION_DAYS', 90),
        'notifications_days' => env('NOTIFICATIONS_RETENTION_DAYS', 30),
        'backtest_results_days' => env('BACKTEST_RETENTION_DAYS', 7),
        'failed_notifications_days' => env('FAILED_NOTIFICATIONS_RETENTION_DAYS', 30),
    ],

    // Health check thresholds
    'health' => [
        'max_queue_depth' => env('ALERT_MAX_QUEUE_DEPTH', 1000),
        'max_failure_rate' => env('ALERT_MAX_FAILURE_RATE', 0.05),
        'max_p99_latency_ms' => env('ALERT_MAX_P99_LATENCY', 5000),
        'subscriber_timeout_seconds' => env('ALERT_SUBSCRIBER_TIMEOUT', 60),
    ],

    // Ops alerting
    'ops' => [
        'slack_webhook' => env('ALERT_OPS_SLACK_WEBHOOK'),
        'telegram_chat_id' => env('ALERT_OPS_TELEGRAM_CHAT_ID'),
        'email' => env('ALERT_OPS_EMAIL'),
    ],
];
```

---

## Verification Checklist

After implementation, verify:

```bash
# Health check passes
php artisan alerts:health-check

# Cleanup jobs work
php artisan queue:work --queue=default --once

# Metrics endpoint responds
curl http://localhost/metrics

# Admin statistics work
curl -H "Authorization: Bearer $TOKEN" http://localhost/admin/alerts/statistics
```

---

## Implementation Complete

All 9 tasks have been documented. See [Task Overview](./00-kira-alerts-overview.md) for the complete task index and implementation order.
