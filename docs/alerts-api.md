# Alerts API Documentation

## Overview

The Horin Alert System provides comprehensive price and intelligence-based alerts for trading assets. This document covers the API endpoints, data structures, and integration patterns.

## API Endpoints

### Web Routes (Inertia)

| Method | Route | Controller Method | Description |
|--------|-------|-------------------|-------------|
| GET | `/alerts` | `AlertController@index` | List user alerts with pagination |
| GET | `/alerts/create` | `AlertController@create` | Show alert creation form |
| POST | `/alerts` | `AlertController@store` | Create new alert |
| GET | `/alerts/{alert}` | `AlertController@show` | View alert details |
| GET | `/alerts/{alert}/edit` | `AlertController@edit` | Edit alert form |
| PUT | `/alerts/{alert}` | `AlertController@update` | Update alert |
| DELETE | `/alerts/{alert}` | `AlertController@destroy` | Delete alert |
| POST | `/alerts/{alert}/snooze` | `AlertController@snooze` | Snooze alert |
| POST | `/alerts/{alert}/unsnooze` | `AlertController@unsnooze` | Unsnooze alert |
| POST | `/alerts/{alert}/backtest` | `AlertController@backtest` | Run backtest |
| GET | `/alerts/{alert}/backtest-results` | `AlertController@backtestResults` | Get backtest results |
| POST | `/alerts/{alert}/duplicate` | `AlertController@duplicate` | Duplicate alert |

### API Routes

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/alerts/active-count` | Get count of active alerts |
| GET | `/api/alerts/search-assets` | Search assets for alert creation |

## Data Structures

### Alert Types

```typescript
type AlertType = 'price' | 'prediction' | 'signal' | 'anomaly' | 'pattern' | 'recommendation';
```

### Trigger Types

```typescript
type AlertTriggerType =
    | 'target_price'    // Price reaches target
    | 'breakout'        // Price breaks level
    | 'zone'            // Price enters/exits zone
    | 'gap'             // Gap up/down detected
    | '52week'          // New 52-week high/low
    | 'daily_change'    // Daily % change threshold
    | 'entry_return'    // Return from entry price
    | 'prediction'      // AI prediction trigger
    | 'signal'          // Technical signal trigger
    | 'anomaly'         // Anomaly detection
    | 'pattern'         // Pattern recognition
    | 'recommendation'  // Recommendation change
    | 'compound_intelligence'; // Multiple conditions
```

### Alert Status

```typescript
type AlertStatus = 'active' | 'triggered' | 'paused' | 'expired' | 'chained' | 'deleted';
```

### Direction

```typescript
type AlertDirection = 'above' | 'below' | 'both' | 'cross_up' | 'cross_down';
```

### Priority

```typescript
type AlertPriority = 'critical' | 'high' | 'medium' | 'low';
```

### Scope

```typescript
type AlertScope = 'single_asset' | 'watchlist' | 'portfolio' | 'sector' | 'market';
```

## Alert Parameters by Trigger Type

### target_price
```json
{
    "target_price": 150.00,
    "direction": "above"
}
```

### breakout
```json
{
    "level": 100.00,
    "direction": "above",
    "confirmation": "close"
}
```

### zone
```json
{
    "zone_low": 90.00,
    "zone_high": 110.00,
    "trigger_on": "enter"
}
```

### gap
```json
{
    "gap_threshold_percent": 3.0,
    "direction": "above"
}
```

### 52week
```json
{
    "type": "high",
    "tolerance_percent": 0.5
}
```

### daily_change
```json
{
    "threshold_percent": 5.0,
    "direction": "above"
}
```

### entry_return
```json
{
    "entry_price": 100.00,
    "tolerance_percent": 2.0,
    "direction": "above"
}
```

### signal
```json
{
    "indicators": ["RSI", "MACD"],
    "signal_types": ["oversold", "bullish_crossover"],
    "min_strength": 0.7
}
```

### prediction
```json
{
    "min_confidence": 0.75,
    "direction": "above",
    "horizon": "1d"
}
```

### anomaly
```json
{
    "anomaly_types": ["volume_spike", "price_spike"],
    "min_score": 0.8
}
```

### pattern
```json
{
    "patterns": ["double_bottom", "head_shoulders"],
    "min_confidence": 0.7,
    "pattern_status": "forming"
}
```

### compound_intelligence
```json
{
    "conditions": [
        {"type": "signal", "min_strength": 0.7},
        {"type": "prediction", "min_confidence": 0.8}
    ],
    "any_or_all": "all"
}
```

## Creating Alerts

### Request Body

```json
{
    "asset_id": "uuid",
    "type": "price",
    "trigger_type": "target_price",
    "scope": "single_asset",
    "direction": "above",
    "condition_logic": "single",
    "parameters": {
        "target_price": 150.00
    },
    "priority": "medium",
    "is_recurring": false,
    "cooldown_minutes": 240,
    "max_triggers": null,
    "delivery_config": {
        "channels": ["telegram", "push", "in_app"],
        "sound_enabled": true
    },
    "market_hours_only": true,
    "expires_at": "2026-12-31T23:59:59Z"
}
```

### Response

```json
{
    "id": "uuid",
    "user_id": "uuid",
    "asset": {
        "id": "uuid",
        "symbol": "AAPL",
        "name": "Apple Inc",
        "last_price": 145.50
    },
    "type": "price",
    "trigger_type": "target_price",
    "status": "active",
    "priority": "medium",
    "parameters": {
        "target_price": 150.00
    },
    "triggered_count": 0,
    "created_at": "2026-01-12T10:00:00Z",
    "updated_at": "2026-01-12T10:00:00Z"
}
```

## Alert Chains

Alerts can be chained so that one alert triggers another.

### Chain Configuration

```json
{
    "trigger_alert_id": "uuid",
    "activate_alert_id": "uuid",
    "delay_minutes": 30,
    "expires_after_minutes": 1440,
    "is_active": true
}
```

When `trigger_alert_id` is triggered:
1. If `delay_minutes > 0`, activation is scheduled
2. The `activate_alert_id` alert status changes from `chained` to `active`
3. `chain_from_id` is set on the activated alert
4. Optional expiration is set based on `expires_after_minutes`

## Snooze Functionality

### Snooze Presets

| Preset | Duration |
|--------|----------|
| `1h` | 1 hour |
| `4h` | 4 hours |
| `1d` | 1 day |
| `until_market_close` | Until 14:30 Cairo time |
| `until_market_open` | Until 10:00 Cairo time next trading day |

### Custom Duration

```json
{
    "duration_minutes": 120
}
```

## Backtest API

### Request

```json
{
    "lookback_days": 90,
    "include_ml_signals": false
}
```

### Response

```json
{
    "status": "completed",
    "result": {
        "id": "uuid",
        "alert_id": "uuid",
        "lookback_days": 90,
        "trigger_count": 15,
        "triggers": [
            {
                "date": "2025-10-15",
                "trigger_price": 148.50,
                "performance": {
                    "1d": 2.1,
                    "1w": 5.3,
                    "1m": 8.7
                }
            }
        ],
        "avg_return_1d": 1.8,
        "avg_return_1w": 4.2,
        "avg_return_1m": 7.5,
        "win_rate": 0.73,
        "completed_at": "2026-01-12T10:05:00Z"
    }
}
```

## Delivery Channels

| Channel | Description |
|---------|-------------|
| `telegram` | Telegram bot notification |
| `push` | Mobile push notification |
| `email` | Email notification |
| `in_app` | In-app notification center |

## Alert History

Each time an alert triggers, a history record is created:

```json
{
    "id": "uuid",
    "alert_id": "uuid",
    "triggered_at": "2026-01-12T12:30:00Z",
    "trigger_value": 150.25,
    "trigger_context": {
        "target_price": 150.00,
        "current_price": 150.25,
        "direction": "above"
    },
    "notification_sent": true,
    "acknowledged_at": null,
    "escalation_level": 0
}
```

## Escalation Configuration

For critical alerts, escalation can be configured:

```json
{
    "enabled": true,
    "max_escalations": 3,
    "levels": [
        {
            "level": 1,
            "channel": "push",
            "delay_minutes": 5,
            "condition": "not_acknowledged"
        },
        {
            "level": 2,
            "channel": "email",
            "delay_minutes": 15,
            "condition": "not_acknowledged"
        },
        {
            "level": 3,
            "channel": "telegram",
            "delay_minutes": 30,
            "condition": "not_acknowledged"
        }
    ]
}
```

## Error Responses

| Status | Description |
|--------|-------------|
| 400 | Invalid request parameters |
| 401 | Unauthenticated |
| 403 | Unauthorized (not owner of alert) |
| 404 | Alert not found |
| 422 | Validation error |
| 429 | Rate limit exceeded |

## Rate Limits

- Alert creation: 100 alerts per user
- API calls: 60 requests per minute
- Backtest: 10 per hour

## Services

### AlertMatcher

Evaluates alerts against current market data.

```php
$matcher = app(AlertMatcher::class);
$result = $matcher->evaluatePriceAlert($alert, $price);
// $result->triggered (bool)
// $result->triggerValue (float)
// $result->context (array)
```

### AlertCacheService

Manages cached alerts for fast lookup.

```php
$cache = app(AlertCacheService::class);
$alerts = $cache->getAlertsForAsset($assetId);
$cache->invalidateAsset($assetId);
```

## Jobs

| Job | Description |
|-----|-------------|
| `ProcessPriceAlerts` | Processes price-based alerts |
| `ProcessIntelligenceAlerts` | Processes AI/ML alerts |
| `ProcessAlertChains` | Activates chained alerts |
| `ProcessEscalation` | Handles escalation logic |
| `SendAlertNotification` | Dispatches notifications |
| `RunAlertBacktest` | Runs historical backtest |
| `GenerateDigest` | Creates daily digest |

## Telegram Integration

Alerts can be created and managed via Telegram bot:

- `/alerts` - View alerts menu
- Create alerts via inline keyboard flow
- Receive notifications on triggers
- Acknowledge alerts directly in Telegram
