# Horin Alerts User Guide

## Introduction

Horin Alerts help you stay informed about market movements without constantly watching prices. Set up alerts once, and get notified when conditions are met.

## Alert Types

### Price Alerts
Monitor specific price conditions for any asset.

**Available Triggers:**
- **Target Price**: Alert when price reaches your target
- **Breakout**: Alert when price breaks through a support/resistance level
- **Zone**: Alert when price enters or exits a price range
- **Gap**: Alert on significant gap up/down at market open
- **52-Week High/Low**: Alert on new yearly extremes
- **Daily Change**: Alert when daily % change exceeds threshold
- **Entry Return**: Alert when price returns near your entry point

### Intelligence Alerts
Leverage AI and technical analysis for smarter alerts.

**Available Triggers:**
- **Signal**: Technical indicator signals (RSI, MACD, patterns)
- **Prediction**: AI price predictions with confidence thresholds
- **Anomaly**: Unusual market behavior detection
- **Pattern**: Chart pattern recognition (double bottom, head & shoulders, etc.)
- **Recommendation**: Changes in analyst recommendations
- **Compound**: Multiple conditions combined with AND/OR logic

## Creating an Alert

### Step 1: Choose Asset
Select the asset you want to monitor from:
- Your portfolio holdings
- Your watchlist
- Search any available asset

### Step 2: Select Alert Type
Choose between Price Alert or Intelligence Alert based on what you want to monitor.

### Step 3: Configure Trigger
Set up the specific trigger conditions:

**For Target Price:**
1. Enter your target price
2. Choose direction (Above, Below, or Both)

**For Breakout:**
1. Enter the breakout level
2. Choose direction
3. Optional: Require candle close confirmation

**For Zone:**
1. Enter zone boundaries (low and high)
2. Choose when to trigger (Enter zone, Exit zone, or Both)

**For Daily Change:**
1. Enter percentage threshold
2. Choose direction (Up, Down, or Either)

### Step 4: Set Priority
- **Critical**: Immediate notification with escalation
- **High**: Prominent notification
- **Medium**: Standard notification (default)
- **Low**: Quiet notification

### Step 5: Configure Delivery
Choose how to receive notifications:
- Telegram (recommended for instant alerts)
- Push notifications (mobile app)
- Email
- In-app notification center

### Step 6: Additional Options
- **Market Hours Only**: Only trigger during trading hours
- **Recurring**: Re-activate after triggering
- **Cooldown**: Minimum time between triggers
- **Max Triggers**: Limit total number of triggers
- **Expiration**: Auto-expire after date

## Managing Alerts

### View Alerts
Access your alerts from the Alerts page to see:
- Active alerts with current status
- Recently triggered alerts
- Alert statistics

### Edit Alert
Modify any alert configuration. Changes take effect immediately.

### Snooze Alert
Temporarily disable an alert:
- 1 hour
- 4 hours
- 1 day
- Until market close
- Until market open
- Custom duration

### Duplicate Alert
Create a copy of an existing alert to quickly set up similar alerts for different assets or price levels.

### Delete Alert
Permanently remove an alert. This action cannot be undone.

## Alert Chains

Chain alerts together for sequential monitoring.

**Example Use Case:**
1. Alert A: Notify when AAPL breaks $150
2. Alert B (chained): After A triggers, notify if AAPL drops below $145 within 24 hours

**Setting Up Chains:**
1. Create the follow-up alert with status "Chained"
2. Go to the trigger alert's detail page
3. Use the Chain Manager to link alerts
4. Configure delay and expiration

## Compound Alerts

Combine multiple conditions for sophisticated monitoring.

**AND Logic**: All conditions must be met
```
Signal strength > 70% AND Prediction confidence > 80%
```

**OR Logic**: Any condition triggers
```
Price > $150 OR RSI oversold signal
```

## Backtest Feature

Test how your alert would have performed historically.

**Running a Backtest:**
1. Go to alert detail page
2. Click "Run Backtest"
3. Choose lookback period (30, 60, or 90 days)
4. View results including:
   - Number of triggers
   - Average return after trigger (1d, 1w, 1m)
   - Win rate

## Alert History

View the complete history of an alert:
- When it triggered
- Trigger price/value
- Whether notification was sent
- Acknowledgment status

## Telegram Integration

### Setup
1. Connect Telegram in Settings
2. Start a chat with @HorinBot
3. Send `/start` to activate

### Creating Alerts via Telegram
1. Send `/alerts` or tap Alerts button
2. Tap "Create Alert"
3. Follow the guided flow:
   - Select alert type
   - Choose asset from portfolio/watchlist or search
   - Configure trigger
   - Confirm creation

### Managing via Telegram
- View active alerts
- Snooze/unsnooze
- Acknowledge triggered alerts
- Quick actions via inline buttons

## Best Practices

### Setting Effective Alerts

1. **Be Specific**: Set precise price targets rather than round numbers
2. **Use Zones**: For ranging markets, use zone alerts instead of single prices
3. **Layer Alerts**: Set multiple alerts at different levels
4. **Combine Intelligence**: Use compound alerts for higher conviction signals

### Avoiding Alert Fatigue

1. **Set Appropriate Thresholds**: Don't alert on every 1% move
2. **Use Cooldowns**: Prevent duplicate notifications
3. **Prioritize**: Use priority levels to focus attention
4. **Review Regularly**: Delete or modify alerts that no longer serve you

### For Active Traders

1. **Breakout Alerts**: Monitor key support/resistance levels
2. **Gap Alerts**: Catch overnight gaps at market open
3. **Signal Alerts**: Get notified on technical confirmations

### For Long-term Investors

1. **Entry Return**: Monitor positions approaching your cost basis
2. **52-Week Alerts**: Track new highs/lows in your watchlist
3. **Prediction Alerts**: Let AI flag significant forecast changes

## Troubleshooting

### Alert Not Triggering
- Check if alert is Active (not Paused or Snoozed)
- Verify market hours setting
- Ensure asset has price data
- Check if max triggers reached

### Not Receiving Notifications
- Verify delivery channels are configured
- Check Telegram bot is connected
- Enable push notifications in device settings
- Check email spam folder

### Alert Triggered Multiple Times
- Increase cooldown period
- Consider using non-recurring setting
- Set max triggers limit

## Limits

| Feature | Limit |
|---------|-------|
| Total Active Alerts | 100 per user |
| Alerts per Asset | 10 per asset |
| Backtests | 10 per hour |
| Chain Depth | 5 levels |

## Glossary

- **Trigger**: The condition that causes an alert to fire
- **Cooldown**: Minimum time between consecutive triggers
- **Escalation**: Automatic increase in notification urgency
- **Chain**: Linked alerts that activate sequentially
- **Compound**: Multiple conditions combined in one alert
- **Snooze**: Temporary deactivation of an alert
