<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;
use App\Services\AlertCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AlertsHealthCheck extends Command
{
    protected $signature = 'alerts:health-check
        {--json : Output results as JSON}';

    protected $description = 'Check alert system health and report status';

    private array $issues = [];

    private array $stats = [];

    public function handle(): int
    {
        $this->info('Running Alert System Health Check...');
        $this->newLine();

        // Run all checks
        $this->checkRedisSubscriber();
        $this->checkRedisConnection();
        $this->checkQueueDepth();
        $this->checkAlertCache();
        $this->checkRecentActivity();
        $this->checkNotificationDelivery();

        // Output results
        if ($this->option('json')) {
            $this->outputJson();
        } else {
            $this->outputSummary();
        }

        return count($this->issues) > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function checkRedisSubscriber(): void
    {
        $this->task('Redis Subscriber Running', function () {
            $running = $this->isRedisSubscriberRunning();
            if (! $running) {
                $this->issues[] = 'Redis subscriber process not running';
            }
            $this->stats['subscriber_running'] = $running;

            return $running;
        });
    }

    private function checkRedisConnection(): void
    {
        $this->task('Redis Connection', function () {
            try {
                Redis::ping();
                $this->stats['redis_connected'] = true;

                return true;
            } catch (\Exception $e) {
                $this->issues[] = 'Redis connection failed: '.$e->getMessage();
                $this->stats['redis_connected'] = false;

                return false;
            }
        });
    }

    private function checkQueueDepth(): void
    {
        $this->task('Queue Depth', function () {
            try {
                $queueDepth = Queue::size('alerts');
                $this->stats['queue_depth'] = $queueDepth;

                if ($queueDepth > 1000) {
                    $this->issues[] = "Alert queue depth high: {$queueDepth}";

                    return false;
                }

                return true;
            } catch (\Exception $e) {
                $this->stats['queue_depth'] = -1;

                return true; // Don't fail health check if queue check fails
            }
        });
    }

    private function checkAlertCache(): void
    {
        $this->task('Alert Cache', function () {
            try {
                $cacheService = app(AlertCacheService::class);
                $stats = $cacheService->getStats();

                $this->stats['cached_assets'] = $stats['assets_with_alerts'];
                $this->stats['cache_ttl'] = $stats['cache_ttl_seconds'];

                return true;
            } catch (\Exception $e) {
                $this->issues[] = 'Alert cache error: '.$e->getMessage();

                return false;
            }
        });
    }

    private function checkRecentActivity(): void
    {
        $this->task('Recent Activity (24h)', function () {
            $triggeredCount = AlertHistory::where('triggered_at', '>', now()->subHours(24))->count();
            $activeAlerts = Alert::where('status', 'active')->count();

            $this->stats['triggered_24h'] = $triggeredCount;
            $this->stats['active_alerts'] = $activeAlerts;

            $this->line("    Triggered: {$triggeredCount}, Active: {$activeAlerts}");

            return true;
        });
    }

    private function checkNotificationDelivery(): void
    {
        $this->task('Notification Delivery', function () {
            $pending = AlertNotification::where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(5))
                ->count();

            $failed = AlertNotification::where('status', 'failed')
                ->where('created_at', '>', now()->subHours(24))
                ->count();

            $this->stats['pending_notifications'] = $pending;
            $this->stats['failed_notifications_24h'] = $failed;

            if ($pending > 100) {
                $this->issues[] = "Many pending notifications: {$pending}";

                return false;
            }

            if ($failed > 50) {
                $this->issues[] = "Many failed notifications in 24h: {$failed}";

                return false;
            }

            $this->line("    Pending: {$pending}, Failed (24h): {$failed}");

            return true;
        });
    }

    private function isRedisSubscriberRunning(): bool
    {
        // Check if the alerts:listen process is running
        $output = shell_exec('ps aux | grep "alerts:listen" | grep -v grep');

        return ! empty(trim($output ?? ''));
    }

    private function task(string $name, callable $callback): void
    {
        $this->output->write("  {$name}... ");

        try {
            $result = $callback();
            $this->output->writeln($result ? '<info>OK</info>' : '<error>FAIL</error>');
        } catch (\Exception $e) {
            $this->output->writeln('<error>ERROR</error>');
            $this->issues[] = "{$name} error: ".$e->getMessage();
        }
    }

    private function outputSummary(): void
    {
        $this->newLine();

        if (count($this->issues) > 0) {
            $this->error('Issues found:');
            foreach ($this->issues as $issue) {
                $this->line("  - {$issue}");
            }
            $this->newLine();
        }

        $this->info('Statistics:');
        foreach ($this->stats as $key => $value) {
            $label = str_replace('_', ' ', ucfirst($key));
            $this->line("  {$label}: {$value}");
        }
        $this->newLine();

        if (count($this->issues) === 0) {
            $this->info('All checks passed!');
        }
    }

    private function outputJson(): void
    {
        $output = [
            'healthy' => count($this->issues) === 0,
            'timestamp' => now()->toIso8601String(),
            'issues' => $this->issues,
            'stats' => $this->stats,
        ];

        $this->line(json_encode($output, JSON_PRETTY_PRINT));
    }
}
