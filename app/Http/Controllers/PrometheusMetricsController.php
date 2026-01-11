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
        $output[] = '# HELP kira_alerts_active_total Number of active alerts';
        $output[] = '# TYPE kira_alerts_active_total gauge';
        $output[] = "kira_alerts_active_total {$data['alerts']['active_count']}";

        $output[] = '# HELP kira_alerts_triggered_last_hour Alerts triggered in last hour';
        $output[] = '# TYPE kira_alerts_triggered_last_hour gauge';
        $output[] = "kira_alerts_triggered_last_hour {$data['alerts']['triggered_last_hour']}";

        $output[] = '# HELP kira_alerts_triggered_last_day Alerts triggered in last 24 hours';
        $output[] = '# TYPE kira_alerts_triggered_last_day gauge';
        $output[] = "kira_alerts_triggered_last_day {$data['alerts']['triggered_last_day']}";

        $output[] = '# HELP kira_notifications_pending_total Pending notifications';
        $output[] = '# TYPE kira_notifications_pending_total gauge';
        $output[] = "kira_notifications_pending_total {$data['notifications']['pending']}";

        $output[] = '# HELP kira_notifications_sent_last_hour Notifications sent in last hour';
        $output[] = '# TYPE kira_notifications_sent_last_hour gauge';
        $output[] = "kira_notifications_sent_last_hour {$data['notifications']['sent_last_hour']}";

        $output[] = '# HELP kira_notifications_failed_last_hour Failed notifications in last hour';
        $output[] = '# TYPE kira_notifications_failed_last_hour gauge';
        $output[] = "kira_notifications_failed_last_hour {$data['notifications']['failed_last_hour']}";

        $output[] = '# HELP kira_queue_depth Queue depth by queue name';
        $output[] = '# TYPE kira_queue_depth gauge';
        $output[] = "kira_queue_depth{queue=\"alerts\"} {$data['queue']['alerts_depth']}";
        $output[] = "kira_queue_depth{queue=\"notifications\"} {$data['queue']['notifications_depth']}";

        // Latency
        if ($data['performance']['p50_latency_ms']) {
            $output[] = '# HELP kira_processing_latency_ms Processing latency percentiles';
            $output[] = '# TYPE kira_processing_latency_ms gauge';
            $output[] = "kira_processing_latency_ms{quantile=\"0.5\"} {$data['performance']['p50_latency_ms']}";
            $output[] = "kira_processing_latency_ms{quantile=\"0.95\"} {$data['performance']['p95_latency_ms']}";
            $output[] = "kira_processing_latency_ms{quantile=\"0.99\"} {$data['performance']['p99_latency_ms']}";
        }

        // Redis subscriber
        $subscriberConnected = $data['redis_subscriber']['connected'] ? 1 : 0;
        $output[] = '# HELP kira_redis_subscriber_connected Redis subscriber connection status';
        $output[] = '# TYPE kira_redis_subscriber_connected gauge';
        $output[] = "kira_redis_subscriber_connected {$subscriberConnected}";

        // Alerts by type
        if (! empty($data['alerts']['by_type'])) {
            $output[] = '# HELP kira_alerts_by_type Active alerts by type';
            $output[] = '# TYPE kira_alerts_by_type gauge';
            foreach ($data['alerts']['by_type'] as $type => $count) {
                $output[] = "kira_alerts_by_type{type=\"{$type}\"} {$count}";
            }
        }

        // Alerts by status
        if (! empty($data['alerts']['by_status'])) {
            $output[] = '# HELP kira_alerts_by_status Alerts by status';
            $output[] = '# TYPE kira_alerts_by_status gauge';
            foreach ($data['alerts']['by_status'] as $status => $count) {
                $output[] = "kira_alerts_by_status{status=\"{$status}\"} {$count}";
            }
        }

        // Notifications by channel
        if (! empty($data['notifications']['by_channel'])) {
            $output[] = '# HELP kira_notifications_by_channel Notifications by channel (last 24h)';
            $output[] = '# TYPE kira_notifications_by_channel gauge';
            foreach ($data['notifications']['by_channel'] as $channel => $count) {
                $output[] = "kira_notifications_by_channel{channel=\"{$channel}\"} {$count}";
            }
        }

        return response(implode("\n", $output), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
