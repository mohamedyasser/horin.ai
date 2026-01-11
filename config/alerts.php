<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data Retention Settings
    |--------------------------------------------------------------------------
    |
    | Configure how long to keep historical data before cleanup jobs remove it.
    |
    */
    'retention' => [
        'alert_history_days' => env('ALERT_HISTORY_RETENTION_DAYS', 90),
        'notifications_days' => env('NOTIFICATIONS_RETENTION_DAYS', 30),
        'backtest_results_days' => env('BACKTEST_RETENTION_DAYS', 7),
        'failed_notifications_days' => env('FAILED_NOTIFICATIONS_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for the health check command to determine system health.
    |
    */
    'health' => [
        'max_queue_depth' => env('ALERT_MAX_QUEUE_DEPTH', 1000),
        'max_failure_rate' => env('ALERT_MAX_FAILURE_RATE', 0.05),
        'max_p99_latency_ms' => env('ALERT_MAX_P99_LATENCY', 5000),
        'subscriber_timeout_seconds' => env('ALERT_SUBSCRIBER_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ops Alerting
    |--------------------------------------------------------------------------
    |
    | Configuration for ops team alerting when system issues are detected.
    |
    */
    'ops' => [
        'slack_webhook' => env('ALERT_OPS_SLACK_WEBHOOK'),
        'telegram_chat_id' => env('ALERT_OPS_TELEGRAM_CHAT_ID'),
        'email' => env('ALERT_OPS_EMAIL'),
        'metrics_token' => env('METRICS_TOKEN'),
        'metrics_allowed_ips' => array_filter(explode(',', env('METRICS_ALLOWED_IPS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | These limits control how many alert notifications a user can receive
    | within specific time windows to prevent notification fatigue.
    |
    */

    'rate_limits' => [
        'per_user_per_hour' => env('ALERT_RATE_LIMIT_HOURLY', 10),
        'per_user_per_day' => env('ALERT_RATE_LIMIT_DAILY', 50),
        'per_alert_per_day' => env('ALERT_RATE_LIMIT_PER_ALERT', 3),

        // Channel-specific limits
        'telegram_per_hour' => env('TELEGRAM_RATE_LIMIT_HOURLY', 20),
        'push_per_hour' => env('PUSH_RATE_LIMIT_HOURLY', 30),
        'sms_per_hour' => env('SMS_RATE_LIMIT_HOURLY', 5),
        'email_per_hour' => env('EMAIL_RATE_LIMIT_HOURLY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Limits
    |--------------------------------------------------------------------------
    |
    | Maximum number of alerts a user can create.
    |
    */

    'user_limits' => [
        'max_active_alerts' => env('MAX_ACTIVE_ALERTS_PER_USER', 50),
        'max_alerts_per_asset' => env('MAX_ALERTS_PER_ASSET', 5),
        'max_alerts_per_type' => env('MAX_ALERTS_PER_TYPE', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Escalation Configuration
    |--------------------------------------------------------------------------
    |
    | Default escalation behavior for unacknowledged critical alerts.
    |
    */

    'escalation' => [
        'enabled' => env('ALERT_ESCALATION_ENABLED', true),

        'default_levels' => [
            [
                'level' => 1,
                'channel' => 'telegram',
                'delay_minutes' => 5,
                'condition' => 'not_acknowledged',
            ],
            [
                'level' => 2,
                'channel' => 'sms',
                'delay_minutes' => 15,
                'condition' => 'not_acknowledged',
            ],
        ],

        'max_escalations' => env('MAX_ESCALATION_LEVELS', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quiet Hours
    |--------------------------------------------------------------------------
    |
    | Default quiet hours during which non-critical notifications are held.
    |
    */

    'quiet_hours' => [
        'default_start' => '23:00',
        'default_end' => '07:00',
        'default_timezone' => 'Africa/Cairo',
        'allow_critical' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Market Hours
    |--------------------------------------------------------------------------
    |
    | Egypt Stock Exchange trading hours.
    |
    */

    'market_hours' => [
        'timezone' => 'Africa/Cairo',
        'open' => '10:00',
        'close' => '14:30',
        'weekend_days' => [5, 6], // Friday and Saturday
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Cache TTL and key prefixes for alert caching.
    |
    */

    'cache' => [
        'ttl_seconds' => env('ALERT_CACHE_TTL', 60),
        'prefix' => 'alerts',
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | Available notification channels and their priority order.
    |
    */

    'channels' => [
        'available' => ['telegram', 'push', 'in_app', 'email', 'sms'],

        'default' => ['telegram', 'in_app'],

        'priority_mapping' => [
            'critical' => ['telegram', 'push', 'sms', 'in_app'],
            'high' => ['telegram', 'push', 'in_app'],
            'medium' => ['telegram', 'in_app'],
            'low' => ['in_app'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Digest Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for daily and weekly digest notifications.
    |
    */

    'digest' => [
        'daily_time' => '20:00', // 8 PM Cairo time
        'weekly_day' => 4, // Thursday (before Egypt weekend)
        'weekly_time' => '15:00', // 3 PM Cairo time
        'timezone' => 'Africa/Cairo',
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert Types Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different alert types.
    |
    */

    'types' => [
        'price' => [
            'trigger_types' => [
                'target_price',
                'breakout',
                'zone',
                'daily_change',
                '52week',
                'gap',
                'entry_return',
            ],
            'default_cooldown_minutes' => 240, // 4 hours
        ],

        'signal' => [
            'trigger_types' => ['signal'],
            'default_cooldown_minutes' => 60,
        ],

        'anomaly' => [
            'trigger_types' => ['anomaly'],
            'default_cooldown_minutes' => 120,
        ],

        'pattern' => [
            'trigger_types' => ['pattern'],
            'default_cooldown_minutes' => 480, // 8 hours
        ],

        'recommendation' => [
            'trigger_types' => ['recommendation'],
            'default_cooldown_minutes' => 1440, // 24 hours
        ],

        'prediction' => [
            'trigger_types' => ['prediction'],
            'default_cooldown_minutes' => 720, // 12 hours
        ],
    ],

];
