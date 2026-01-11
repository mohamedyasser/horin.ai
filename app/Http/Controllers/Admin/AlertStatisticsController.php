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
        if (! $metrics['redis_subscriber']['connected']) {
            $status = 'unhealthy';
            $issues[] = 'Redis subscriber not connected';
        }

        $maxQueueDepth = config('alerts.health.max_queue_depth', 1000);
        if ($metrics['queue']['alerts_depth'] > $maxQueueDepth) {
            $status = 'unhealthy';
            $issues[] = 'Alert queue depth critical';
        }

        $maxLatency = config('alerts.health.max_p99_latency_ms', 5000);
        if ($metrics['performance']['p99_latency_ms'] && $metrics['performance']['p99_latency_ms'] > $maxLatency) {
            $status = 'degraded';
            $issues[] = 'High processing latency';
        }

        $sentLastHour = $metrics['notifications']['sent_last_hour'];
        $failedLastHour = $metrics['notifications']['failed_last_hour'];
        $failureRate = $sentLastHour > 0 ? $failedLastHour / $sentLastHour : 0.0;

        $maxFailureRate = config('alerts.health.max_failure_rate', 0.05);
        if ($failureRate > $maxFailureRate * 2) {
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
