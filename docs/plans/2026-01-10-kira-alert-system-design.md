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
┌─────────────────────────────────────────────────────────────────────┐
│                    EXISTING ML PIPELINE                             │
│  technical-analysis → signal-detection → signal-classification     │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│              ENHANCED signal-consumers SERVICE                      │
│  UserAlertConsumer: matches signals against user alerts            │
│  Publishes to 'user_alerts' Redis channel                          │
└─────────────────────────────────┬───────────────────────────────────┘
                                  │
                                  ▼
                          user_alerts (Redis)
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      LARAVEL BACKEND                                │
│  alerts:listen → SendAlertNotification → AlertTriggered (Broadcast)│
│       ↓                    ↓                      ↓                 │
│  Database            Telegram              Laravel Reverb           │
│  Notifications       Bot API              (WebSocket)               │
└─────────────────────────────────────────────────────────────────────┘
                                  │
                                  ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      VUE FRONTEND                                   │
│  Echo.private('user.{id}.alerts').listen('alert.triggered', ...)   │
└─────────────────────────────────────────────────────────────────────┘
```

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

The alert system integrates with existing Redis channels:

| Channel | Purpose |
|---------|---------|
| `classified_critical` | Immediate priority signals |
| `classified_high` | High priority signals |
| `classified_medium` | Medium priority signals |
| `classified_low` | Low priority signals |
| `trading_recommendations` | Recommendation updates |
| `user_alerts` | Triggered user alerts (new) |

---

## API Routes

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
echoInstance
    .private(`user.${userId}.alerts`)
    .listen('.alert.triggered', (event: AlertNotification) => {
        notifications.value.unshift(event);

        if (['critical', 'high'].includes(event.priority)) {
            toastQueue.value.push(event);
        }

        if (event.priority === 'critical') {
            playSound();
        }
    });
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
