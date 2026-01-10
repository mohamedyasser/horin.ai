# Kira Alert System - Notification Examples

**Date:** 2026-01-10
**Related Document:** `2026-01-10-kira-alert-system-design.md`
**Purpose:** Full notification message examples for each alert type

---

## Overview

This document provides complete notification message examples for all 13 alert types across all delivery channels. Each example includes both Arabic and English versions with appropriate formatting for each channel.

---

## Message Format Standards

### Priority Icons

| Priority | Icon | Description |
|----------|------|-------------|
| Critical | :rotating_light: | Immediate action required |
| High | :dart: | Important alert |
| Medium | :bell: | Standard notification |
| Low | :information_source: | Informational |

### Channel Characteristics

| Channel | Max Length | Rich Text | Links | Images |
|---------|------------|-----------|-------|--------|
| Telegram | 4096 chars | Markdown | Yes | Yes |
| In-App | Unlimited | HTML | Yes | Yes |
| Push | 250 chars | No | Deep link | No |
| Email | Unlimited | HTML | Yes | Yes |

---

## Price-Based Alerts

### 1. Target Price Alert

#### Telegram - English (High Priority)

```
:dart: *Target Price Reached*
━━━━━━━━━━━━━━━━━━

*COMI* - Commercial International Bank

:chart_with_upwards_trend: Current Price: *52.50 EGP*
:dart: Target: 52.00 EGP (Above)
:chart_increasing: Change: +4.2% today

Your alert triggered at 52.00 EGP.
The stock crossed your target from below.

:clock1: 10:45 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Stock](https://kira.app/ar/assets/COMI) · [Manage Alert](https://kira.app/alerts/123)
```

#### Telegram - Arabic (High Priority)

```
:dart: *وصول السعر المستهدف*
━━━━━━━━━━━━━━━━━━

*COMI* - البنك التجاري الدولي

:chart_with_upwards_trend: السعر الحالي: *52.50 ج.م*
:dart: الهدف: 52.00 ج.م (أعلى)
:chart_increasing: التغير: +4.2% اليوم

تم تفعيل التنبيه عند 52.00 ج.م.
السهم تجاوز هدفك صعوداً.

:clock1: 10:45 ص · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض السهم](https://kira.app/ar/assets/COMI) · [إدارة التنبيه](https://kira.app/alerts/123)
```

#### Push Notification - English

```
Title: COMI reached 52.00 EGP
Body: Target price hit! Now trading at 52.50 EGP (+4.2%)
```

#### Push Notification - Arabic

```
Title: COMI وصل إلى 52.00 ج.م
Body: تم الوصول للسعر المستهدف! يتداول حالياً عند 52.50 ج.م (+4.2%)
```

#### In-App Notification

```json
{
  "type": "alert.triggered",
  "icon": "target",
  "priority": "high",
  "title": {
    "en": "Target Price Reached",
    "ar": "وصول السعر المستهدف"
  },
  "body": {
    "en": "COMI reached your target of 52.00 EGP. Current price: 52.50 EGP",
    "ar": "COMI وصل لهدفك عند 52.00 ج.م. السعر الحالي: 52.50 ج.م"
  },
  "asset": {
    "symbol": "COMI",
    "name_en": "Commercial International Bank",
    "name_ar": "البنك التجاري الدولي"
  },
  "data": {
    "target_price": 52.00,
    "current_price": 52.50,
    "direction": "above",
    "change_percent": 4.2
  },
  "actions": [
    { "label": "View Stock", "url": "/ar/assets/COMI" },
    { "label": "Manage Alert", "url": "/alerts/123" }
  ],
  "timestamp": "2026-01-10T10:45:00+02:00"
}
```

---

### 2. Breakout Alert

#### Telegram - English (High Priority)

```
:rocket: *Breakout Confirmed*
━━━━━━━━━━━━━━━━━━

*HRHO* - Hermes Holding

:chart_with_upwards_trend: Current Price: *15.85 EGP*
:triangular_flag_on_post: Breakout Level: 15.50 EGP
:arrow_up: Direction: Above (Confirmed)
:chart_increasing: Volume: 2.5x average

Price sustained above breakout level for 30+ seconds.
Strong buying pressure detected.

:clock1: 11:23 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Stock](https://kira.app/ar/assets/HRHO) · [Manage Alert](https://kira.app/alerts/456)
```

#### Telegram - Arabic (High Priority)

```
:rocket: *اختراق مؤكد*
━━━━━━━━━━━━━━━━━━

*HRHO* - القابضة المصرية الكويتية

:chart_with_upwards_trend: السعر الحالي: *15.85 ج.م*
:triangular_flag_on_post: مستوى الاختراق: 15.50 ج.م
:arrow_up: الاتجاه: أعلى (مؤكد)
:chart_increasing: الحجم: 2.5 ضعف المتوسط

السعر استقر فوق مستوى الاختراق لأكثر من 30 ثانية.
تم رصد ضغط شراء قوي.

:clock1: 11:23 ص · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض السهم](https://kira.app/ar/assets/HRHO) · [إدارة التنبيه](https://kira.app/alerts/456)
```

#### Push Notification - English

```
Title: HRHO Breakout Confirmed!
Body: Broke above 15.50 EGP with 2.5x volume. Now at 15.85 EGP
```

#### Push Notification - Arabic

```
Title: اختراق مؤكد لـ HRHO!
Body: اخترق 15.50 ج.م بحجم 2.5 ضعف. حالياً 15.85 ج.م
```

---

### 3. Support/Resistance Zone Alert

#### Telegram - English (Medium Priority)

```
:shield: *Entered Support Zone*
━━━━━━━━━━━━━━━━━━

*SWDY* - Elsewedy Electric

:chart_with_downwards_trend: Current Price: *28.75 EGP*
:pushpin: Zone: 28.00 - 29.00 EGP (Support)
:arrow_down: Entry: From Above

Price entered your defined support zone.
Watch for potential bounce or breakdown.

:clock1: 12:15 PM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Stock](https://kira.app/ar/assets/SWDY) · [Manage Alert](https://kira.app/alerts/789)
```

#### Telegram - Arabic (Medium Priority)

```
:shield: *دخول منطقة الدعم*
━━━━━━━━━━━━━━━━━━

*SWDY* - السويدي إليكتريك

:chart_with_downwards_trend: السعر الحالي: *28.75 ج.م*
:pushpin: المنطقة: 28.00 - 29.00 ج.م (دعم)
:arrow_down: الدخول: من الأعلى

السعر دخل منطقة الدعم المحددة.
راقب احتمالية الارتداد أو الكسر.

:clock1: 12:15 م · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض السهم](https://kira.app/ar/assets/SWDY) · [إدارة التنبيه](https://kira.app/alerts/789)
```

#### Zone Exit Variant - English

```
:shield: *Exited Resistance Zone*
━━━━━━━━━━━━━━━━━━

*SWDY* - Elsewedy Electric

:chart_with_upwards_trend: Current Price: *31.25 EGP*
:pushpin: Zone: 30.00 - 31.00 EGP (Resistance)
:arrow_up: Exit: Broke Above

Price exited your resistance zone to the upside!
Potential bullish continuation.

:clock1: 1:30 PM · Jan 10, 2026
```

---

### 4. Price Gap Alert

#### Telegram - English (High Priority)

```
:hole: *Gap Up Detected*
━━━━━━━━━━━━━━━━━━

*EFIH* - EFG Hermes Holding

:arrow_up: Open: *22.50 EGP*
:last_track_button: Previous Close: 21.00 EGP
:chart_increasing: Gap: +7.1%

Market opened significantly higher than yesterday's close.
This exceeds your 5% gap threshold.

:clock1: 10:00 AM · Jan 10, 2026 (Market Open)

━━━━━━━━━━━━━━━━━━
[View Stock](https://kira.app/ar/assets/EFIH) · [Manage Alert](https://kira.app/alerts/101)
```

#### Telegram - Arabic (High Priority)

```
:hole: *فجوة سعرية صاعدة*
━━━━━━━━━━━━━━━━━━

*EFIH* - مجموعة اي اف جي القابضة

:arrow_up: الافتتاح: *22.50 ج.م*
:last_track_button: إغلاق أمس: 21.00 ج.م
:chart_increasing: الفجوة: +7.1%

السوق افتتح أعلى بكثير من إغلاق أمس.
هذا يتجاوز حد الفجوة 5% المحدد.

:clock1: 10:00 ص · 10 يناير 2026 (افتتاح السوق)

━━━━━━━━━━━━━━━━━━
[عرض السهم](https://kira.app/ar/assets/EFIH) · [إدارة التنبيه](https://kira.app/alerts/101)
```

#### Gap Down Variant - Arabic

```
:hole: *فجوة سعرية هابطة*
━━━━━━━━━━━━━━━━━━

*EFIH* - مجموعة اي اف جي القابضة

:arrow_down: الافتتاح: *19.50 ج.م*
:last_track_button: إغلاق أمس: 21.00 ج.م
:chart_decreasing: الفجوة: -7.1%

السوق افتتح أقل بكثير من إغلاق أمس.
انتبه للضغط البيعي المحتمل.

:clock1: 10:00 ص · 10 يناير 2026 (افتتاح السوق)
```

---

### 5. 52-Week High/Low Alert

#### Telegram - English (Critical Priority)

```
:trophy: *New 52-Week High!*
━━━━━━━━━━━━━━━━━━

*TMGH* - Talaat Moustafa Group

:star2: Current Price: *45.80 EGP*
:chart_with_upwards_trend: Previous 52W High: 44.50 EGP
:calendar: Last High: Sep 15, 2025
:chart_increasing: YTD Return: +68.2%

Stock broke to a new all-time high!
Strong momentum continues.

:clock1: 2:15 PM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Stock](https://kira.app/ar/assets/TMGH) · [Manage Alert](https://kira.app/alerts/202)
```

#### Telegram - Arabic (Critical Priority)

```
:trophy: *قمة جديدة لـ 52 أسبوع!*
━━━━━━━━━━━━━━━━━━

*TMGH* - مجموعة طلعت مصطفى

:star2: السعر الحالي: *45.80 ج.م*
:chart_with_upwards_trend: القمة السابقة 52 أسبوع: 44.50 ج.م
:calendar: تاريخ القمة السابقة: 15 سبتمبر 2025
:chart_increasing: العائد منذ بداية العام: +68.2%

السهم سجل قمة تاريخية جديدة!
الزخم الإيجابي مستمر.

:clock1: 2:15 م · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض السهم](https://kira.app/ar/assets/TMGH) · [إدارة التنبيه](https://kira.app/alerts/202)
```

#### 52-Week Low Variant - English

```
:warning: *New 52-Week Low*
━━━━━━━━━━━━━━━━━━

*JUFO* - Juhayna Food Industries

:chart_with_downwards_trend: Current Price: *4.85 EGP*
:small_red_triangle_down: Previous 52W Low: 5.00 EGP
:calendar: Last Low: Mar 20, 2025
:chart_decreasing: YTD Return: -32.5%

Stock fell to a new 52-week low.
Consider your position carefully.

:clock1: 2:15 PM · Jan 10, 2026
```

---

### 6. Daily % Change Alert

#### Telegram - English (High Priority)

```
:chart_with_upwards_trend: *Big Move Alert*
━━━━━━━━━━━━━━━━━━

*ORWE* - Oriental Weavers

:rocket: Current Price: *12.50 EGP*
:chart_increasing: Day Open: 11.50 EGP
:fire: Change: *+8.7%*
:bar_chart: Volume: 3.2M shares

Stock moved more than your 5% threshold today.
Significant buying interest detected.

:clock1: 1:45 PM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Stock](https://kira.app/ar/assets/ORWE) · [Manage Alert](https://kira.app/alerts/303)
```

#### Telegram - Arabic (High Priority)

```
:chart_with_upwards_trend: *تنبيه حركة كبيرة*
━━━━━━━━━━━━━━━━━━

*ORWE* - النساجون الشرقيون

:rocket: السعر الحالي: *12.50 ج.م*
:chart_increasing: الافتتاح: 11.50 ج.م
:fire: التغير: *+8.7%*
:bar_chart: الحجم: 3.2 مليون سهم

السهم تحرك أكثر من حد 5% المحدد.
تم رصد اهتمام شرائي كبير.

:clock1: 1:45 م · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض السهم](https://kira.app/ar/assets/ORWE) · [إدارة التنبيه](https://kira.app/alerts/303)
```

#### Daily Drop Variant - Arabic

```
:chart_with_downwards_trend: *تنبيه انخفاض كبير*
━━━━━━━━━━━━━━━━━━

*ORWE* - النساجون الشرقيون

:small_red_triangle_down: السعر الحالي: *10.50 ج.م*
:chart_decreasing: الافتتاح: 11.50 ج.م
:fire: التغير: *-8.7%*
:bar_chart: الحجم: 5.1 مليون سهم

السهم انخفض أكثر من حد 5% المحدد.
ضغط بيعي كبير.

:clock1: 1:45 م · 10 يناير 2026
```

---

### 7. Price Return to Entry Alert

#### Telegram - English (High Priority)

```
:repeat: *Back to Your Entry Price*
━━━━━━━━━━━━━━━━━━

*PHDC* - Palm Hills Development

:moneybag: Current Price: *3.25 EGP*
:dart: Your Entry: 3.22 EGP
:arrow_right_hook: Difference: +0.9%
:calendar: Entry Date: Oct 15, 2025

Price returned to your purchase price.
Break-even opportunity.

:clock1: 11:30 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Stock](https://kira.app/ar/assets/PHDC) · [Manage Alert](https://kira.app/alerts/404)
```

#### Telegram - Arabic (High Priority)

```
:repeat: *العودة لسعر الشراء*
━━━━━━━━━━━━━━━━━━

*PHDC* - بالم هيلز للتعمير

:moneybag: السعر الحالي: *3.25 ج.م*
:dart: سعر شرائك: 3.22 ج.م
:arrow_right_hook: الفرق: +0.9%
:calendar: تاريخ الشراء: 15 أكتوبر 2025

السعر عاد لمستوى شرائك.
فرصة للخروج بدون خسارة.

:clock1: 11:30 ص · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض السهم](https://kira.app/ar/assets/PHDC) · [إدارة التنبيه](https://kira.app/alerts/404)
```

---

## Intelligence-Based Alerts

### 1. Prediction Alert

#### Telegram - English (High Priority)

```
:crystal_ball: *AI Prediction Alert*
━━━━━━━━━━━━━━━━━━

*ETEL* - Telecom Egypt

:robot_face: Kira AI Prediction:
:arrow_up: Direction: *Bullish*
:clock3: Horizon: 1 Hour
:dart: Confidence: *82%*
:chart_increasing: Expected Move: +2.5% to +4.0%

Current Price: 18.50 EGP
Model: Price Direction v3.2

:brain: Based on: Volume patterns, momentum indicators, and market microstructure analysis.

:clock1: 10:15 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Prediction](https://kira.app/ar/assets/ETEL/predictions) · [Manage Alert](https://kira.app/alerts/505)
```

#### Telegram - Arabic (High Priority)

```
:crystal_ball: *تنبيه توقع الذكاء الاصطناعي*
━━━━━━━━━━━━━━━━━━

*ETEL* - المصرية للاتصالات

:robot_face: توقع كيرا:
:arrow_up: الاتجاه: *صاعد*
:clock3: المدى: ساعة واحدة
:dart: الثقة: *82%*
:chart_increasing: الحركة المتوقعة: +2.5% إلى +4.0%

السعر الحالي: 18.50 ج.م
النموذج: توقع اتجاه السعر v3.2

:brain: بناءً على: أنماط الحجم، مؤشرات الزخم، وتحليل البنية الدقيقة للسوق.

:clock1: 10:15 ص · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض التوقع](https://kira.app/ar/assets/ETEL/predictions) · [إدارة التنبيه](https://kira.app/alerts/505)
```

#### Push Notification - English

```
Title: AI: ETEL Bullish (82% confidence)
Body: Kira predicts +2.5-4% in next hour. Current: 18.50 EGP
```

---

### 2. Signal Alert

#### Telegram - English (Medium Priority)

```
:chart_with_upwards_trend: *Technical Signal Detected*
━━━━━━━━━━━━━━━━━━

*MNHD* - Madinet Nasr Housing

:mag: Signal Type: *RSI Oversold*
:arrow_up: Indicator: RSI(14) = 28.5
:muscle: Strength: 78%
:chart_increasing: Potential: Bullish reversal

Current Price: 5.25 EGP
Signal triggered at 5.20 EGP

:bar_chart: Additional Signals:
• MACD: Bullish divergence forming
• Volume: 1.8x average

:clock1: 11:45 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Analysis](https://kira.app/ar/assets/MNHD/signals) · [Manage Alert](https://kira.app/alerts/606)
```

#### Telegram - Arabic (Medium Priority)

```
:chart_with_upwards_trend: *تم رصد إشارة فنية*
━━━━━━━━━━━━━━━━━━

*MNHD* - مدينة نصر للإسكان

:mag: نوع الإشارة: *RSI في منطقة التشبع البيعي*
:arrow_up: المؤشر: RSI(14) = 28.5
:muscle: القوة: 78%
:chart_increasing: الاحتمال: انعكاس صاعد

السعر الحالي: 5.25 ج.م
الإشارة عند: 5.20 ج.م

:bar_chart: إشارات إضافية:
• MACD: تباعد إيجابي يتشكل
• الحجم: 1.8 ضعف المتوسط

:clock1: 11:45 ص · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض التحليل](https://kira.app/ar/assets/MNHD/signals) · [إدارة التنبيه](https://kira.app/alerts/606)
```

#### Multiple Signals Variant - English

```
:chart_with_upwards_trend: *Multiple Signals Aligned*
━━━━━━━━━━━━━━━━━━

*MNHD* - Madinet Nasr Housing

:star: 3 Bullish Signals Detected:

1. :green_circle: RSI Oversold (28.5)
2. :green_circle: MACD Bullish Cross
3. :green_circle: EMA(20) Support Touch

:muscle: Combined Strength: 85%
:dart: Confluence Score: High

Current Price: 5.25 EGP

:clock1: 11:45 AM · Jan 10, 2026
```

---

### 3. Anomaly Alert

#### Telegram - English (Critical Priority)

```
:rotating_light: *Market Anomaly Detected*
━━━━━━━━━━━━━━━━━━

*GBCO* - GB Auto

:warning: Anomaly Type: *Volume Surge*
:zap: Severity: Critical
:dart: Confidence: 92%

:bar_chart: Current Volume: 12.5M shares
:chart_with_upwards_trend: Average Volume: 2.1M shares
:fire: Ratio: *6x normal*

Current Price: 8.75 EGP (+3.5%)

:brain: Analysis: Unusual institutional activity detected. Volume significantly exceeds historical patterns.

:clock1: 10:30 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Anomaly](https://kira.app/ar/assets/GBCO/anomalies) · [Manage Alert](https://kira.app/alerts/707)
```

#### Telegram - Arabic (Critical Priority)

```
:rotating_light: *تم رصد شذوذ في السوق*
━━━━━━━━━━━━━━━━━━

*GBCO* - جي بي أوتو

:warning: نوع الشذوذ: *ارتفاع حاد في الحجم*
:zap: الخطورة: حرجة
:dart: الثقة: 92%

:bar_chart: الحجم الحالي: 12.5 مليون سهم
:chart_with_upwards_trend: متوسط الحجم: 2.1 مليون سهم
:fire: النسبة: *6 أضعاف الطبيعي*

السعر الحالي: 8.75 ج.م (+3.5%)

:brain: التحليل: تم رصد نشاط مؤسسي غير عادي. الحجم يتجاوز الأنماط التاريخية بشكل كبير.

:clock1: 10:30 ص · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض الشذوذ](https://kira.app/ar/assets/GBCO/anomalies) · [إدارة التنبيه](https://kira.app/alerts/707)
```

#### Price Spike Anomaly - English

```
:rotating_light: *Price Spike Anomaly*
━━━━━━━━━━━━━━━━━━

*GBCO* - GB Auto

:warning: Anomaly Type: *Unusual Price Movement*
:zap: Severity: High
:dart: Confidence: 88%

:chart_with_upwards_trend: Price moved +5.2% in 10 minutes
:clock3: Normal range: ±1.5% per 10 min
:thinking: Deviation: 3.5 standard deviations

Current Price: 9.15 EGP

:brain: Analysis: Price movement exceeds normal volatility bounds. Potential news or block trade.

:clock1: 10:35 AM · Jan 10, 2026
```

---

### 4. Pattern Alert

#### Telegram - English (High Priority)

```
:triangular_ruler: *Chart Pattern Confirmed*
━━━━━━━━━━━━━━━━━━

*EAST* - Eastern Company

:mag: Pattern: *Double Bottom*
:white_check_mark: Status: Confirmed
:dart: Confidence: 78%
:arrow_up: Bias: Bullish

:chart_with_upwards_trend: Pattern Details:
• First Bottom: 12.50 EGP (Dec 15)
• Second Bottom: 12.45 EGP (Jan 8)
• Neckline: 13.80 EGP
• Target: 15.10 EGP (+9.4%)

Current Price: 13.95 EGP (Broke neckline)

:brain: Classic reversal pattern with volume confirmation.

:clock1: 2:00 PM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Pattern](https://kira.app/ar/assets/EAST/patterns) · [Manage Alert](https://kira.app/alerts/808)
```

#### Telegram - Arabic (High Priority)

```
:triangular_ruler: *تأكيد نموذج فني*
━━━━━━━━━━━━━━━━━━

*EAST* - الشرقية للدخان

:mag: النموذج: *قاع مزدوج*
:white_check_mark: الحالة: مؤكد
:dart: الثقة: 78%
:arrow_up: الميل: صاعد

:chart_with_upwards_trend: تفاصيل النموذج:
• القاع الأول: 12.50 ج.م (15 ديسمبر)
• القاع الثاني: 12.45 ج.م (8 يناير)
• خط العنق: 13.80 ج.م
• الهدف: 15.10 ج.م (+9.4%)

السعر الحالي: 13.95 ج.م (اخترق خط العنق)

:brain: نموذج انعكاسي كلاسيكي مع تأكيد من الحجم.

:clock1: 2:00 م · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض النموذج](https://kira.app/ar/assets/EAST/patterns) · [إدارة التنبيه](https://kira.app/alerts/808)
```

#### Head & Shoulders Pattern - English

```
:triangular_ruler: *Bearish Pattern Forming*
━━━━━━━━━━━━━━━━━━

*CLHO* - Cleopatra Hospital

:mag: Pattern: *Head & Shoulders*
:hourglass_flowing_sand: Status: Forming (75% complete)
:dart: Confidence: 72%
:arrow_down: Bias: Bearish

:chart_with_downwards_trend: Pattern Details:
• Left Shoulder: 8.50 EGP
• Head: 9.20 EGP
• Right Shoulder: 8.45 EGP (forming)
• Neckline: 7.80 EGP
• Target: 6.40 EGP (-18%)

Current Price: 8.35 EGP

:warning: Watch for neckline break confirmation.

:clock1: 2:00 PM · Jan 10, 2026
```

---

### 5. Recommendation Alert

#### Telegram - English (High Priority)

```
:star2: *Recommendation Updated*
━━━━━━━━━━━━━━━━━━

*FWRY* - Fawry

:arrow_up: New Rating: *Strong Buy*
:left_arrow_curving_right: Previous: Buy
:dart: Score: 8.5/10
:chart_increasing: Upgrade!

:bar_chart: Score Breakdown:
• Technical: 8.2/10
• Momentum: 9.1/10
• Value: 7.8/10
• Sentiment: 8.9/10

Current Price: 7.85 EGP
Target Range: 9.00 - 9.50 EGP (+15-21%)

:brain: Strong momentum with improving fundamentals.

:clock1: 9:00 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Full Analysis](https://kira.app/ar/assets/FWRY/recommendation) · [Manage Alert](https://kira.app/alerts/909)
```

#### Telegram - Arabic (High Priority)

```
:star2: *تحديث التوصية*
━━━━━━━━━━━━━━━━━━

*FWRY* - فوري

:arrow_up: التصنيف الجديد: *شراء قوي*
:left_arrow_curving_right: السابق: شراء
:dart: الدرجة: 8.5/10
:chart_increasing: ترقية!

:bar_chart: تفصيل الدرجة:
• فني: 8.2/10
• زخم: 9.1/10
• قيمة: 7.8/10
• معنويات: 8.9/10

السعر الحالي: 7.85 ج.م
النطاق المستهدف: 9.00 - 9.50 ج.م (+15-21%)

:brain: زخم قوي مع تحسن في الأساسيات.

:clock1: 9:00 ص · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض التحليل الكامل](https://kira.app/ar/assets/FWRY/recommendation) · [إدارة التنبيه](https://kira.app/alerts/909)
```

#### Downgrade Variant - English

```
:small_red_triangle_down: *Recommendation Downgrade*
━━━━━━━━━━━━━━━━━━

*SKPC* - Sidi Kerir Petrochemicals

:arrow_down: New Rating: *Hold*
:left_arrow_curving_right: Previous: Buy
:dart: Score: 5.2/10
:chart_decreasing: Downgrade

:bar_chart: Score Breakdown:
• Technical: 4.8/10
• Momentum: 4.5/10
• Value: 6.2/10
• Sentiment: 5.3/10

Current Price: 14.20 EGP

:warning: Momentum weakening, consider reducing exposure.

:clock1: 9:00 AM · Jan 10, 2026
```

---

### 6. Compound Intelligence Alert

#### Telegram - English (Critical Priority)

```
:star: *Multiple Signals Aligned!*
━━━━━━━━━━━━━━━━━━

*ABUK* - Abu Qir Fertilizers

:fire: *High-Conviction Setup*
All 3 conditions met:

:one: :white_check_mark: RSI Oversold (26.3)
   Signal strength: 85%

:two: :white_check_mark: Bullish Prediction
   Confidence: 79%
   Expected: +3-5% (1 hour)

:three: :white_check_mark: Double Bottom Pattern
   Status: Confirmed
   Target: +12%

:dart: Combined Confidence: *87%*
Current Price: 28.50 EGP

:brain: Rare alignment of technical, AI, and pattern signals. Historical success rate for similar setups: 76%.

:clock1: 11:00 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Full Analysis](https://kira.app/ar/assets/ABUK) · [Manage Alert](https://kira.app/alerts/1010)
```

#### Telegram - Arabic (Critical Priority)

```
:star: *توافق إشارات متعددة!*
━━━━━━━━━━━━━━━━━━

*ABUK* - أبو قير للأسمدة

:fire: *فرصة عالية الثقة*
جميع الشروط الثلاثة تحققت:

:one: :white_check_mark: RSI في التشبع البيعي (26.3)
   قوة الإشارة: 85%

:two: :white_check_mark: توقع صاعد
   الثقة: 79%
   المتوقع: +3-5% (ساعة واحدة)

:three: :white_check_mark: نموذج قاع مزدوج
   الحالة: مؤكد
   الهدف: +12%

:dart: الثقة المجمعة: *87%*
السعر الحالي: 28.50 ج.م

:brain: توافق نادر بين الإشارات الفنية والذكاء الاصطناعي والنماذج. معدل النجاح التاريخي لإعدادات مماثلة: 76%.

:clock1: 11:00 ص · 10 يناير 2026

━━━━━━━━━━━━━━━━━━
[عرض التحليل الكامل](https://kira.app/ar/assets/ABUK) · [إدارة التنبيه](https://kira.app/alerts/1010)
```

---

## Special Notifications

### Daily Digest

#### Telegram - English (Low Priority)

```
:newspaper: *Your Daily Alert Digest*
━━━━━━━━━━━━━━━━━━

:calendar: Friday, January 10, 2026

:bell: *Alerts Triggered Today: 5*

:trophy: Highlights:
• TMGH hit 52-week high (+3.2%)
• COMI reached your target 52.00 EGP
• 2 AI predictions were correct

:chart_with_upwards_trend: Your Watchlist Summary:
• Gainers: 8 stocks
• Losers: 4 stocks
• Top: ORWE +8.7%
• Bottom: SKPC -2.1%

:dart: Active Alerts: 12
:hourglass_flowing_sand: Pending: 8
:white_check_mark: Completed: 4

━━━━━━━━━━━━━━━━━━
[View All Alerts](https://kira.app/alerts) · [Manage Preferences](https://kira.app/settings/alerts)
```

#### Telegram - Arabic (Low Priority)

```
:newspaper: *ملخصك اليومي*
━━━━━━━━━━━━━━━━━━

:calendar: الجمعة، 10 يناير 2026

:bell: *التنبيهات المفعلة اليوم: 5*

:trophy: أبرز الأحداث:
• TMGH سجل قمة 52 أسبوع (+3.2%)
• COMI وصل لهدفك 52.00 ج.م
• 2 توقعات ذكاء اصطناعي كانت صحيحة

:chart_with_upwards_trend: ملخص قائمة المتابعة:
• الرابحون: 8 أسهم
• الخاسرون: 4 أسهم
• الأعلى: ORWE +8.7%
• الأدنى: SKPC -2.1%

:dart: تنبيهات نشطة: 12
:hourglass_flowing_sand: معلقة: 8
:white_check_mark: مكتملة: 4

━━━━━━━━━━━━━━━━━━
[عرض كل التنبيهات](https://kira.app/alerts) · [إدارة التفضيلات](https://kira.app/settings/alerts)
```

---

### Alert Chain Triggered

#### Telegram - English (High Priority)

```
:link: *Alert Chain Activated*
━━━━━━━━━━━━━━━━━━

*Chain: "COMI Breakout Strategy"*

:one: :white_check_mark: Trigger Alert Fired:
   Target Price 52.00 EGP (Reached)

:two: :arrow_right: Activated Alert:
   Stop Loss at 50.00 EGP
   (Now Active)

:three: :hourglass_flowing_sand: Pending:
   Take Profit at 56.00 EGP
   (Activates on confirmation)

Current Price: 52.50 EGP

:clock1: 10:45 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[View Chain](https://kira.app/alerts/chains/123) · [Manage Alerts](https://kira.app/alerts)
```

---

### Escalation Notification

#### Telegram - English (Critical - Escalated)

```
:rotating_light: *ESCALATED: Unacknowledged Alert*
━━━━━━━━━━━━━━━━━━

:warning: This is escalation level 2

Original Alert (15 minutes ago):
:dart: COMI reached target 52.00 EGP

:bell: You have not acknowledged this alert.
This is a high-priority notification.

Current Price: 52.75 EGP (+1.4% since alert)

:point_right: *Action Required*
Tap to acknowledge and stop escalation.

:clock1: 11:00 AM · Jan 10, 2026

━━━━━━━━━━━━━━━━━━
[Acknowledge](https://kira.app/alerts/123/ack) · [Snooze 1h](https://kira.app/alerts/123/snooze)
```

---

### Backtest Result

#### Telegram - English (Low Priority)

```
:test_tube: *Backtest Complete*
━━━━━━━━━━━━━━━━━━

*Alert: Target Price 52.00 EGP for COMI*
:calendar: Period: Last 6 months

:bar_chart: Results:
• Would have triggered: *3 times*
• Average time to trigger: 12 days
• Max wait: 28 days
• Last would-have-triggered: Dec 5, 2025

:chart_with_upwards_trend: Performance after trigger:
• Avg return (1 day): +1.2%
• Avg return (1 week): +3.5%
• Avg return (1 month): +7.8%

:brain: Recommendation: This alert level has historically shown good follow-through.

━━━━━━━━━━━━━━━━━━
[Create Alert](https://kira.app/alerts/create?preset=123) · [Modify Parameters](https://kira.app/alerts/backtest)
```

---

## Email Notification Template

### HTML Email - English

```html
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background: #f8f9fa; padding: 20px; border: 1px solid #e9ecef; }
        .price { font-size: 32px; font-weight: bold; color: #28a745; }
        .details { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .cta { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; }
        .footer { text-align: center; padding: 20px; color: #6c757d; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Target Price Reached</h1>
            <p>COMI - Commercial International Bank</p>
        </div>
        <div class="content">
            <p class="price">52.50 EGP</p>
            <p>Your target of 52.00 EGP has been reached!</p>

            <div class="details">
                <table width="100%">
                    <tr><td>Target Price:</td><td><strong>52.00 EGP</strong></td></tr>
                    <tr><td>Current Price:</td><td><strong>52.50 EGP</strong></td></tr>
                    <tr><td>Daily Change:</td><td><strong>+4.2%</strong></td></tr>
                    <tr><td>Triggered At:</td><td>10:45 AM, Jan 10, 2026</td></tr>
                </table>
            </div>

            <p>
                <a href="https://kira.app/ar/assets/COMI" class="cta">View Stock</a>
                <a href="https://kira.app/alerts/123" class="cta" style="background: #6c757d;">Manage Alert</a>
            </p>
        </div>
        <div class="footer">
            <p>You received this email because you set up a price alert on Kira.</p>
            <p><a href="https://kira.app/settings/alerts">Manage notification preferences</a></p>
        </div>
    </div>
</body>
</html>
```

---

## In-App Toast Notifications

### Toast Component Variants

```vue
<!-- High Priority Toast -->
<Toast priority="high">
  <template #icon>
    <TargetIcon class="text-green-500" />
  </template>
  <template #title>COMI reached 52.00 EGP</template>
  <template #body>Target price hit! Now at 52.50 EGP</template>
  <template #actions>
    <Button size="sm" @click="viewStock">View</Button>
    <Button size="sm" variant="ghost" @click="dismiss">Dismiss</Button>
  </template>
</Toast>

<!-- Critical Priority Toast (with sound) -->
<Toast priority="critical" :with-sound="true">
  <template #icon>
    <AlertTriangleIcon class="text-red-500 animate-pulse" />
  </template>
  <template #title>Volume Anomaly: GBCO</template>
  <template #body>6x normal volume detected!</template>
  <template #actions>
    <Button size="sm" variant="destructive" @click="viewAnomaly">
      View Now
    </Button>
  </template>
</Toast>
```

---

## Notification Data Payload

### WebSocket Event Payload

```typescript
interface AlertNotification {
  id: string;
  type: 'alert.triggered';
  priority: 'critical' | 'high' | 'medium' | 'low';
  alert: {
    id: string;
    type: AlertType;
    trigger_type: string;
  };
  asset: {
    symbol: string;
    name_en: string;
    name_ar: string;
  };
  trigger: {
    value: number;
    condition: string;
    context: Record<string, any>;
  };
  title: {
    en: string;
    ar: string;
  };
  body: {
    en: string;
    ar: string;
  };
  actions: Array<{
    label: { en: string; ar: string };
    url: string;
  }>;
  created_at: string;
  read_at: string | null;
}
```

---

## Localization Reference

### Common Phrases

| English | Arabic |
|---------|--------|
| Target Price Reached | وصول السعر المستهدف |
| Breakout Confirmed | اختراق مؤكد |
| Support Zone Entered | دخول منطقة الدعم |
| Resistance Zone Exited | خروج من منطقة المقاومة |
| Gap Up Detected | فجوة سعرية صاعدة |
| Gap Down Detected | فجوة سعرية هابطة |
| New 52-Week High | قمة جديدة لـ 52 أسبوع |
| New 52-Week Low | قاع جديد لـ 52 أسبوع |
| Big Move Alert | تنبيه حركة كبيرة |
| Back to Entry Price | العودة لسعر الشراء |
| AI Prediction Alert | تنبيه توقع الذكاء الاصطناعي |
| Technical Signal | إشارة فنية |
| Anomaly Detected | تم رصد شذوذ |
| Pattern Confirmed | تأكيد نموذج فني |
| Recommendation Updated | تحديث التوصية |
| Multiple Signals Aligned | توافق إشارات متعددة |
| Current Price | السعر الحالي |
| Target | الهدف |
| Confidence | الثقة |
| View Stock | عرض السهم |
| Manage Alert | إدارة التنبيه |

---

## Implementation Notes

1. **Markdown Escaping**: Telegram uses MarkdownV2, escape special characters: `_`, `*`, `[`, `]`, `(`, `)`, `~`, `` ` ``, `>`, `#`, `+`, `-`, `=`, `|`, `{`, `}`, `.`, `!`

2. **RTL Support**: Arabic messages should be wrapped with RTL markers if needed

3. **Number Formatting**: Use Western numerals for both languages, format with appropriate separators

4. **Currency**: Use "EGP" for English, "ج.م" for Arabic

5. **Time Format**: Use 12-hour format with AM/PM for English, 12-hour with ص/م for Arabic

6. **Deep Links**: All links should use the user's preferred locale in the URL path

---

## Related Documents

- [Kira Alert System Design](./2026-01-10-kira-alert-system-design.md)
