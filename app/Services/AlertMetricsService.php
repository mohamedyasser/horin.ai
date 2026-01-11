<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertHistory;
use App\Models\AlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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
        Redis::lpush(self::METRICS_PREFIX."latency:{$type}", $durationMs);
        Redis::ltrim(self::METRICS_PREFIX."latency:{$type}", 0, 999); // Keep last 1000
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
        $key = self::METRICS_PREFIX."latency:{$type}";
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
        $key = self::METRICS_PREFIX."counter:{$metric}";

        if (! empty($labels)) {
            $labelString = http_build_query($labels);
            $key .= ":{$labelString}";
        }

        Redis::incr($key);

        // Also increment hourly bucket for time-series
        $hourKey = $key.':'.now()->format('Y-m-d-H');
        Redis::incr($hourKey);
        Redis::expire($hourKey, 86400 * 7); // Keep for 7 days
    }

    private function getLatencyBucket(float $ms): string
    {
        if ($ms < 10) {
            return 'lt10';
        }
        if ($ms < 50) {
            return 'lt50';
        }
        if ($ms < 100) {
            return 'lt100';
        }
        if ($ms < 500) {
            return 'lt500';
        }
        if ($ms < 1000) {
            return 'lt1000';
        }
        if ($ms < 5000) {
            return 'lt5000';
        }

        return 'gt5000';
    }

    private function normalizeReason(string $reason): string
    {
        // Normalize error reasons for grouping
        if (str_contains($reason, 'rate limit')) {
            return 'rate_limited';
        }
        if (str_contains($reason, 'timeout')) {
            return 'timeout';
        }
        if (str_contains($reason, 'invalid token')) {
            return 'invalid_token';
        }
        if (str_contains($reason, 'blocked')) {
            return 'blocked';
        }

        return 'other';
    }

    private function getQueueDepth(string $queue): int
    {
        return Queue::size($queue);
    }

    private function isRedisSubscriberConnected(): bool
    {
        $lastHeartbeat = Cache::get('alerts:subscriber_heartbeat');

        if (! $lastHeartbeat) {
            return false;
        }

        return now()->diffInSeconds($lastHeartbeat) < 60;
    }
}
