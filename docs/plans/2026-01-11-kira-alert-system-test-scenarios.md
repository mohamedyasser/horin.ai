# Kira Alert System - Test Scenarios

**Date:** 2026-01-11
**Related:** [Kira Alert System Design](./2026-01-10-kira-alert-system-design.md)
**Status:** Ready for Implementation

---

## Overview

This document defines comprehensive test scenarios for the Kira Alert System. It covers unit tests, integration tests, edge cases, failure scenarios, and performance tests.

---

## 1. Unit Tests - Alert Matching

### 1.1 Price-Based Alerts

#### 1.1.1 Target Price Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| TP-001: Triggers when price crosses target from below | previous=49, current=51, target=50, direction=above | triggered=true, triggerValue=51 | High |
| TP-002: Triggers when price crosses target from above | previous=51, current=49, target=50, direction=below | triggered=true, triggerValue=49 | High |
| TP-003: Does NOT trigger when price moves up but below target | previous=48, current=49, target=50, direction=above | triggered=false | High |
| TP-004: Does NOT trigger when price moves down but above target | previous=52, current=51, target=50, direction=below | triggered=false | High |
| TP-005: Triggers on exact target price (direction=above) | previous=49.99, current=50.00, target=50, direction=above | triggered=true | Medium |
| TP-006: Triggers on exact target price (direction=below) | previous=50.01, current=50.00, target=50, direction=below | triggered=true | Medium |
| TP-007: Both direction triggers on cross up | previous=49, current=51, target=50, direction=both | triggered=true, direction_crossed=up | Medium |
| TP-008: Both direction triggers on cross down | previous=51, current=49, target=50, direction=both | triggered=true, direction_crossed=down | Medium |
| TP-009: Auto-direction infers above when target > current | current=45, target=50, auto_direction=true | direction=above | Medium |
| TP-010: Auto-direction infers below when target < current | current=55, target=50, auto_direction=true | direction=below | Medium |

#### 1.1.2 Breakout Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| BO-001: Triggers on clean breakout above level | previous=49.80, current=50.20, level=50, direction=above | triggered=true | High |
| BO-002: Triggers on clean breakdown below level | previous=50.20, current=49.80, level=50, direction=below | triggered=true | High |
| BO-003: Does NOT trigger on intraday touch without close | touch=50.10, close=49.90, level=50, direction=above, confirmation=sustained | triggered=false | High |
| BO-004: Sustained confirmation requires hold for N seconds | level=50, confirmation_seconds=30, holds_for=35 | triggered=true | Medium |
| BO-005: Anti-whipsaw prevents false breakout | first_break=50.10, retest=49.95, second_break=50.15, anti_whipsaw=true | triggered=false on first, true on second after retest | Medium |
| BO-006: Consecutive ticks confirmation | ticks=[50.05, 50.10, 50.08], consecutive_ticks=2, level=50 | triggered=true after 2nd tick | Medium |

#### 1.1.3 Support/Resistance Zone Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| ZN-001: Triggers when price enters zone from below | previous=47, current=48.50, zone_low=48, zone_high=52, trigger_on=enter | triggered=true | High |
| ZN-002: Triggers when price enters zone from above | previous=53, current=51, zone_low=48, zone_high=52, trigger_on=enter | triggered=true | High |
| ZN-003: Triggers when price exits zone (breaks support) | previous=48.50, current=47.80, zone_low=48, zone_high=52, trigger_on=exit | triggered=true | High |
| ZN-004: Triggers when price exits zone (breaks resistance) | previous=51.50, current=52.20, zone_low=48, zone_high=52, trigger_on=exit | triggered=true | High |
| ZN-005: Does NOT trigger while price remains in zone | previous=49, current=51, zone_low=48, zone_high=52 | triggered=false | Medium |
| ZN-006: Cooldown prevents repeated triggers | cooldown_hours=4, last_trigger=2h ago | can_trigger=false | Medium |

#### 1.1.4 Price Gap Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| GP-001: Detects gap up at market open | prev_close=50, open=52.50, gap_threshold=3% | triggered=true, gap_percent=5% | High |
| GP-002: Detects gap down at market open | prev_close=50, open=48, gap_threshold=3% | triggered=true, gap_percent=-4% | High |
| GP-003: Does NOT trigger on small gap | prev_close=50, open=51, gap_threshold=3% | triggered=false (2% < 3%) | High |
| GP-004: Both direction triggers on gap up | gap_percent=5%, direction=both | triggered=true | Medium |
| GP-005: Gap up only triggers for direction=above | gap_percent=5%, direction=above | triggered=true | Medium |
| GP-006: Gap up does NOT trigger for direction=below | gap_percent=5%, direction=below | triggered=false | Medium |

#### 1.1.5 52-Week High/Low Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| 52-001: Triggers on new 52-week high | current=55, 52w_high=54, type=high | triggered=true | High |
| 52-002: Triggers on new 52-week low | current=20, 52w_low=21, type=low | triggered=true | High |
| 52-003: Does NOT trigger when equal to 52-week high | current=54, 52w_high=54, type=high | triggered=false (not new) | Medium |
| 52-004: Cooldown prevents daily repeat triggers | cooldown_hours=24, last_trigger=12h ago | can_trigger=false | Medium |

#### 1.1.6 Daily % Change Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| DC-001: Triggers on large positive daily change | open=50, current=55, threshold=5%, direction=both | triggered=true, change=10% | High |
| DC-002: Triggers on large negative daily change | open=50, current=45, threshold=5%, direction=both | triggered=true, change=-10% | High |
| DC-003: Does NOT trigger on small change | open=50, current=51, threshold=5% | triggered=false (2% < 5%) | High |
| DC-004: Direction above only triggers positive | change=+7%, direction=above, threshold=5% | triggered=true | Medium |
| DC-005: Direction below only triggers negative | change=-7%, direction=below, threshold=5% | triggered=true | Medium |
| DC-006: From reference=prev_close uses previous close | prev_close=48, current=55, from_reference=prev_close | change calculated from 48 | Medium |

#### 1.1.7 Entry Return Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| ER-001: Triggers when price returns to entry | entry=50, current=50.20, tolerance=0.5% | triggered=true | High |
| ER-002: Does NOT trigger outside tolerance | entry=50, current=51, tolerance=0.5% | triggered=false (2% > 0.5%) | High |
| ER-003: Tolerance works for below entry | entry=50, current=49.80, tolerance=0.5% | triggered=true | Medium |
| ER-004: Manual entry source uses provided value | entry_price=45, source=manual | uses 45 | Medium |
| ER-005: Portfolio entry uses average cost | user_holding.avg_cost=47.50, source=portfolio | uses 47.50 | Medium |

---

### 1.2 Intelligence-Based Alerts

#### 1.2.1 Prediction Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| PR-001: Triggers on high confidence up prediction | prediction_direction=up, confidence=0.85, min_confidence=0.75 | triggered=true | High |
| PR-002: Does NOT trigger on low confidence | prediction_direction=up, confidence=0.60, min_confidence=0.75 | triggered=false | High |
| PR-003: Direction filter works | prediction_direction=down, alert_direction=up | triggered=false | High |
| PR-004: Predicted change threshold | predicted_change=3%, min_predicted_change=2% | triggered=true | Medium |
| PR-005: Horizon filter (1hour predictions only) | horizon=1hour, alert_horizon=1hour | triggered=true | Medium |
| PR-006: Horizon mismatch | horizon=1day, alert_horizon=1hour | triggered=false | Medium |

#### 1.2.2 Signal Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| SG-001: Triggers on matching indicator signal | indicator=RSI, signal_type=oversold, alert_indicators=[RSI] | triggered=true | High |
| SG-002: Does NOT trigger on non-matching indicator | indicator=MACD, alert_indicators=[RSI, Bollinger] | triggered=false | High |
| SG-003: Signal type filter | signal_type=bullish_cross, alert_signal_types=[oversold] | triggered=false | High |
| SG-004: Min strength filter | strength=0.65, min_strength=0.7 | triggered=false | Medium |
| SG-005: Any_or_all=any triggers on first match | matched_indicators=1/3, any_or_all=any | triggered=true | Medium |
| SG-006: Any_or_all=all requires all matches | matched_indicators=2/3, any_or_all=all | triggered=false | Medium |

#### 1.2.3 Anomaly Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| AN-001: Triggers on matching anomaly type | anomaly_type=price_spike, alert_types=[price_spike, volume_surge] | triggered=true | High |
| AN-002: Confidence filter | anomaly_confidence=0.75, min_confidence=0.8 | triggered=false | High |
| AN-003: Severity filter | severity=medium, alert_severity=[high, critical] | triggered=false | High |
| AN-004: Multiple anomaly types in single event | event_types=[price_spike, unusual_pattern], alert_types=[price_spike] | triggered=true | Medium |

#### 1.2.4 Pattern Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| PT-001: Triggers on confirmed pattern match | pattern=double_bottom, status=confirmed, alert_patterns=[double_bottom] | triggered=true | High |
| PT-002: Does NOT trigger on forming pattern | pattern=double_bottom, status=forming, alert_status=confirmed | triggered=false | High |
| PT-003: Direction bias filter | pattern=head_shoulders (bearish), direction_bias=bullish | triggered=false | Medium |
| PT-004: Pattern confidence filter | pattern_confidence=0.65, min_confidence=0.7 | triggered=false | Medium |

#### 1.2.5 Recommendation Alert

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| RC-001: Triggers on new strong_buy recommendation | recommendation=strong_buy, alert_recommendations=[strong_buy, buy] | triggered=true | High |
| RC-002: Triggers on recommendation change | prev=hold, new=buy, trigger_on=change | triggered=true | High |
| RC-003: Does NOT trigger when recommendation unchanged | prev=buy, new=buy, trigger_on=change | triggered=false | High |
| RC-004: Min score filter | score=0.70, min_score=0.75 | triggered=false | Medium |
| RC-005: Downgrade notification | prev=buy, new=sell, notify_downgrades=true | triggered=true, is_downgrade=true | Medium |

---

### 1.3 Compound Alerts

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| CP-001: AND logic - all conditions must match | condition1=true, condition2=true, condition3=true, logic=and | triggered=true | High |
| CP-002: AND logic - partial match fails | condition1=true, condition2=false, condition3=true, logic=and | triggered=false | High |
| CP-003: OR logic - any condition triggers | condition1=false, condition2=true, condition3=false, logic=or | triggered=true | High |
| CP-004: OR logic - none match fails | condition1=false, condition2=false, condition3=false, logic=or | triggered=false | High |
| CP-005: Nested compound alert | (A AND B) OR C, A=true, B=false, C=true | triggered=true | Medium |

---

## 2. Unit Tests - Alert Lifecycle

### 2.1 Cooldown & Rate Limiting

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| CL-001: Respects cooldown period | cooldown=60min, last_triggered=30min ago | can_trigger=false | High |
| CL-002: Allows trigger after cooldown expires | cooldown=60min, last_triggered=65min ago | can_trigger=true | High |
| CL-003: Respects max_triggers limit | max_triggers=3, triggered_count=3 | status changes to triggered (done) | High |
| CL-004: Recurring alert resets | is_recurring=true, triggered_count=1 | can_trigger=true (after cooldown) | Medium |
| CL-005: User hourly rate limit | user_alerts_this_hour=10, max_per_hour=10 | rate_limited=true | Medium |
| CL-006: User daily rate limit | user_alerts_today=50, max_per_day=50 | rate_limited=true | Medium |

### 2.2 Snooze Functionality

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| SN-001: Snoozed alert does not trigger | snoozed_until=1h from now | can_trigger=false | High |
| SN-002: Alert triggers after snooze expires | snoozed_until=1h ago | can_trigger=true | High |
| SN-003: Preset 1h sets correct snooze time | preset=1h | snoozed_until=now+1h | Medium |
| SN-004: Preset until_market_close sets correct time | preset=until_market_close, now=12:00 Cairo | snoozed_until=14:30 Cairo | Medium |
| SN-005: Preset until_market_open on weekend | preset=until_market_open, now=Friday 15:00 | snoozed_until=Sunday 10:00 | Medium |

### 2.3 Expiration

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| EX-001: Expired alert does not trigger | expires_at=yesterday | status=expired, can_trigger=false | High |
| EX-002: Alert with no expiry triggers | expires_at=null | can_trigger=true | High |
| EX-003: Alert expires at exact time | expires_at=now | status changes to expired | Medium |

### 2.4 Market Hours

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| MH-001: market_hours_only=true blocks outside hours | market_hours_only=true, time=08:00 Cairo | can_trigger=false | High |
| MH-002: market_hours_only=true allows during hours | market_hours_only=true, time=11:00 Cairo | can_trigger=true | High |
| MH-003: market_hours_only=false allows anytime | market_hours_only=false, time=20:00 Cairo | can_trigger=true | Medium |
| MH-004: Weekend is not market hours | time=Saturday 11:00 Cairo | is_market_hours=false | Medium |

---

## 3. Unit Tests - Scope Resolution

| Test Case | Input | Expected | Priority |
|-----------|-------|----------|----------|
| SC-001: single_asset scope matches exact asset | scope=single_asset, alert_asset=A, event_asset=A | matches=true | High |
| SC-002: single_asset scope rejects other asset | scope=single_asset, alert_asset=A, event_asset=B | matches=false | High |
| SC-003: watchlist scope includes user watchlist assets | scope=watchlist, user_watchlist=[A,B,C], event_asset=B | matches=true | High |
| SC-004: watchlist scope excludes non-watchlist assets | scope=watchlist, user_watchlist=[A,B,C], event_asset=D | matches=false | High |
| SC-005: portfolio scope includes holdings | scope=portfolio, user_holdings=[X,Y], event_asset=X | matches=true | High |
| SC-006: sector scope includes sector assets | scope=sector, sector_id=banks, event_asset=COMI (bank) | matches=true | Medium |
| SC-007: market scope includes all active assets | scope=market, event_asset=any_active | matches=true | Medium |

---

## 4. Integration Tests - Alert Processing

### 4.1 Price Alert Processing

| Test Case | Description | Priority |
|-----------|-------------|----------|
| IP-001: Process price alerts creates history on trigger | Create alert, update price to trigger, verify alert_history created | High |
| IP-002: Process price alerts sends notification | Create alert, trigger it, verify notification dispatched | High |
| IP-003: Process price alerts updates last_triggered_at | Create alert, trigger it, verify timestamp updated | High |
| IP-004: Process price alerts respects cooldown | Create alert, trigger it, update price again immediately, verify no second trigger | High |
| IP-005: Batch processing handles multiple alerts | Create 100 alerts, process batch, verify all evaluated | Medium |

### 4.2 Intelligence Alert Processing

| Test Case | Description | Priority |
|-----------|-------------|----------|
| II-001: Signal alert triggers from Redis pub/sub | Publish signal to classified_high channel, verify alert triggered | High |
| II-002: Anomaly alert triggers from anomaly_alerts channel | Publish anomaly event, verify alert triggered | High |
| II-003: Pattern alert triggers from pattern_updates channel | Publish pattern event, verify alert triggered | High |
| II-004: Recommendation alert triggers on change | Update recommendation, verify alert triggered | High |

### 4.3 Scheduled Alert Processing

| Test Case | Description | Priority |
|-----------|-------------|----------|
| IS-001: Gap alerts process at market open | Simulate market open, verify gap alerts checked | High |
| IS-002: 52-week alerts process at market close | Simulate market close, verify 52-week alerts checked | High |
| IS-003: Daily change alerts process at market close | Simulate market close, verify daily change alerts checked | High |

### 4.4 Alert Chain Processing

| Test Case | Description | Priority |
|-----------|-------------|----------|
| IC-001: Chain activates dependent alert | Alert A triggers, verify Alert B activated | High |
| IC-002: Chain respects delay_minutes | Alert A triggers with delay=5, verify Alert B activated after 5 min | Medium |
| IC-003: Chain expires_after_minutes works | Alert B activated with expires_after=60, verify expiry set | Medium |

---

## 5. Integration Tests - Notifications

### 5.1 Notification Delivery

| Test Case | Description | Priority |
|-----------|-------------|----------|
| ND-001: Telegram notification sent | Trigger alert with telegram enabled, verify Telegram API called | High |
| ND-002: In-app notification created | Trigger alert, verify notifications table has record | High |
| ND-003: Push notification sent | Trigger alert with push enabled, verify push service called | High |
| ND-004: Email notification sent | Trigger alert with email enabled, verify email queued | Medium |
| ND-005: Multiple channels receive notification | Trigger alert with all channels, verify all received | High |

### 5.2 Notification Content

| Test Case | Description | Priority |
|-----------|-------------|----------|
| NC-001: English notification content correct | Trigger alert for English user, verify EN content | High |
| NC-002: Arabic notification content correct | Trigger alert for Arabic user, verify AR content | High |
| NC-003: Notification includes trigger value | Trigger target price alert, verify price in message | High |
| NC-004: Notification includes deep link | Verify notification data contains correct URLs | Medium |

### 5.3 Delivery Preferences

| Test Case | Description | Priority |
|-----------|-------------|----------|
| DP-001: Quiet hours blocks non-critical | trigger during quiet hours, priority=medium, verify blocked | High |
| DP-002: Quiet hours allows critical | trigger during quiet hours, priority=critical, verify sent | High |
| DP-003: Channel priority respected | Telegram priority=1, Push priority=2, verify order | Medium |
| DP-004: Digest batches low priority | priority=low, digest_only=true, verify batched | Medium |

### 5.4 Escalation

| Test Case | Description | Priority |
|-----------|-------------|----------|
| ES-001: Escalation triggers after timeout | notification not acknowledged, delay passed, verify escalation | High |
| ES-002: Acknowledgement stops escalation | acknowledge notification, verify no escalation | High |
| ES-003: Max escalations respected | escalation_level=max, verify no further escalation | Medium |

---

## 6. Integration Tests - WebSocket

| Test Case | Description | Priority |
|-----------|-------------|----------|
| WS-001: Alert broadcast to correct user channel | Trigger alert for user A, verify only user A receives | High |
| WS-002: Real-time notification appears in frontend | Trigger alert, verify Vue component receives event | High |
| WS-003: Toast shown for high priority | Trigger high priority alert, verify toast displayed | Medium |
| WS-004: Sound played for critical | Trigger critical alert, verify sound played | Low |

---

## 7. Integration Tests - Backtest

| Test Case | Description | Priority |
|-----------|-------------|----------|
| BT-001: Backtest returns trigger count | Run backtest with historical triggers, verify count | High |
| BT-002: Backtest calculates returns correctly | Run backtest, verify 1d/1w/1m returns calculated | High |
| BT-003: Backtest respects lookback_days | lookback_days=30, verify only 30 days of data used | Medium |
| BT-004: Backtest result stored in database | Run backtest, verify result saved | Medium |
| BT-005: Win rate calculated correctly | Multiple triggers with varying performance, verify win_rate | Medium |

---

## 8. Edge Cases & Boundary Conditions

### 8.1 Data Edge Cases

| Test Case | Description | Priority |
|-----------|-------------|----------|
| EC-001: Handle missing price data | Asset has no recent prices, verify graceful handling | High |
| EC-002: Handle null previous price | First price of day, previous=null, verify no crash | High |
| EC-003: Handle extreme price values | Price = 0.001 or 999999, verify no overflow | Medium |
| EC-004: Handle negative change percentage | Large drop to near-zero, verify correct calculation | Medium |
| EC-005: Handle midnight timezone transitions | Alert created at 23:59 Cairo, verify correct day | Medium |

### 8.2 Concurrent Processing

| Test Case | Description | Priority |
|-----------|-------------|----------|
| CC-001: Same alert not triggered twice concurrently | Two processes evaluate same alert, verify only one trigger | High |
| CC-002: User rate limit enforced under concurrency | Multiple alerts fire simultaneously, verify limit respected | High |
| CC-003: Idempotency key prevents duplicate notifications | Same trigger processed twice, verify one notification | High |

### 8.3 User Edge Cases

| Test Case | Description | Priority |
|-----------|-------------|----------|
| UC-001: User with no telegram_id skips telegram channel | User.telegram_id=null, verify no telegram error | High |
| UC-002: User with invalid push token handles gracefully | Push fails with invalid token, verify token cleared | High |
| UC-003: User hits max alerts limit | User has 100 active alerts (max), verify create blocked | Medium |
| UC-004: Deleted user alerts not processed | User soft-deleted, verify alerts skipped | Medium |

### 8.4 Alert Configuration Edge Cases

| Test Case | Description | Priority |
|-----------|-------|----------|
| AC-001: Empty parameters object handled | parameters={}, verify default behavior | Medium |
| AC-002: Missing optional parameters | Only required params provided, verify defaults used | Medium |
| AC-003: Invalid trigger_type rejected | trigger_type="invalid", verify validation error | High |
| AC-004: Compound alert with empty conditions | conditions=[], verify validation error | Medium |

---

## 9. Failure Scenarios & Recovery

### 9.1 Redis Failures

| Test Case | Description | Expected Behavior | Priority |
|-----------|-------------|-------------------|----------|
| RF-001: Redis connection lost during subscription | AlertsListen command | Exponential backoff reconnect | High |
| RF-002: Redis connection lost during cache read | ProcessPriceAlerts job | Fall back to database query | High |
| RF-003: Redis down for extended period | Multiple reconnect attempts | Alert ops team after 5 failures | High |

### 9.2 Notification Delivery Failures

| Test Case | Description | Expected Behavior | Priority |
|-----------|-------------|-------------------|----------|
| NF-001: Telegram rate limit hit | 429 response from Telegram | Respect Retry-After, re-queue | High |
| NF-002: Telegram bad request | 400 response | Don't retry, log error | High |
| NF-003: Push token invalid | FCM returns invalid token | Clear user's push token | High |
| NF-004: Email delivery fails | SMTP error | Retry 3 times with backoff | Medium |
| NF-005: All retries exhausted | 3 failures | Move to dead letter queue | High |

### 9.3 Database Failures

| Test Case | Description | Expected Behavior | Priority |
|-----------|-------------|-------------------|----------|
| DF-001: Database connection lost | During alert evaluation | Job fails, queued for retry | High |
| DF-002: Transaction deadlock | Concurrent updates | Retry transaction | Medium |
| DF-003: Alert history insert fails | Database error | Alert still marked triggered, log error | High |

### 9.4 Queue Failures

| Test Case | Description | Expected Behavior | Priority |
|-----------|-------------|-------------------|----------|
| QF-001: Queue worker crash mid-job | Worker dies | Job returns to queue, processed by another worker | High |
| QF-002: Queue backlog builds up | > 1000 jobs pending | Health check alerts ops team | High |

---

## 10. Performance & Load Tests

### 10.1 Alert Matching Performance

| Test Case | Target | Priority |
|-----------|--------|----------|
| PM-001: Evaluate 1000 price alerts in < 1 second | p99 < 1000ms | High |
| PM-002: Evaluate 100 compound alerts in < 500ms | p99 < 500ms | High |
| PM-003: Cache hit rate > 90% for active alerts | 90% cache hits | Medium |

### 10.2 Notification Throughput

| Test Case | Target | Priority |
|-----------|--------|----------|
| NT-001: Send 100 Telegram notifications/minute | 100/min sustained | High |
| NT-002: Broadcast 1000 WebSocket events/minute | 1000/min sustained | High |
| NT-003: Process notification queue without backlog | Queue depth < 100 | High |

### 10.3 Concurrent Users

| Test Case | Target | Priority |
|-----------|--------|----------|
| CU-001: Support 10,000 active alerts simultaneously | No degradation | High |
| CU-002: Support 1,000 concurrent WebSocket connections | All receive events | High |
| CU-003: Support 100 alerts/second creation rate | No errors | Medium |

### 10.4 Latency Requirements

| Test Case | Target | Priority |
|-----------|--------|----------|
| LT-001: Price to notification latency | < 5 seconds p99 | High |
| LT-002: ML signal to notification latency | < 3 seconds p99 | High |
| LT-003: WebSocket event delivery latency | < 1 second p99 | High |

---

## 11. Security Tests

| Test Case | Description | Priority |
|-----------|-------------|----------|
| SEC-001: User cannot access other user's alerts | API returns 403 for other user's alert | High |
| SEC-002: User cannot trigger other user's alerts | POST to trigger fails authorization | High |
| SEC-003: WebSocket channel authorization | User A cannot subscribe to User B's channel | High |
| SEC-004: Alert parameters sanitized | XSS attempt in parameters blocked | High |
| SEC-005: Rate limiting prevents abuse | Excessive requests return 429 | High |
| SEC-006: SQL injection prevented | Malicious input in search sanitized | High |

---

## 12. API Endpoint Tests

### 12.1 AlertController

| Endpoint | Test Case | Priority |
|----------|-----------|----------|
| GET /alerts | Returns user's alerts only | High |
| GET /alerts | Pagination works correctly | Medium |
| GET /alerts | Filtering by status works | Medium |
| GET /alerts | Filtering by type works | Medium |
| GET /alerts/{id} | Returns single alert | High |
| GET /alerts/{id} | Returns 404 for non-existent | High |
| GET /alerts/{id} | Returns 403 for other user's alert | High |
| POST /alerts | Creates alert with valid data | High |
| POST /alerts | Returns validation errors for invalid data | High |
| POST /alerts | Respects user's max alerts limit | Medium |
| PUT /alerts/{id} | Updates alert | High |
| PUT /alerts/{id} | Returns 403 for other user's alert | High |
| DELETE /alerts/{id} | Soft deletes alert | High |
| POST /alerts/{id}/snooze | Snoozes alert | High |
| POST /alerts/{id}/snooze | Validates duration | Medium |
| POST /alerts/{id}/backtest | Queues backtest job | High |
| GET /alerts/{id}/backtest/results | Returns backtest results | Medium |

### 12.2 AlertHistoryController

| Endpoint | Test Case | Priority |
|----------|-----------|----------|
| GET /alerts/history | Returns user's history | High |
| GET /alerts/history | Date range filtering works | Medium |
| POST /alerts/history/{id}/acknowledge | Marks as acknowledged | High |

### 12.3 AlertPreferencesController

| Endpoint | Test Case | Priority |
|----------|-----------|----------|
| GET /settings/alerts | Returns user preferences | High |
| PATCH /settings/alerts | Updates preferences | High |
| PATCH /settings/alerts | Validates quiet hours format | Medium |

---

## 13. Frontend Tests

### 13.1 Component Tests

| Component | Test Case | Priority |
|-----------|-----------|----------|
| AlertCard | Displays alert info correctly | High |
| AlertCard | Edit button navigates to edit page | Medium |
| AlertCard | Delete button shows confirmation | Medium |
| AlertCard | Snooze dropdown works | Medium |
| AlertTypeSelector | All types selectable | High |
| AlertTypeSelector | Selection updates parent | High |
| PriceAlertConfig | Form validation works | High |
| PriceAlertConfig | Direction toggle works | Medium |
| IntelligenceAlertConfig | Indicator multi-select works | High |
| DeliveryConfig | Channel toggles work | High |
| DeliveryConfig | Escalation config shows when enabled | Medium |
| BacktestResult | Displays results correctly | Medium |
| NotificationToast | Shows on new alert | High |
| NotificationToast | Dismisses after timeout | Medium |

### 13.2 Page Tests

| Page | Test Case | Priority |
|------|-----------|----------|
| Index | Lists user's alerts | High |
| Index | Empty state shown when no alerts | Medium |
| Index | Filter controls work | Medium |
| Create | Wizard navigation works | High |
| Create | Form submission creates alert | High |
| Create | Validation errors displayed | High |
| Edit | Loads existing alert data | High |
| Edit | Updates alert on submit | High |
| History | Lists triggered alerts | High |
| History | Acknowledge button works | Medium |

### 13.3 WebSocket Integration

| Test Case | Description | Priority |
|-----------|-------------|----------|
| FWS-001: Connection established on mount | Echo connects on page load | High |
| FWS-002: Reconnection on disconnect | Connection restored after drop | High |
| FWS-003: Missed notifications fetched | After reconnect, missed alerts loaded | Medium |
| FWS-004: Tab visibility reconnect | Reconnects when tab becomes active | Medium |

---

## 14. Test Data Factories

### Alert Factory States

```php
Alert::factory()->targetPrice();      // Target price alert
Alert::factory()->breakout();         // Breakout alert
Alert::factory()->zone();             // Zone alert
Alert::factory()->gap();              // Gap alert
Alert::factory()->fiftyTwoWeek();     // 52-week alert
Alert::factory()->dailyChange();      // Daily change alert
Alert::factory()->entryReturn();      // Entry return alert
Alert::factory()->prediction();       // Prediction alert
Alert::factory()->signal();           // Signal alert
Alert::factory()->anomaly();          // Anomaly alert
Alert::factory()->pattern();          // Pattern alert
Alert::factory()->recommendation();   // Recommendation alert
Alert::factory()->compound();         // Compound alert
Alert::factory()->triggered();        // Already triggered
Alert::factory()->snoozed();          // Currently snoozed
Alert::factory()->expired();          // Expired alert
Alert::factory()->paused();           // Paused alert
Alert::factory()->watchlistScope();   // Watchlist scope
Alert::factory()->portfolioScope();   // Portfolio scope
```

### Test Helpers

```php
// Simulate price update
$this->updateAssetPrice($asset, 52.50);

// Simulate ML signal
$this->publishSignal($asset, [
    'indicator' => 'RSI',
    'signal_type' => 'oversold',
    'strength' => 0.85,
]);

// Simulate market open
$this->travelTo(Carbon::parse('10:00', 'Africa/Cairo'));

// Simulate market close
$this->travelTo(Carbon::parse('14:30', 'Africa/Cairo'));
```

---

## 15. Test Coverage Requirements

| Category | Minimum Coverage |
|----------|-----------------|
| Alert Matching Logic | 95% |
| Alert Processing Jobs | 90% |
| Notification Delivery | 85% |
| API Controllers | 90% |
| Form Requests | 100% |
| Policies | 100% |
| Models | 80% |

---

## 16. Test Execution Plan

### Phase 1: Unit Tests
- [ ] All alert matching tests (Section 1)
- [ ] All lifecycle tests (Section 2)
- [ ] All scope resolution tests (Section 3)

### Phase 2: Integration Tests
- [ ] Alert processing tests (Section 4)
- [ ] Notification tests (Section 5)
- [ ] WebSocket tests (Section 6)
- [ ] Backtest tests (Section 7)

### Phase 3: Edge Cases & Failures
- [ ] Edge case tests (Section 8)
- [ ] Failure scenario tests (Section 9)

### Phase 4: Performance & Security
- [ ] Performance tests (Section 10)
- [ ] Security tests (Section 11)

### Phase 5: API & Frontend
- [ ] API endpoint tests (Section 12)
- [ ] Frontend component tests (Section 13)

---

## Appendix: Test File Structure

```
tests/
├── Unit/
│   └── Alerts/
│       ├── Matching/
│       │   ├── TargetPriceMatcherTest.php
│       │   ├── BreakoutMatcherTest.php
│       │   ├── ZoneMatcherTest.php
│       │   ├── GapMatcherTest.php
│       │   ├── FiftyTwoWeekMatcherTest.php
│       │   ├── DailyChangeMatcherTest.php
│       │   ├── EntryReturnMatcherTest.php
│       │   ├── PredictionMatcherTest.php
│       │   ├── SignalMatcherTest.php
│       │   ├── AnomalyMatcherTest.php
│       │   ├── PatternMatcherTest.php
│       │   ├── RecommendationMatcherTest.php
│       │   └── CompoundMatcherTest.php
│       ├── AlertCooldownTest.php
│       ├── AlertSnoozeTest.php
│       ├── AlertExpirationTest.php
│       ├── AlertMarketHoursTest.php
│       └── AlertScopeResolverTest.php
├── Feature/
│   └── Alerts/
│       ├── AlertControllerTest.php
│       ├── AlertHistoryControllerTest.php
│       ├── AlertPreferencesControllerTest.php
│       ├── ProcessPriceAlertsTest.php
│       ├── ProcessIntelligenceAlertsTest.php
│       ├── ProcessScheduledAlertsTest.php
│       ├── ProcessAlertChainsTest.php
│       ├── SendAlertNotificationTest.php
│       ├── AlertBacktestTest.php
│       ├── AlertEscalationTest.php
│       └── AlertBroadcastingTest.php
├── Integration/
│   └── Alerts/
│       ├── RedisSubscriberTest.php
│       ├── NotificationDeliveryTest.php
│       └── WebSocketIntegrationTest.php
└── Browser/
    └── Alerts/
        ├── AlertCreateWizardTest.php
        ├── AlertListingTest.php
        └── AlertNotificationTest.php
```
