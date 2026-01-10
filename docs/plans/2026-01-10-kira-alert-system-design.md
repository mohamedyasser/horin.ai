# Kira Alert System - Design Document

**Date:** 2026-01-10
**Status:** Ready for Implementation
**Scope:** Full Price Category + Intelligence-Based Alerts

---

## Overview

The Kira Alert System enables users to create customizable alerts for Egyptian stock market assets. It combines traditional price-based alerts with Kira's unique ML-powered intelligence alerts (predictions, signals, anomalies, patterns, recommendations).

### Key Differentiators

- **Intelligence Alerts**: Leverage existing ML pipeline (predictions, signals, anomalies, patterns)
- **Compound Alerts**: AND/OR logic combining multiple conditions
- **Alert Chains**: Sequential triggers (Alert A fires → activates Alert B)
- **Watchlist Scope**: Apply alerts across entire watchlist/portfolio
- **Backtest**: Historical simulation before creating alerts

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         EXISTING ML PIPELINE                                 │
│                                                                              │
│  technical-analysis ──► signal-detection ──► anomaly ──► signal-classification
│         │                     │                │              │              │
│         ▼                     ▼                ▼              ▼              │
│  technical_indicators   detected_signals  anomaly_alerts  processed_signals │
│                                                                              │
│  pattern-detection ────────────────────────────────────► pattern_updates     │
│                                                                              │
│  recommendation ───────────────────────────────────► trading_recommendations│
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                   ┌────────────────┼────────────────┐
                   ▼                ▼                ▼
        ┌──────────────────┬──────────────────┬──────────────────┐
        │ Priority Channels│ Action Channels  │ Special Channels │
        ├──────────────────┼──────────────────┼──────────────────┤
        │classified_critical│action_strong_buy│ pattern_updates  │
        │classified_high   │action_buy        │ anomaly_alerts   │
        │classified_medium │action_hold       │ trading_recs     │
        │classified_low    │action_sell       │                  │
        │classified_info   │action_strong_sell│                  │
        │                  │action_take_profit│                  │
        │                  │action_stop_loss  │                  │
        └──────────────────┴──────────────────┴──────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                      LARAVEL BACKEND (alerts:listen command)                 │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │              AlertsListener (Long-running Redis subscriber)          │    │
│  │  • Subscribes to all ML pipeline channels                           │    │
│  │  • Decodes MessagePack/JSON messages                                │    │
│  │  • Matches against active user alerts                               │    │
│  │  • Dispatches SendAlertNotification jobs                            │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                    │                                         │
│         ┌──────────────────────────┼──────────────────────────┐             │
│         ▼                          ▼                          ▼             │
│  ┌─────────────┐          ┌──────────────┐          ┌─────────────┐        │
│  │   Database  │          │   Telegram   │          │   Reverb    │        │
│  │ Notifications│          │   Bot API   │          │ (WebSocket) │        │
│  └─────────────┘          └──────────────┘          └─────────────┘        │
│                                                            │                │
│                                    AlertTriggered Event ───┘                │
└─────────────────────────────────────────────────────────────────────────────┘
                                         │
                                         ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              VUE FRONTEND                                    │
│                                                                              │
│  Echo.private(`user.${userId}.alerts`)                                      │
│      .listen('.alert.triggered', (event) => {                               │
│          notifications.value.unshift(event);                                │
│          showToast(event);                                                  │
│      });                                                                    │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Channel Subscription Strategy

The Laravel backend subscribes to channels based on alert types:

| Alert Type | Channels to Subscribe |
|------------|----------------------|
| Signal | `classified_*`, `action_*` |
| Anomaly | `anomaly_alerts`, `classified_critical` |
| Pattern | `pattern_updates` |
| Recommendation | `trading_recommendations`, `action_strong_buy`, `action_buy`, `action_sell`, `action_strong_sell` |
| Prediction | (Polled from Redis keys, not pub/sub) |
| Price-based | (Polled from price feed, not pub/sub) |

---

## Database Schema

### Core Tables

#### `alerts`

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| user_id | uuid | Foreign key to users |
| asset_id | uuid, nullable | Foreign key to assets (null for watchlist/portfolio scope) |
| template_id | uuid, nullable | Links to reusable template |
| parent_alert_id | uuid, nullable | For compound alert grouping |
| chain_from_id | uuid, nullable | Triggered by which alert |
| type | enum | price/prediction/signal/anomaly/pattern/recommendation |
| trigger_type | string | Specific trigger (target_price, rsi_cross, etc.) |
| scope | enum | single_asset/watchlist/portfolio/sector/market |
| direction | enum | above/below/both/cross_up/cross_down |
| condition_logic | enum | single/and/or |
| parameters | json | Type-specific thresholds & config |
| status | enum | active/triggered/paused/expired/chained |
| priority | enum | critical/high/medium/low |
| is_recurring | bool | Fire once vs. repeat |
| cooldown_minutes | int | Min time between triggers (default: 60) |
| max_triggers | int, nullable | Stop after N fires |
| triggered_count | int | Times triggered (default: 0) |
| delivery_config | json | Per-alert channel overrides |
| escalation_config | json | Escalation rules |
| snoozed_until | timestamp, nullable | Snooze end time |
| last_triggered_at | timestamp, nullable | Last trigger time |
| expires_at | timestamp, nullable | Alert expiration |
| market_hours_only | bool | Only during market hours (default: true) |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `alert_templates`

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| user_id | uuid, nullable | Null for system templates |
| name | string | Template name |
| name_ar | string | Arabic name |
| description | string | Description |
| type | enum | Alert type |
| trigger_type | string | Trigger type |
| default_parameters | json | Default parameters |
| default_delivery_config | json | Default delivery settings |
| is_public | bool | Shareable with others |
| usage_count | int | Times used |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `alert_history`

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| alert_id | uuid | Foreign key to alerts |
| user_id | uuid | Denormalized for fast queries |
| asset_id | uuid, nullable | Asset that triggered |
| triggered_at | timestamp | When triggered |
| trigger_value | decimal | Value that caused trigger |
| trigger_context | json | Snapshot of conditions |
| notification_sent | bool | Whether notification was sent |
| acknowledged_at | timestamp, nullable | When user acknowledged |
| escalation_level | int | Current escalation level (default: 0) |

#### `user_alert_preferences`

| Column | Type | Description |
|--------|------|-------------|
| user_id | uuid | Primary key, foreign key to users |
| default_channels | json | ["telegram", "push", "email"] |
| quiet_hours_start | time | Quiet hours start |
| quiet_hours_end | time | Quiet hours end |
| timezone | string | User timezone |
| max_alerts_per_hour | int | Rate limit (default: 10) |
| max_alerts_per_day | int | Rate limit (default: 50) |
| digest_enabled | bool | Enable daily digest |
| digest_time | time | Digest delivery time |
| smart_defaults_enabled | bool | Use profile for suggestions |
| created_at | timestamp | |
| updated_at | timestamp | |

#### `alert_chains`

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| name | string | Chain name |
| user_id | uuid | Owner |
| trigger_alert_id | uuid | When this fires... |
| activate_alert_id | uuid | ...activate this |
| delay_minutes | int | Delay before activation (default: 0) |
| expires_after_minutes | int, nullable | Activated alert expiry |
| is_active | bool | Chain enabled |

#### `notifications`

| Column | Type | Description |
|--------|------|-------------|
| id | uuid | Primary key |
| user_id | uuid | Recipient |
| alert_id | uuid, nullable | Source alert |
| alert_history_id | uuid | Link to history |
| type | string | Notification class |
| channel | enum | telegram/push/email/sms/in_app |
| priority | enum | critical/high/medium/low |
| title | string | English title |
| title_ar | string | Arabic title |
| body | text | English body |
| body_ar | text | Arabic body |
| data | json | Deep linking payload |
| status | enum | pending/sent/delivered/failed/read |
| scheduled_at | timestamp | For digest/delayed delivery |
| sent_at | timestamp | When sent |
| delivered_at | timestamp | When delivered |
| read_at | timestamp | When read |
| failed_reason | string, nullable | Failure reason |
| retry_count | int | Retry attempts (default: 0) |
| escalation_level | int | Current level (default: 0) |
| escalated_at | timestamp, nullable | When escalated |
| created_at | timestamp | |
| updated_at | timestamp | |

---

## Alert Types & Configurations

### Price-Based Alerts (Category C)

#### 1. Target Price Alert

```json
{
  "trigger_type": "target_price",
  "parameters": {
    "target_price": 52.50,
    "direction": "above",
    "auto_direction": true
  }
}
```

#### 2. Breakout Alert

```json
{
  "trigger_type": "breakout",
  "parameters": {
    "level": 50.00,
    "direction": "above",
    "confirmation": "sustained",
    "confirmation_seconds": 30,
    "anti_whipsaw": true,
    "consecutive_ticks": 2
  }
}
```

#### 3. Support/Resistance Zone Alert

```json
{
  "trigger_type": "zone",
  "parameters": {
    "zone_low": 48.00,
    "zone_high": 52.00,
    "trigger_on": "enter",
    "cooldown_hours": 4
  }
}
```

#### 4. Price Gap Alert

```json
{
  "trigger_type": "gap",
  "parameters": {
    "gap_threshold_percent": 3.0,
    "direction": "both",
    "check_at_open": true
  }
}
```

#### 5. 52-Week High/Low Alert

```json
{
  "trigger_type": "52week",
  "parameters": {
    "type": "high",
    "cooldown_hours": 24
  }
}
```

#### 6. Daily % Change Alert

```json
{
  "trigger_type": "daily_change",
  "parameters": {
    "threshold_percent": 5.0,
    "direction": "both",
    "from_reference": "open"
  }
}
```

#### 7. Price Return to Entry Alert

```json
{
  "trigger_type": "entry_return",
  "parameters": {
    "entry_price": 45.00,
    "source": "manual",
    "tolerance_percent": 0.5
  }
}
```

### Intelligence-Based Alerts (Category D)

#### 1. Prediction Alert

```json
{
  "trigger_type": "prediction",
  "parameters": {
    "prediction_type": "price_direction",
    "horizon": "1hour",
    "direction": "up",
    "min_confidence": 0.75,
    "min_predicted_change_percent": 2.0
  }
}
```

#### 2. Signal Alert

```json
{
  "trigger_type": "signal",
  "parameters": {
    "indicators": ["RSI", "MACD", "EMA"],
    "signal_types": ["oversold", "bullish_cross"],
    "min_strength": 0.7,
    "any_or_all": "any"
  }
}
```

#### 3. Anomaly Alert

```json
{
  "trigger_type": "anomaly",
  "parameters": {
    "anomaly_types": ["price_spike", "volume_surge", "unusual_pattern"],
    "min_confidence": 0.8,
    "severity": ["high", "critical"]
  }
}
```

#### 4. Pattern Alert

```json
{
  "trigger_type": "pattern",
  "parameters": {
    "patterns": ["head_shoulder", "double_bottom", "triangle"],
    "pattern_status": "confirmed",
    "min_confidence": 0.7,
    "direction_bias": "bullish"
  }
}
```

#### 5. Recommendation Alert

```json
{
  "trigger_type": "recommendation",
  "parameters": {
    "trigger_on": "change",
    "recommendations": ["strong_buy", "buy"],
    "min_score": 0.75,
    "notify_downgrades": true
  }
}
```

#### 6. Compound Intelligence Alert

```json
{
  "trigger_type": "compound_intelligence",
  "condition_logic": "and",
  "parameters": {
    "conditions": [
      {
        "type": "signal",
        "indicators": ["RSI"],
        "signal_types": ["oversold"],
        "min_strength": 0.7
      },
      {
        "type": "prediction",
        "direction": "up",
        "min_confidence": 0.7
      },
      {
        "type": "pattern",
        "patterns": ["double_bottom"],
        "pattern_status": "confirmed"
      }
    ]
  }
}
```

---

## Notification System

### Channels

| Channel | Priority | Implementation |
|---------|----------|----------------|
| Telegram | Primary | Existing `telegram_id` on User |
| In-App | Primary | Database notifications table |
| Push | Secondary | Firebase/OneSignal |
| Email | Low | Laravel Mail |
| SMS | Critical only | Twilio/MessageBird (future) |

### Delivery Preferences

```json
{
  "channels": {
    "telegram": {
      "enabled": true,
      "priority": 1,
      "for_priorities": ["critical", "high", "medium"]
    },
    "push": {
      "enabled": true,
      "priority": 2,
      "for_priorities": ["critical", "high"]
    },
    "email": {
      "enabled": true,
      "priority": 3,
      "for_priorities": ["low"],
      "digest_only": true
    }
  },
  "quiet_hours": {
    "enabled": true,
    "start": "23:00",
    "end": "07:00",
    "timezone": "Africa/Cairo",
    "allow_critical": true
  },
  "batching": {
    "enabled": true,
    "window_minutes": 5,
    "max_per_batch": 5
  }
}
```

### Escalation Rules

```json
{
  "enabled": true,
  "levels": [
    { "level": 0, "channel": "push", "delay_minutes": 0 },
    { "level": 1, "channel": "telegram", "delay_minutes": 5, "condition": "not_acknowledged" },
    { "level": 2, "channel": "sms", "delay_minutes": 15, "condition": "not_acknowledged" }
  ],
  "max_escalations": 2,
  "reset_on_acknowledge": true
}
```

### Telegram Message Format

```
🎯 *Target Price Reached*
━━━━━━━━━━━━━━━━━━

*COMI* - Commercial International Bank

Current Price: *52.50 EGP*
Target: 52.00 EGP
Change: +4.2% today

🕐 10:45 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Stock](https://kira.app/assets/COMI) · [Manage Alert](https://kira.app/alerts/123)
```

### Rate Limits

| Limit | Value |
|-------|-------|
| Per user per hour | 10 |
| Per user per day | 50 |
| Per alert per day (recurring) | 3 |
| Telegram per hour | 20 |
| Push per hour | 30 |
| Email per hour | 10 |

---

## Processing Architecture

### Laravel Jobs

```
app/Jobs/Alerts/
├── ProcessPriceAlerts.php       ── Triggered on price update
├── ProcessIntelligenceAlerts.php ── Triggered on ML data update
├── ProcessScheduledAlerts.php   ── Runs on schedule (gap, 52-week)
├── ProcessCompoundAlerts.php    ── Evaluates multi-condition alerts
├── ProcessAlertChains.php       ── Activates chained alerts
├── SendAlertNotification.php    ── Dispatches to channels
├── ProcessEscalation.php        ── Handles escalation logic
├── GenerateDigest.php           ── Creates digest notifications
├── RunAlertBacktest.php         ── Historical simulation
```

### Schedule Configuration

```php
// routes/console.php

// Every minute: process price alerts
Schedule::job(new ProcessPriceAlerts())
    ->everyMinute()
    ->withoutOverlapping();

// Market open (10:00 AM Cairo): gap alerts
Schedule::job(new ProcessScheduledAlerts('market_open'))
    ->dailyAt('10:00')
    ->timezone('Africa/Cairo')
    ->weekdays();

// Market close (14:30 PM Cairo): 52-week, daily change
Schedule::job(new ProcessScheduledAlerts('market_close'))
    ->dailyAt('14:30')
    ->timezone('Africa/Cairo')
    ->weekdays();

// Process escalations every 5 minutes
Schedule::job(new ProcessEscalation())
    ->everyFiveMinutes();

// Daily digest at 8 PM
Schedule::job(new GenerateDigest('daily'))
    ->dailyAt('20:00')
    ->timezone('Africa/Cairo');

// Weekly digest on Friday
Schedule::job(new GenerateDigest('weekly'))
    ->weeklyOn(5, '15:00')
    ->timezone('Africa/Cairo');
```

### Redis Integration

The alert system integrates with existing Redis Pub/Sub channels from the ML pipeline:

#### Priority-Based Signal Channels (MessagePack encoded)

| Channel | Purpose | Alert Types |
|---------|---------|-------------|
| `classified_critical` | Priority 1 signals | Anomaly, Signal (high strength) |
| `classified_high` | Priority 2 signals | Signal, Prediction (high confidence) |
| `classified_medium` | Priority 3 signals | Signal, Pattern |
| `classified_low` | Priority 4 signals | Signal (low strength) |
| `classified_info` | Priority 5 signals | Informational only |

#### Action-Based Signal Channels (MessagePack encoded)

| Channel | Purpose | Alert Types |
|---------|---------|-------------|
| `action_strong_buy` | Strong buy signals | Recommendation, Compound |
| `action_buy` | Buy signals | Recommendation, Compound |
| `action_hold` | Hold signals | Recommendation |
| `action_sell` | Sell signals | Recommendation, Compound |
| `action_strong_sell` | Strong sell signals | Recommendation, Compound |
| `action_wait` | Wait signals | Recommendation |
| `action_monitor` | Monitor signals | Pattern |
| `action_take_profit` | Take profit signals | Target price proximity |
| `action_stop_loss` | Stop loss signals | Price drop |

#### Pattern & Anomaly Channels

| Channel | Purpose | Format |
|---------|---------|--------|
| `pattern_updates` | Chart pattern detections | MessagePack |
| `anomaly_alerts` | Anomaly detections | JSON |
| `detected_signals` | Raw technical signals | MessagePack |
| `processed_signals` | Enriched signals | MessagePack |

#### Recommendation Channel (JSON encoded)

| Channel | Purpose | Format |
|---------|---------|--------|
| `trading_recommendations` | Recommendation updates | JSON |

#### New Alert System Channel

| Channel | Purpose | Format |
|---------|---------|--------|
| `user_alerts` | Triggered user alerts | MessagePack |

#### Message Formats

**Classified Signal (MessagePack):**
```python
{
    'id': str,                    # Classification ID (UUID)
    'pid': str,                   # Product/Asset ID
    'original_signal': {
        'id': str,
        'indicator': str,         # RSI, MACD, Bollinger, etc.
        'signal_type': str,       # oversold, bullish_cross, breakout, etc.
        'strength': float,        # 0.0-1.0
        'value': dict,
        'confidence': float,
        'price': float,
        'volume': float
    },
    'category': str,              # strong_reversal, breakout, momentum, etc.
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

**Pattern Update (MessagePack):**
```python
{
    'pid': str,
    'timestamp': float,
    'patterns': [
        {
            'type': str,          # head_shoulders, double_bottom, triangle, etc.
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

**Anomaly Alert (JSON):**
```python
{
    'pid': str,
    'score': float,               # 0.0-1.0 anomaly score
    'types': [str],               # price_spike, volume_surge, etc.
    'reasons': [str],
    'timestamp': float,
    'price': float,
    'metadata': dict
}
```

**Trading Recommendation (JSON):**
```python
{
    'event': 'recommendations_updated',
    'count': int,
    'by_action': {
        'STRONG_BUY': int,
        'BUY': int,
        'ACCUMULATE': int,
        'HOLD': int,
        'REDUCE': int,
        'SELL': int,
        'STRONG_SELL': int,
        'AVOID': int
    },
    'urgent_count': int,
    'timestamp': str              # ISO format
}
```

#### Redis Data Keys (Non-Pub/Sub)

| Key Pattern | Purpose | TTL |
|-------------|---------|-----|
| `signal:{pid}:{id}` | Individual signal | 5 min |
| `recent_signals:{pid}` | Recent signals list | - |
| `classified:{pid}:{id}` | Classified signal | 5 min |
| `patterns:{pid}` | Pattern data | 5 min |
| `recommendations:all` | All recommendations | 60 sec |
| `recommendations:{action}` | By action type | 60 sec |
| `recommendations:top_opportunities` | Top 10 | 60 sec |
| `recommendations:urgent` | Urgent recs | 60 sec |

---

## Price Feed Integration

Price-based alerts require real-time price data. Unlike intelligence alerts which use Redis Pub/Sub, price alerts poll from the database and Redis cache.

### Price Data Sources

| Source | Location | Format | Freshness |
|--------|----------|--------|-----------|
| Latest prices | `latest_asset_prices` (materialized view) | SQL | Every tick |
| Redis cache | `price:{symbol}` | JSON | 1 second TTL |
| OHLCV candles | `asset_prices` (TimescaleDB) | SQL | Historical |
| Market status | `market:status` Redis key | JSON | On change |

### Price Cache Structure

```json
{
  "symbol": "COMI",
  "price": 52.50,
  "open": 50.35,
  "high": 52.80,
  "low": 50.10,
  "prev_close": 50.35,
  "volume": 3250000,
  "change": 2.15,
  "change_percent": 4.27,
  "bid": 52.45,
  "ask": 52.55,
  "updated_at": "2026-01-10T10:45:00+02:00"
}
```

### Price Alert Processing Flow

```php
// app/Jobs/Alerts/ProcessPriceAlerts.php

public function handle()
{
    // 1. Get all assets with active price alerts
    $assetIds = Alert::where('type', 'price')
        ->where('status', 'active')
        ->whereNull('snoozed_until')
        ->orWhere('snoozed_until', '<', now())
        ->distinct()
        ->pluck('asset_id');

    // 2. Batch fetch latest prices
    $prices = LatestAssetPrice::whereIn('asset_id', $assetIds)
        ->get()
        ->keyBy('asset_id');

    // 3. Fetch alerts grouped by asset for efficiency
    $alertsByAsset = Alert::where('type', 'price')
        ->where('status', 'active')
        ->whereIn('asset_id', $assetIds)
        ->get()
        ->groupBy('asset_id');

    // 4. Process each asset's alerts
    foreach ($alertsByAsset as $assetId => $alerts) {
        $price = $prices->get($assetId);
        if (!$price) continue;

        foreach ($alerts as $alert) {
            $this->evaluateAlert($alert, $price);
        }
    }
}
```

### Market Hours Awareness

```php
// Only process during market hours (10:00 AM - 2:30 PM Cairo)
private function isMarketOpen(): bool
{
    $cairo = new DateTimeZone('Africa/Cairo');
    $now = new DateTime('now', $cairo);

    $dayOfWeek = (int) $now->format('N');
    if ($dayOfWeek >= 6) return false; // Weekend

    $time = $now->format('H:i');
    return $time >= '10:00' && $time <= '14:30';
}
```

---

## Alert Matching Optimization

Efficient alert matching is critical when processing thousands of active alerts against real-time data.

### Database Indexes

```sql
-- alerts table indexes
CREATE INDEX idx_alerts_user_status ON alerts(user_id, status);
CREATE INDEX idx_alerts_asset_type_status ON alerts(asset_id, type, status)
    WHERE status = 'active';
CREATE INDEX idx_alerts_type_status ON alerts(type, status)
    WHERE status = 'active';
CREATE INDEX idx_alerts_expires ON alerts(expires_at)
    WHERE expires_at IS NOT NULL AND status = 'active';
CREATE INDEX idx_alerts_snoozed ON alerts(snoozed_until)
    WHERE snoozed_until IS NOT NULL;

-- Partial index for price alerts specifically
CREATE INDEX idx_alerts_price_active ON alerts(asset_id, trigger_type)
    WHERE type = 'price' AND status = 'active';

-- JSON path indexes for common queries
CREATE INDEX idx_alerts_target_price ON alerts
    USING gin ((parameters->'target_price'));

-- alert_history indexes
CREATE INDEX idx_history_user_triggered ON alert_history(user_id, triggered_at DESC);
CREATE INDEX idx_history_alert ON alert_history(alert_id, triggered_at DESC);
CREATE INDEX idx_history_asset ON alert_history(asset_id, triggered_at DESC);

-- notifications indexes
CREATE INDEX idx_notifications_user_status ON notifications(user_id, status, created_at DESC);
CREATE INDEX idx_notifications_scheduled ON notifications(scheduled_at)
    WHERE status = 'pending';
CREATE INDEX idx_notifications_escalation ON notifications(escalation_level, escalated_at)
    WHERE escalation_level > 0;
```

### In-Memory Alert Cache

```php
// app/Services/AlertCacheService.php

class AlertCacheService
{
    private const CACHE_TTL = 60; // seconds

    /**
     * Cache active alerts by asset for fast lookup
     */
    public function cacheActiveAlerts(): void
    {
        $alerts = Alert::where('status', 'active')
            ->whereNull('snoozed_until')
            ->orWhere('snoozed_until', '<', now())
            ->get();

        // Group by asset_id
        $byAsset = $alerts->groupBy('asset_id');
        foreach ($byAsset as $assetId => $assetAlerts) {
            Cache::put(
                "active_alerts:asset:{$assetId}",
                $assetAlerts->toArray(),
                self::CACHE_TTL
            );
        }

        // Group by type for intelligence alerts
        $byType = $alerts->groupBy('type');
        foreach ($byType as $type => $typeAlerts) {
            Cache::put(
                "active_alerts:type:{$type}",
                $typeAlerts->toArray(),
                self::CACHE_TTL
            );
        }

        // Store asset IDs with active alerts in a set
        Redis::del('active_alert_assets');
        Redis::sadd('active_alert_assets', ...$byAsset->keys()->toArray());
    }

    /**
     * Get alerts for a specific asset
     */
    public function getAlertsForAsset(string $assetId): Collection
    {
        return Cache::remember(
            "active_alerts:asset:{$assetId}",
            self::CACHE_TTL,
            fn() => Alert::where('asset_id', $assetId)
                ->where('status', 'active')
                ->get()
        );
    }

    /**
     * Check if asset has any active alerts (O(1) lookup)
     */
    public function hasActiveAlerts(string $assetId): bool
    {
        return Redis::sismember('active_alert_assets', $assetId);
    }
}
```

### Optimized Matching Flow

```
┌─────────────────────────────────────────────────────────────┐
│ 1. Event Arrives (price update or ML signal)                │
└─────────────────────────────────┬───────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. Quick Check: Does this asset have any active alerts?     │
│    Redis SISMEMBER 'active_alert_assets' {asset_id}         │
│    If NO → Skip (O(1))                                      │
└─────────────────────────────────┬───────────────────────────┘
                                  │ YES
                                  ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. Load alerts from cache                                   │
│    Cache::get("active_alerts:asset:{asset_id}")             │
│    If miss → Query DB and cache (O(log n))                  │
└─────────────────────────────────┬───────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────┐
│ 4. Filter alerts by type matching event                     │
│    Price event → price alerts only                          │
│    Signal event → signal/compound alerts only               │
└─────────────────────────────────┬───────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. Evaluate each matching alert                             │
│    Check trigger condition                                  │
│    Check cooldown                                           │
│    Check rate limits                                        │
└─────────────────────────────────┬───────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. Queue notifications for triggered alerts                 │
│    SendAlertNotification::dispatch($alert, $data)           │
└─────────────────────────────────────────────────────────────┘
```

### Cache Invalidation

```php
// Invalidate cache when alert is created/updated/deleted
Alert::created(fn($alert) => app(AlertCacheService::class)->invalidateAsset($alert->asset_id));
Alert::updated(fn($alert) => app(AlertCacheService::class)->invalidateAsset($alert->asset_id));
Alert::deleted(fn($alert) => app(AlertCacheService::class)->invalidateAsset($alert->asset_id));
```

---

## Scope Resolution

Alerts can target single assets, watchlists, portfolios, sectors, or the entire market.

### Scope Types

| Scope | Description | Use Case |
|-------|-------------|----------|
| `single_asset` | One specific asset | "Alert me when COMI hits 52 EGP" |
| `watchlist` | All assets in user's watchlist | "Alert me when any watchlist stock gaps up 5%" |
| `portfolio` | All assets user owns | "Alert me when any holding drops 10%" |
| `sector` | All assets in a sector | "Alert me when any bank stock is oversold" |
| `market` | All active assets | "Alert me on any 52-week high" (premium) |

### Scope Resolution Logic

```php
// app/Services/AlertScopeResolver.php

class AlertScopeResolver
{
    /**
     * Resolve which assets an alert applies to
     */
    public function resolveAssets(Alert $alert): Collection
    {
        return match ($alert->scope) {
            'single_asset' => collect([$alert->asset_id]),

            'watchlist' => $alert->user
                ->watchlist()
                ->pluck('asset_id'),

            'portfolio' => $alert->user
                ->portfolioHoldings()
                ->where('quantity', '>', 0)
                ->pluck('asset_id'),

            'sector' => Asset::where('sector_id', $alert->parameters['sector_id'])
                ->where('is_active', true)
                ->pluck('id'),

            'market' => Asset::where('is_active', true)
                ->pluck('id'),

            default => collect([]),
        };
    }

    /**
     * Check if an event matches an alert's scope
     */
    public function matchesScope(Alert $alert, string $eventAssetId): bool
    {
        if ($alert->scope === 'single_asset') {
            return $alert->asset_id === $eventAssetId;
        }

        // For multi-asset scopes, check membership
        $assets = $this->resolveAssets($alert);
        return $assets->contains($eventAssetId);
    }

    /**
     * Get entry price for portfolio alerts
     */
    public function getEntryPrice(Alert $alert, string $assetId): ?float
    {
        if ($alert->scope !== 'portfolio') {
            return $alert->parameters['entry_price'] ?? null;
        }

        $holding = $alert->user
            ->portfolioHoldings()
            ->where('asset_id', $assetId)
            ->first();

        return $holding?->average_cost;
    }
}
```

### Multi-Asset Alert History

When a watchlist/portfolio alert triggers for multiple assets:

```php
// Create separate history entry per triggered asset
foreach ($triggeredAssets as $assetId => $triggerData) {
    AlertHistory::create([
        'alert_id' => $alert->id,
        'user_id' => $alert->user_id,
        'asset_id' => $assetId,  // Specific asset that triggered
        'triggered_at' => now(),
        'trigger_value' => $triggerData['value'],
        'trigger_context' => $triggerData['context'],
    ]);
}
```

---

## Failure Handling & Recovery

### Redis Subscriber Recovery

```php
// app/Console/Commands/AlertsListen.php

class AlertsListen extends Command
{
    private int $reconnectAttempts = 0;
    private const MAX_RECONNECT_DELAY = 30; // seconds

    public function handle()
    {
        while (true) {
            try {
                $this->subscribeToChannels();
            } catch (ConnectionException $e) {
                $this->handleDisconnection($e);
            }
        }
    }

    private function handleDisconnection(ConnectionException $e): void
    {
        $this->reconnectAttempts++;
        $delay = min(
            pow(2, $this->reconnectAttempts), // Exponential backoff
            self::MAX_RECONNECT_DELAY
        );

        Log::warning("Redis disconnected, reconnecting in {$delay}s", [
            'attempt' => $this->reconnectAttempts,
            'error' => $e->getMessage()
        ]);

        // Alert ops if too many failures
        if ($this->reconnectAttempts >= 5) {
            $this->alertOpsTeam('Redis subscriber failing', $e);
        }

        sleep($delay);
    }

    private function subscribeToChannels(): void
    {
        $redis = Redis::connection('pubsub');

        // Reset counter on successful connection
        $this->reconnectAttempts = 0;

        $redis->subscribe($this->getAllChannels(), function ($message, $channel) {
            $this->processMessage($message, $channel);
        });
    }
}
```

### Notification Delivery Failures

```php
// app/Jobs/SendAlertNotification.php

class SendAlertNotification implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [10, 60, 300]; // seconds

    public function handle(): void
    {
        $notification = $this->createNotificationRecord();

        try {
            $this->sendToChannels($notification);
            $notification->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (TelegramRateLimitException $e) {
            // Respect Telegram's Retry-After header
            $this->release($e->retryAfter);
        } catch (TelegramBadRequestException $e) {
            // Don't retry, log and notify admin
            $notification->update([
                'status' => 'failed',
                'failed_reason' => $e->getMessage()
            ]);
            Log::error('Telegram bad request', ['error' => $e->getMessage()]);
        } catch (PushTokenInvalidException $e) {
            // Mark user's push token as invalid
            $this->alert->user->update(['push_token' => null]);
            $notification->update(['status' => 'failed', 'failed_reason' => 'invalid_token']);
        }
    }

    public function failed(\Throwable $e): void
    {
        // Move to dead letter queue after all retries exhausted
        FailedNotification::create([
            'notification_id' => $this->notification->id,
            'alert_id' => $this->alert->id,
            'user_id' => $this->alert->user_id,
            'error' => $e->getMessage(),
            'payload' => $this->data,
            'failed_at' => now()
        ]);
    }
}
```

### Idempotency

```php
// Prevent duplicate notifications
private function createNotificationRecord(): Notification
{
    $uniqueKey = "{$this->alert->id}:{$this->triggerTimestamp}";

    return Notification::firstOrCreate(
        ['idempotency_key' => $uniqueKey],
        [
            'user_id' => $this->alert->user_id,
            'alert_id' => $this->alert->id,
            'type' => 'alert.triggered',
            'status' => 'pending',
            // ... other fields
        ]
    );
}
```

### Dead Letter Queue Processing

```php
// app/Jobs/ProcessFailedNotifications.php
// Runs daily to review and retry failed notifications

Schedule::job(new ProcessFailedNotifications())
    ->dailyAt('04:00')
    ->timezone('Africa/Cairo');
```

---

## Monitoring & Observability

### Key Metrics

```php
// app/Services/AlertMetricsService.php

class AlertMetricsService
{
    public function recordAlertTriggered(Alert $alert): void
    {
        // Counter: total alerts triggered
        Metrics::counter('alerts.triggered.total', 1, [
            'type' => $alert->type,
            'trigger_type' => $alert->trigger_type,
            'priority' => $alert->priority,
        ]);
    }

    public function recordNotificationSent(Notification $notification): void
    {
        Metrics::counter('notifications.sent.total', 1, [
            'channel' => $notification->channel,
            'priority' => $notification->priority,
        ]);
    }

    public function recordNotificationFailed(Notification $notification, string $reason): void
    {
        Metrics::counter('notifications.failed.total', 1, [
            'channel' => $notification->channel,
            'reason' => $reason,
        ]);
    }

    public function recordProcessingLatency(string $type, float $durationMs): void
    {
        Metrics::histogram('alerts.processing.duration_ms', $durationMs, [
            'type' => $type,
        ]);
    }
}
```

### Metrics Dashboard

| Metric | Type | Labels | Alert Threshold |
|--------|------|--------|-----------------|
| `alerts.active.count` | Gauge | type, scope | - |
| `alerts.triggered.total` | Counter | type, trigger_type, priority | - |
| `alerts.processing.duration_ms` | Histogram | type | p99 > 1000ms |
| `notifications.sent.total` | Counter | channel, priority | - |
| `notifications.failed.total` | Counter | channel, reason | rate > 5% |
| `notifications.delivery.latency_ms` | Histogram | channel | p99 > 5000ms |
| `redis.subscriber.connected` | Gauge | - | 0 for > 1 min |
| `alerts.queue.depth` | Gauge | queue_name | > 1000 |

### Structured Logging

```php
// All alert-related logs include these fields
Log::info('Alert triggered', [
    'alert_id' => $alert->id,
    'user_id' => $alert->user_id,
    'asset_id' => $assetId,
    'type' => $alert->type,
    'trigger_type' => $alert->trigger_type,
    'trigger_value' => $triggerValue,
    'current_value' => $currentValue,
    'latency_ms' => $latencyMs,
]);
```

### Internal Alerts (for Ops)

```php
// app/Console/Commands/AlertSystemHealthCheck.php

class AlertSystemHealthCheck extends Command
{
    protected $signature = 'alerts:health-check';

    public function handle(): void
    {
        $issues = [];

        // Check Redis subscriber
        if (!$this->isRedisSubscriberRunning()) {
            $issues[] = 'Redis subscriber not running';
        }

        // Check queue depth
        $queueDepth = Queue::size('alerts');
        if ($queueDepth > 1000) {
            $issues[] = "Alert queue depth high: {$queueDepth}";
        }

        // Check failure rate (last hour)
        $failureRate = $this->getNotificationFailureRate();
        if ($failureRate > 0.05) {
            $issues[] = "Notification failure rate: " . ($failureRate * 100) . "%";
        }

        // Check processing latency
        $p99Latency = $this->getP99Latency();
        if ($p99Latency > 5000) {
            $issues[] = "P99 latency high: {$p99Latency}ms";
        }

        if (count($issues) > 0) {
            $this->alertOpsTeam($issues);
        }
    }
}

// Run every 5 minutes
Schedule::command('alerts:health-check')->everyFiveMinutes();
```

---

## Backtest Implementation

### Backtest Request

```php
// POST /alerts/{alert}/backtest
{
    "lookback_days": 90,        // 1-365 days
    "include_ml_signals": true  // Include historical ML data
}
```

### Backtest Job

```php
// app/Jobs/Alerts/RunAlertBacktest.php

class RunAlertBacktest implements ShouldQueue
{
    public function handle(): void
    {
        $results = [];
        $startDate = now()->subDays($this->lookbackDays);

        // Load historical price data
        $prices = AssetPrice::where('asset_id', $this->alert->asset_id)
            ->where('date', '>=', $startDate)
            ->orderBy('date')
            ->get();

        // Simulate alert matching day by day
        foreach ($prices as $index => $price) {
            $wouldTrigger = $this->evaluateHistoricalTrigger($price, $prices, $index);

            if ($wouldTrigger) {
                $results[] = [
                    'date' => $price->date,
                    'trigger_price' => $price->close,
                    'performance' => $this->calculatePerformance($prices, $index),
                ];
            }
        }

        // Store results
        AlertBacktestResult::create([
            'alert_id' => $this->alert->id,
            'lookback_days' => $this->lookbackDays,
            'trigger_count' => count($results),
            'triggers' => $results,
            'avg_return_1d' => $this->avgReturn($results, '1d'),
            'avg_return_1w' => $this->avgReturn($results, '1w'),
            'avg_return_1m' => $this->avgReturn($results, '1m'),
            'completed_at' => now(),
        ]);
    }

    private function calculatePerformance(Collection $prices, int $triggerIndex): array
    {
        $triggerPrice = $prices[$triggerIndex]->close;

        return [
            '1d' => $this->getReturn($prices, $triggerIndex, 1, $triggerPrice),
            '1w' => $this->getReturn($prices, $triggerIndex, 5, $triggerPrice),
            '1m' => $this->getReturn($prices, $triggerIndex, 22, $triggerPrice),
        ];
    }
}
```

### Backtest Response

```json
{
    "alert_id": "uuid",
    "lookback_days": 90,
    "results": {
        "trigger_count": 3,
        "avg_time_to_trigger_days": 12,
        "max_wait_days": 28,
        "triggers": [
            {
                "date": "2025-12-05",
                "trigger_price": 51.80,
                "performance": {
                    "1d": 0.012,
                    "1w": 0.035,
                    "1m": 0.078
                }
            }
        ],
        "summary": {
            "avg_return_1d": 0.012,
            "avg_return_1w": 0.035,
            "avg_return_1m": 0.078,
            "win_rate": 0.67
        }
    },
    "recommendation": "This alert level has historically shown good follow-through."
}
```

### Backtest Limitations

- Max lookback: 365 days
- Price-based alerts: Full support
- Intelligence alerts: Limited to cached historical signals (may have gaps)
- Compound alerts: Not supported for backtest

---

## Testing Strategy

### Unit Tests

```php
// tests/Unit/Alerts/

class TargetPriceAlertTest extends TestCase
{
    /** @test */
    public function it_triggers_when_price_crosses_target_from_below(): void
    {
        $alert = Alert::factory()->create([
            'trigger_type' => 'target_price',
            'parameters' => ['target_price' => 50.00, 'direction' => 'above'],
        ]);

        $previousPrice = 49.00;
        $currentPrice = 51.00;

        $matcher = new AlertMatcher();
        $result = $matcher->evaluate($alert, $currentPrice, $previousPrice);

        $this->assertTrue($result->triggered);
        $this->assertEquals(51.00, $result->triggerValue);
    }

    /** @test */
    public function it_respects_cooldown_period(): void
    {
        $alert = Alert::factory()->create([
            'cooldown_minutes' => 60,
            'last_triggered_at' => now()->subMinutes(30),
        ]);

        $matcher = new AlertMatcher();
        $result = $matcher->canTrigger($alert);

        $this->assertFalse($result);
    }
}
```

### Integration Tests

```php
// tests/Feature/Alerts/

class AlertProcessingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_processes_price_alerts_and_sends_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->create(['telegram_id' => '123456']);
        $asset = Asset::factory()->create();
        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'parameters' => ['target_price' => 50.00],
        ]);

        // Simulate price update
        LatestAssetPrice::factory()->create([
            'asset_id' => $asset->id,
            'price' => 51.00,
        ]);

        // Run processing job
        (new ProcessPriceAlerts())->handle();

        // Assert notification was sent
        Notification::assertSentTo($user, AlertTriggeredNotification::class);

        // Assert alert history created
        $this->assertDatabaseHas('alert_history', [
            'alert_id' => $alert->id,
            'trigger_value' => 51.00,
        ]);
    }
}
```

### Load Tests

```yaml
# k6 load test config
scenarios:
  price_updates:
    executor: constant-arrival-rate
    rate: 100           # 100 price updates per second
    duration: 5m
    preAllocatedVUs: 50

thresholds:
  http_req_duration: ['p99<100']  # 99th percentile < 100ms
  alerts_processed: ['rate>95']   # Process 95%+ of alerts
```

### Chaos Tests

```php
// tests/Chaos/

class RedisFailureTest extends TestCase
{
    /** @test */
    public function alert_listener_recovers_from_redis_disconnect(): void
    {
        // Start listener
        $listener = new AlertsListen();
        $listener->handle();

        // Simulate Redis disconnect
        Redis::disconnect();

        // Wait for reconnection
        sleep(5);

        // Assert listener is back online
        $this->assertTrue($listener->isConnected());
    }
}
```

---

## Data Retention Policy

### Retention Periods

| Table | Retention | Action |
|-------|-----------|--------|
| `alerts` | Indefinite | Soft delete (status = 'deleted') |
| `alert_history` | 90 days | Hard delete older records |
| `notifications` | 30 days | Hard delete older records |
| `alert_templates` | Indefinite | - |
| `alert_backtest_results` | 7 days | Hard delete |
| `failed_notifications` | 30 days | Hard delete after review |

### Cleanup Jobs

```php
// routes/console.php

// Daily cleanup at 3 AM Cairo time
Schedule::job(new CleanupAlertHistory(days: 90))
    ->dailyAt('03:00')
    ->timezone('Africa/Cairo');

Schedule::job(new CleanupNotifications(days: 30))
    ->dailyAt('03:15')
    ->timezone('Africa/Cairo');

Schedule::job(new CleanupBacktestResults(days: 7))
    ->dailyAt('03:30')
    ->timezone('Africa/Cairo');
```

### Cleanup Implementation

```php
// app/Jobs/CleanupAlertHistory.php

class CleanupAlertHistory implements ShouldQueue
{
    public function __construct(private int $days = 90) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->days);

        $deleted = AlertHistory::where('triggered_at', '<', $cutoff)
            ->delete();

        Log::info("Cleaned up {$deleted} alert history records older than {$this->days} days");

        Metrics::gauge('cleanup.alert_history.deleted', $deleted);
    }
}
```

---

## API Routes

### Web Routes

```php
// Alerts CRUD
Route::resource('alerts', AlertController::class);

// Snooze endpoint
Route::post('alerts/{alert}/snooze', [AlertController::class, 'snooze']);

// Backtest endpoint
Route::post('alerts/{alert}/backtest', [AlertController::class, 'backtest']);
Route::get('alerts/{alert}/backtest/results', [AlertController::class, 'backtestResults']);

// Alert History
Route::get('alerts/history', [AlertHistoryController::class, 'index']);
Route::post('alerts/history/{history}/acknowledge', [AlertHistoryController::class, 'acknowledge']);

// Settings
Route::get('settings/alerts', [AlertPreferencesController::class, 'edit']);
Route::patch('settings/alerts', [AlertPreferencesController::class, 'update']);
```

### Snooze Endpoint Details

**POST** `/alerts/{alert}/snooze`

**Request Body:**
```json
{
    "duration_minutes": 60,
    // OR use preset
    "preset": "1h" | "4h" | "1d" | "until_market_close" | "until_market_open"
}
```

**Response:**
```json
{
    "success": true,
    "alert": {
        "id": "uuid",
        "snoozed_until": "2026-01-10T12:45:00+02:00",
        "status": "active"
    }
}
```

**Preset Resolution:**
- `1h`: now + 1 hour
- `4h`: now + 4 hours
- `1d`: now + 24 hours
- `until_market_close`: Same day 14:30 Cairo (or next trading day if after close)
- `until_market_open`: Next trading day 10:00 Cairo

### Web Routes

```php
// Alerts CRUD
Route::resource('alerts', AlertController::class);
Route::post('alerts/{alert}/snooze', [AlertController::class, 'snooze']);
Route::post('alerts/{alert}/backtest', [AlertController::class, 'backtest']);

// Alert History
Route::get('alerts/history', [AlertHistoryController::class, 'index']);
Route::post('alerts/history/{history}/acknowledge', [AlertHistoryController::class, 'acknowledge']);

// Settings
Route::get('settings/alerts', [AlertPreferencesController::class, 'edit']);
Route::patch('settings/alerts', [AlertPreferencesController::class, 'update']);
```

### API Routes

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
});
```

### Broadcasting Channels

```php
Broadcast::channel('user.{userId}.alerts', function (User $user, string $userId) {
    return $user->id === $userId;
});
```

---

## Frontend Structure

### File Structure

```
resources/js/
├── pages/
│   └── alerts/
│       ├── Index.vue         ── Alert list & management
│       ├── Create.vue        ── Create wizard (4 steps)
│       ├── Edit.vue          ── Edit existing alert
│       ├── History.vue       ── Triggered alert history
│       └── Preferences.vue   ── Global settings
│
├── components/
│   ├── alerts/
│   │   ├── AlertCard.vue
│   │   ├── AlertTypeSelector.vue
│   │   ├── PriceAlertConfig.vue
│   │   ├── IntelligenceAlertConfig.vue
│   │   ├── CompoundAlertBuilder.vue
│   │   ├── DeliveryConfig.vue
│   │   └── BacktestResult.vue
│   │
│   └── notifications/
│       ├── NotificationToast.vue
│       ├── NotificationBell.vue
│       └── NotificationDropdown.vue
│
├── composables/
│   ├── useAlerts.ts
│   └── useNotifications.ts
│
└── types/
    └── alerts.ts
```

### WebSocket Integration

```typescript
// composables/useNotifications.ts
import { ref, onMounted, onUnmounted } from 'vue';
import Echo from 'laravel-echo';

export function useNotifications(userId: string) {
    const notifications = ref<AlertNotification[]>([]);
    const toastQueue = ref<AlertNotification[]>([]);
    const isConnected = ref(false);
    const reconnectAttempts = ref(0);
    const MAX_RECONNECT_DELAY = 30000; // 30 seconds

    let echoInstance: Echo;
    let reconnectTimeout: number | null = null;

    const setupAlertChannel = () => {
        echoInstance
            .private(`user.${userId}.alerts`)
            .listen('.alert.triggered', handleAlert)
            .error((error: Error) => {
                console.error('WebSocket error:', error);
                isConnected.value = false;
                scheduleReconnect();
            });

        // Mark as connected when subscription succeeds
        isConnected.value = true;
        reconnectAttempts.value = 0;
    };

    const handleAlert = (event: AlertNotification) => {
        notifications.value.unshift(event);

        if (['critical', 'high'].includes(event.priority)) {
            toastQueue.value.push(event);
        }

        if (event.priority === 'critical') {
            playSound();
        }
    };

    const scheduleReconnect = () => {
        if (reconnectTimeout) return;

        reconnectAttempts.value++;
        const delay = Math.min(
            Math.pow(2, reconnectAttempts.value) * 1000,
            MAX_RECONNECT_DELAY
        );

        console.log(`Reconnecting in ${delay}ms (attempt ${reconnectAttempts.value})`);

        reconnectTimeout = window.setTimeout(() => {
            reconnectTimeout = null;
            echoInstance.connector.connect();
            setupAlertChannel();
        }, delay);
    };

    const fetchMissedNotifications = async () => {
        // Fetch any notifications missed while disconnected
        const lastSeen = notifications.value[0]?.created_at;
        const response = await fetch(`/api/notifications?since=${lastSeen || ''}`);
        const missed = await response.json();

        if (missed.length > 0) {
            notifications.value = [...missed, ...notifications.value];
        }
    };

    const playSound = () => {
        const audio = new Audio('/sounds/alert-critical.mp3');
        audio.play().catch(() => {
            // Audio play failed (likely due to autoplay policy)
        });
    };

    // Handle tab visibility change
    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            // Tab became active - check for missed notifications
            fetchMissedNotifications();

            // Reconnect if disconnected
            if (!isConnected.value) {
                echoInstance.connector.connect();
                setupAlertChannel();
            }
        }
    };

    onMounted(() => {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        setupAlertChannel();
    });

    onUnmounted(() => {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout);
        }
    });

    return {
        notifications,
        toastQueue,
        isConnected,
        fetchMissedNotifications,
    };
}
```

---

## Localization Keys

```json
{
  "alerts": {
    "title": "Alerts",
    "heading": "Manage Your Alerts",
    "create": "Create Alert",
    "types": {
      "price": "Price",
      "prediction": "AI Prediction",
      "signal": "Technical Signal",
      "anomaly": "Anomaly",
      "pattern": "Chart Pattern",
      "recommendation": "Recommendation"
    },
    "triggers": {
      "target_price": "Target Price",
      "breakout": "Breakout",
      "zone": "Support/Resistance Zone",
      "gap": "Price Gap",
      "52week": "52-Week High/Low",
      "daily_change": "Daily % Change",
      "entry_return": "Return to Entry"
    },
    "status": {
      "active": "Active",
      "paused": "Paused",
      "triggered": "Triggered",
      "expired": "Expired"
    },
    "priority": {
      "critical": "Critical",
      "high": "High",
      "medium": "Medium",
      "low": "Low"
    }
  }
}
```

---

## Implementation Phases

### Phase 1: Foundation

- [ ] Create database migrations
- [ ] Create Eloquent models with relationships
- [ ] Create AlertController with CRUD
- [ ] Create Form Requests
- [ ] Create AlertPolicy
- [ ] Set up routes

### Phase 2: Alert Processing

- [ ] Create ProcessPriceAlerts job
- [ ] Create ProcessIntelligenceAlerts job
- [ ] Create ProcessScheduledAlerts job
- [ ] Integrate with existing Redis channels
- [ ] Create UserAlertConsumer in signal-consumers service
- [ ] Set up Laravel scheduler

### Phase 3: Notifications

- [ ] Create SendAlertNotification job
- [ ] Create TelegramChannel
- [ ] Create AlertTriggeredNotification
- [ ] Create notification templates (AR/EN)
- [ ] Set up Laravel Reverb broadcasting
- [ ] Create alerts:listen command

### Phase 4: Frontend

- [ ] Create Vue pages (Index, Create, Edit, History)
- [ ] Create alert components
- [ ] Create notification components
- [ ] Create useNotifications composable
- [ ] Integrate WebSocket (Echo + Reverb)
- [ ] Add toast notifications

### Phase 5: Advanced Features

- [ ] Compound alerts (AND/OR logic)
- [ ] Alert chains
- [ ] Backtest functionality
- [ ] Escalation system
- [ ] Digest notifications
- [ ] Alert templates

### Phase 6: Polish

- [ ] Rate limiting
- [ ] Smart defaults based on user profile
- [ ] Analytics dashboard
- [ ] Performance optimization

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Alert delivery latency | < 5 seconds |
| Notification delivery rate | > 99% |
| Daily active alert users | Track growth |
| Alerts per user | 3-5 average |
| Notification open rate | > 40% |
| False positive rate | < 5% |
| User complaints (spam) | < 1% |

---

## Dependencies

### Backend

- Laravel 12 (existing)
- Laravel Reverb (existing, needs activation)
- Redis (existing)
- PostgreSQL + TimescaleDB (existing)

### Frontend

- Vue 3 + Inertia.js v2 (existing)
- Laravel Echo (needs installation)
- pusher-js (needs installation)

### Services

- signal-consumers service (needs UserAlertConsumer)
- Telegram Bot (existing)

---

## Security Considerations

1. **Authorization**: AlertPolicy ensures users only access their own alerts
2. **Rate Limiting**: Prevent notification spam
3. **Input Validation**: Form Requests validate all parameters
4. **Channel Authorization**: Broadcast channels require authentication
5. **Sensitive Data**: No credentials in alert parameters

---

## Next Steps

1. Review and approve this design
2. Create implementation plan with specific tasks
3. Set up git worktree for isolated development
4. Begin Phase 1 implementation
