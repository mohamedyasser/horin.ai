<?php

namespace App\Console\Commands;

use App\Models\AlertHistory;
use App\Services\AlertMetricsService;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AlertSystemHealthCheck extends Command
{
    protected $signature = 'alerts:health-check
        {--alert : Send alert to ops if issues found}
        {--json : Output as JSON}';

    protected $description = 'Check alert system health and optionally alert ops team';

    /** @var array<string> */
    private array $issues = [];

    /** @var array<string> */
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
            $this->addIssue('Database connection failed: '.$e->getMessage());
        }
    }

    private function checkRedisConnection(): void
    {
        try {
            Redis::ping();
            $this->info('Redis connection: OK');
        } catch (\Exception $e) {
            $this->addIssue('Redis connection failed: '.$e->getMessage());
        }
    }

    private function checkRedisSubscriber(): void
    {
        $lastHeartbeat = Cache::get('alerts:subscriber_heartbeat');

        if (! $lastHeartbeat) {
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

        $maxQueueDepth = config('alerts.health.max_queue_depth', 1000);
        $warningThreshold = $maxQueueDepth / 2;

        if ($alertsDepth > $maxQueueDepth) {
            $this->addIssue("Alerts queue depth critical: {$alertsDepth}");
        } elseif ($alertsDepth > $warningThreshold) {
            $this->addWarning("Alerts queue depth elevated: {$alertsDepth}");
        } else {
            $this->info("Alerts queue depth: {$alertsDepth}");
        }

        if ($notificationsDepth > $maxQueueDepth) {
            $this->addIssue("Notifications queue depth critical: {$notificationsDepth}");
        } elseif ($notificationsDepth > $warningThreshold) {
            $this->addWarning("Notifications queue depth elevated: {$notificationsDepth}");
        } else {
            $this->info("Notifications queue depth: {$notificationsDepth}");
        }
    }

    private function checkNotificationFailureRate(): void
    {
        $failureRate = $this->metrics->getNotificationFailureRate(60);
        $maxFailureRate = config('alerts.health.max_failure_rate', 0.05);

        if ($failureRate > $maxFailureRate * 2) {
            $this->addIssue('Notification failure rate critical: '.number_format($failureRate * 100, 1).'%');
        } elseif ($failureRate > $maxFailureRate) {
            $this->addWarning('Notification failure rate elevated: '.number_format($failureRate * 100, 1).'%');
        } else {
            $this->info('Notification failure rate: '.number_format($failureRate * 100, 1).'%');
        }
    }

    private function checkProcessingLatency(): void
    {
        $p99 = $this->metrics->getPercentileLatency('price', 99);
        $maxLatency = config('alerts.health.max_p99_latency_ms', 5000);

        if ($p99 === null) {
            $this->addWarning('No latency data available');

            return;
        }

        if ($p99 > $maxLatency) {
            $this->addIssue("P99 latency critical: {$p99}ms");
        } elseif ($p99 > $maxLatency / 5) {
            $this->addWarning("P99 latency elevated: {$p99}ms");
        } else {
            $this->info("P99 latency: {$p99}ms");
        }
    }

    private function checkRecentActivity(): void
    {
        $recentAlerts = AlertHistory::where('triggered_at', '>', now()->subHours(24))
            ->count();

        $this->info("Alerts triggered (24h): {$recentAlerts}");

        // Check if suspiciously low during market hours
        if ($this->isMarketHours() && $recentAlerts === 0) {
            $this->addWarning('No alerts triggered during market hours');
        }
    }

    private function isMarketHours(): bool
    {
        $cairo = new DateTimeZone('Africa/Cairo');
        $now = now()->setTimezone($cairo);

        $dayOfWeek = (int) $now->format('N');
        if ($dayOfWeek >= 6) {
            return false;
        }

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
                $this->error('Found '.count($this->issues).' issue(s)');
            }
            if (count($this->warnings) > 0) {
                $this->warn('Found '.count($this->warnings).' warning(s)');
            }
        }
    }

    private function alertOpsTeam(): void
    {
        $message = "Alert System Issues:\n\n";
        foreach ($this->issues as $issue) {
            $message .= "- {$issue}\n";
        }

        // Log critical for ops
        Log::critical('Alert system health check failed', [
            'issues' => $this->issues,
            'warnings' => $this->warnings,
        ]);

        // Could also send to ops service
        // app(OpsAlertService::class)->alert('Health Check Failed', $message, 'critical');
    }
}
