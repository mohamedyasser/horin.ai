# Kira Services Integration Design

## Overview

Integrate Kira backend microservices into the Horin frontend to transform the platform from a predictions-only view into a complete trading intelligence platform with actionable recommendations.

## Goals

1. Display actionable trading recommendations (BUY/SELL/HOLD) with entry/exit prices
2. Provide transparency by showing the analysis behind recommendations (signals, patterns, anomalies)
3. Keep the UI clean, simple, and easy to use
4. Design for future Supabase real-time integration

## Scope

### In Scope

- Trading Recommendations (from `recommendation` service)
- Signal Detection (from `signal-detection` service)
- Signal Classification (from `signal-classification` service)
- Pattern Detection (from `pattern-detection` service)
- Anomaly Detection (from `anomaly` service)

### Out of Scope

- News Pipeline (news-scraping, news-rewrite)
- Authentication/User features
- Real-time WebSocket updates (designed for, but not implemented)

---

## Integration Points

### 1. Homepage Enhancement

Replace the current "Featured Predictions" section with a tabbed interface.

#### Tabbed View Structure

```
┌─────────────────────────────────────────────────────────────┐
│  [Filter: Market ▼] [Sector ▼] [Search asset...]           │
│                                                             │
│  ┌──────────────────┬─────────────┐                        │
│  │ Recommendations  │ Predictions │                        │
│  └──────────────────┴─────────────┘                        │
│  ───────────────────────────────────────────────────────── │
│                                                             │
│  (Active tab content)                                       │
└─────────────────────────────────────────────────────────────┘
```

#### Recommendations Tab - Table Columns

| Column | Description |
|--------|-------------|
| Asset | Symbol + Name (links to asset page) |
| Action | STRONG_BUY, BUY, HOLD, SELL, STRONG_SELL with color coding |
| Entry | Entry price |
| Target | Target price |
| Gain | Expected gain percentage |
| Confidence | Confidence level with color coding |

#### Predictions Tab

Current Featured Predictions table - unchanged.

#### Dynamic Sidebar

Sidebar content changes based on active tab.

**When Recommendations tab is active:**

| Section | Content |
|---------|---------|
| Top Buy Signals | Top 5 BUY/STRONG_BUY by confidence |
| Top Sell Signals | Top 5 SELL/STRONG_SELL by confidence |
| Recent Recommendations | Last 5 recommendations with timestamp |

**When Predictions tab is active:**

Current sidebar - unchanged (Top Movers, Highest Confidence, Recent Predictions).

---

### 2. Asset Detail Page Enhancement

Add a Recommendation Card at the top of main content, after the price header and before the chart.

#### Recommendation Card - Collapsed (Default)

```
┌─────────────────────────────────────────────────────────────┐
│  STRONG BUY                                Confidence: 87%  │
│  ──────────────────────────────────────────────────────────│
│  Entry: $142.50    Stop-Loss: $138.00    Target: $156.00   │
│  Risk/Reward: 1:3.2 (Excellent)          Updated: 5m ago   │
│                                                             │
│  [▼ View Analysis]                                          │
└─────────────────────────────────────────────────────────────┘
```

#### Recommendation Card - Expanded

```
┌─────────────────────────────────────────────────────────────┐
│  STRONG BUY                                Confidence: 87%  │
│  ──────────────────────────────────────────────────────────│
│  Entry: $142.50    Stop-Loss: $138.00    Target: $156.00   │
│  Risk/Reward: 1:3.2 (Excellent)          Updated: 5m ago   │
│                                                             │
│  [▲ Hide Analysis]                                          │
│  ──────────────────────────────────────────────────────────│
│                                                             │
│  WHY THIS RECOMMENDATION                                    │
│  "Strong bullish momentum with oversold bounce and          │
│   pattern confirmation. Volume supports the move."          │
│                                                             │
│  ► ACTIVE SIGNALS (4)                          [collapse]   │
│    • RSI Oversold Bounce (strength: 82%)                    │
│    • MACD Bullish Crossover (strength: 75%)                 │
│    • Volume Spike (strength: 68%)                           │
│    • Price Above EMA20 (strength: 71%)                      │
│                                                             │
│  ► DETECTED PATTERNS (2)                       [collapse]   │
│    • Double Bottom (confidence: 79%)                        │
│    • Support Level at $138.50                               │
│                                                             │
│  ► ANOMALIES (1)                               [collapse]   │
│    • Unusual volume increase (+340% vs avg)                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

#### Empty State

When no recommendation is available:

```
┌─────────────────────────────────────────────────────────────┐
│  NO RECOMMENDATION AVAILABLE                                │
│  ──────────────────────────────────────────────────────────│
│  Waiting for sufficient market data to generate a           │
│  recommendation for this asset.                             │
└─────────────────────────────────────────────────────────────┘
```

#### Mobile Consideration

On mobile viewports, sub-sections (Signals, Patterns, Anomalies) are individually collapsible rather than one large expanded block.

---

## Data Architecture

### New Database Views

#### 1. `latest_recommendations`

Latest recommendation per asset.

```sql
CREATE OR REPLACE VIEW latest_recommendations AS
SELECT DISTINCT ON (pid)
    pid,
    score,
    recommendation,
    entry_price,
    stop_loss,
    target_price,
    risk_reward,
    confidence,
    reasoning,
    created_at
FROM instant_recommendations
ORDER BY pid, created_at DESC;
```

#### 2. `latest_detected_signals`

Active signals per asset (last 30 minutes).

```sql
CREATE OR REPLACE VIEW latest_detected_signals AS
SELECT
    pid,
    indicator,
    signal_type,
    value,
    strength,
    created_at
FROM instant_detected_signals
WHERE created_at >= NOW() - INTERVAL '30 minutes'
ORDER BY pid, created_at DESC;
```

#### 3. `latest_pattern_detections`

Recent patterns per asset.

```sql
CREATE OR REPLACE VIEW latest_pattern_detections AS
SELECT DISTINCT ON (pid)
    pid,
    patterns,
    pattern_count,
    has_head_shoulder,
    has_triangle,
    has_double_top,
    has_double_bottom,
    support_levels,
    resistance_levels,
    created_at
FROM instant_pattern_detections
ORDER BY pid, created_at DESC;
```

#### 4. `latest_anomalies`

Recent anomalies per asset (last 30 minutes).

```sql
CREATE OR REPLACE VIEW latest_anomalies AS
SELECT
    pid,
    symbol,
    anomaly_type,
    confidence_score,
    description,
    created_at
FROM instant_anomalies
WHERE created_at >= NOW() - INTERVAL '30 minutes'
ORDER BY pid, created_at DESC;
```

#### 5. `latest_signal_classifications`

Classified signals for discovery.

```sql
CREATE OR REPLACE VIEW latest_signal_classifications AS
SELECT DISTINCT ON (pid)
    pid,
    classification,
    priority,
    action,
    confidence,
    risk_score,
    reward_score,
    created_at
FROM instant_signal_classifications
ORDER BY pid, created_at DESC;
```

---

### New Eloquent Models

#### 1. LatestRecommendation

```php
class LatestRecommendation extends Model
{
    protected $table = 'latest_recommendations';
    protected $primaryKey = 'pid';
    public $timestamps = false;

    protected $casts = [
        'confidence' => 'float',
        'risk_reward' => 'float',
        'entry_price' => 'float',
        'stop_loss' => 'float',
        'target_price' => 'float',
        'created_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'pid');
    }
}
```

#### 2. LatestDetectedSignal

```php
class LatestDetectedSignal extends Model
{
    protected $table = 'latest_detected_signals';
    public $timestamps = false;

    protected $casts = [
        'strength' => 'float',
        'value' => 'float',
        'created_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'pid');
    }
}
```

#### 3. LatestPatternDetection

```php
class LatestPatternDetection extends Model
{
    protected $table = 'latest_pattern_detections';
    protected $primaryKey = 'pid';
    public $timestamps = false;

    protected $casts = [
        'patterns' => 'array',
        'support_levels' => 'array',
        'resistance_levels' => 'array',
        'pattern_count' => 'integer',
        'has_head_shoulder' => 'boolean',
        'has_triangle' => 'boolean',
        'has_double_top' => 'boolean',
        'has_double_bottom' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'pid');
    }
}
```

#### 4. LatestAnomaly

```php
class LatestAnomaly extends Model
{
    protected $table = 'latest_anomalies';
    public $timestamps = false;

    protected $casts = [
        'confidence_score' => 'float',
        'created_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'pid');
    }
}
```

#### 5. LatestSignalClassification

```php
class LatestSignalClassification extends Model
{
    protected $table = 'latest_signal_classifications';
    protected $primaryKey = 'pid';
    public $timestamps = false;

    protected $casts = [
        'confidence' => 'float',
        'risk_score' => 'float',
        'reward_score' => 'float',
        'created_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'pid');
    }
}
```

---

### Asset Model Relationships

Add to existing `Asset` model:

```php
public function latestRecommendation(): HasOne
{
    return $this->hasOne(LatestRecommendation::class, 'pid', 'pid');
}

public function latestSignals(): HasMany
{
    return $this->hasMany(LatestDetectedSignal::class, 'pid', 'pid');
}

public function latestPatternDetection(): HasOne
{
    return $this->hasOne(LatestPatternDetection::class, 'pid', 'pid');
}

public function latestAnomalies(): HasMany
{
    return $this->hasMany(LatestAnomaly::class, 'pid', 'pid');
}

public function latestSignalClassification(): HasOne
{
    return $this->hasOne(LatestSignalClassification::class, 'pid', 'pid');
}
```

---

## Controller Changes

### HomeController

Add new deferred props for recommendations:

```php
public function __invoke(Request $request): Response
{
    return Inertia::render('Welcome', [
        // Existing props...
        'featuredPredictions' => Inertia::defer(fn () => $this->getFeaturedPredictions($request)),
        'topMovers' => Inertia::defer(fn () => $this->getTopMovers()),
        'highestConfidence' => Inertia::defer(fn () => $this->getHighestConfidence()),
        'recentPredictions' => Inertia::defer(fn () => $this->getRecentPredictions()),

        // New props...
        'featuredRecommendations' => Inertia::defer(fn () => $this->getFeaturedRecommendations($request)),
        'topBuySignals' => Inertia::defer(fn () => $this->getTopBuySignals()),
        'topSellSignals' => Inertia::defer(fn () => $this->getTopSellSignals()),
        'recentRecommendations' => Inertia::defer(fn () => $this->getRecentRecommendations()),

        // Filters...
        'filters' => $request->only(['market', 'sector', 'search']),
    ]);
}

private function getFeaturedRecommendations(Request $request): Collection
{
    return LatestRecommendation::query()
        ->with(['asset:pid,symbol,name,market_id,sector_id', 'asset.market:id,name', 'asset.sector:id,name'])
        ->when($request->market, fn ($q, $market) => $q->whereHas('asset', fn ($q) => $q->where('market_id', $market)))
        ->when($request->sector, fn ($q, $sector) => $q->whereHas('asset', fn ($q) => $q->where('sector_id', $sector)))
        ->when($request->search, fn ($q, $search) => $q->whereHas('asset', fn ($q) => $q->where('symbol', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
        ->orderByDesc('confidence')
        ->limit(20)
        ->get();
}

private function getTopBuySignals(): Collection
{
    return LatestRecommendation::query()
        ->with(['asset:pid,symbol,name'])
        ->whereIn('recommendation', ['STRONG_BUY', 'BUY'])
        ->orderByDesc('confidence')
        ->limit(5)
        ->get();
}

private function getTopSellSignals(): Collection
{
    return LatestRecommendation::query()
        ->with(['asset:pid,symbol,name'])
        ->whereIn('recommendation', ['STRONG_SELL', 'SELL'])
        ->orderByDesc('confidence')
        ->limit(5)
        ->get();
}

private function getRecentRecommendations(): Collection
{
    return LatestRecommendation::query()
        ->with(['asset:pid,symbol,name'])
        ->orderByDesc('created_at')
        ->limit(5)
        ->get();
}
```

### AssetController@show

Add recommendation and analysis data:

```php
public function show(Request $request, Asset $asset): Response
{
    return Inertia::render('assets/Show', [
        // Existing props...
        'asset' => $asset->load(['market', 'sector', 'country']),
        'price' => fn () => $asset->latestPrice,
        'predictions' => Inertia::defer(fn () => $asset->latestPredictions),
        'indicators' => Inertia::defer(fn () => $asset->latestIndicator),
        'priceHistory' => Inertia::defer(fn () => $this->getPriceHistory($asset, $request->period ?? 30)),

        // New props...
        'recommendation' => Inertia::defer(fn () => $asset->latestRecommendation),
        'activeSignals' => Inertia::defer(fn () => $asset->latestSignals()->orderByDesc('strength')->get()),
        'detectedPatterns' => Inertia::defer(fn () => $asset->latestPatternDetection),
        'anomalies' => Inertia::defer(fn () => $asset->latestAnomalies),

        // Existing...
        'chartPeriod' => $request->period ?? 30,
    ]);
}
```

---

## Frontend Components

### New Vue Components

#### 1. RecommendationCard.vue

Main recommendation display component for asset page.

**Location:** `resources/js/components/RecommendationCard.vue`

**Props:**
```typescript
interface Props {
    recommendation: Recommendation | null;
    signals: Signal[];
    patterns: PatternDetection | null;
    anomalies: Anomaly[];
}
```

**Features:**
- Collapsible analysis section
- Sub-sections individually collapsible on mobile
- Color-coded action badge
- Staleness indicator ("Updated X ago")
- Empty state handling

#### 2. RecommendationsTable.vue

Table component for homepage recommendations tab.

**Location:** `resources/js/components/RecommendationsTable.vue`

**Props:**
```typescript
interface Props {
    recommendations: Recommendation[];
}
```

#### 3. RecommendationSidebar.vue

Sidebar component for recommendations context.

**Location:** `resources/js/components/RecommendationSidebar.vue`

**Props:**
```typescript
interface Props {
    topBuySignals: Recommendation[];
    topSellSignals: Recommendation[];
    recentRecommendations: Recommendation[];
}
```

### New Composable

#### useRecommendationFormatters.ts

**Location:** `resources/js/composables/useRecommendationFormatters.ts`

```typescript
export function useRecommendationFormatters() {
    const getActionColor = (action: string): string => {
        switch (action) {
            case 'STRONG_BUY':
                return 'bg-green-600 text-white';
            case 'BUY':
                return 'bg-green-500 text-white';
            case 'HOLD':
                return 'bg-gray-500 text-white';
            case 'SELL':
                return 'bg-red-500 text-white';
            case 'STRONG_SELL':
                return 'bg-red-600 text-white';
            default:
                return 'bg-gray-400 text-white';
        }
    };

    const getActionIcon = (action: string): string => {
        if (action.includes('BUY')) return 'TrendingUp';
        if (action.includes('SELL')) return 'TrendingDown';
        return 'Minus';
    };

    const getRiskRewardLabel = (ratio: number): string => {
        if (ratio >= 3) return 'Excellent';
        if (ratio >= 2) return 'Good';
        if (ratio >= 1) return 'Fair';
        return 'Poor';
    };

    const getRiskRewardColor = (ratio: number): string => {
        if (ratio >= 3) return 'text-green-600';
        if (ratio >= 2) return 'text-green-500';
        if (ratio >= 1) return 'text-yellow-500';
        return 'text-red-500';
    };

    const formatTimeAgo = (date: Date): string => {
        const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes}m ago`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h ago`;
        return `${Math.floor(hours / 24)}d ago`;
    };

    const isStale = (date: Date, thresholdMinutes = 30): boolean => {
        return (Date.now() - date.getTime()) > thresholdMinutes * 60 * 1000;
    };

    return {
        getActionColor,
        getActionIcon,
        getRiskRewardLabel,
        getRiskRewardColor,
        formatTimeAgo,
        isStale,
    };
}
```

### TypeScript Types

Add to `resources/js/types/index.d.ts`:

```typescript
// Recommendation Types
export interface Recommendation {
    pid: number;
    recommendation: 'STRONG_BUY' | 'BUY' | 'HOLD' | 'SELL' | 'STRONG_SELL';
    entry_price: number | null;
    stop_loss: number | null;
    target_price: number | null;
    risk_reward: number | null;
    confidence: number;
    reasoning: string | null;
    created_at: string;
    asset?: Asset;
}

export interface Signal {
    pid: number;
    indicator: string;
    signal_type: string;
    value: number;
    strength: number;
    created_at: string;
}

export interface PatternDetection {
    pid: number;
    patterns: string[];
    pattern_count: number;
    has_head_shoulder: boolean;
    has_triangle: boolean;
    has_double_top: boolean;
    has_double_bottom: boolean;
    support_levels: number[];
    resistance_levels: number[];
    created_at: string;
}

export interface Anomaly {
    pid: number;
    symbol: string;
    anomaly_type: string;
    confidence_score: number;
    description: string | null;
    created_at: string;
}

// Page Props Extensions
export interface WelcomePageProps {
    // Existing...
    featuredPredictions: FeaturedPrediction[];
    topMovers: TopMover[];
    highestConfidence: FeaturedPrediction[];
    recentPredictions: RecentPrediction[];

    // New...
    featuredRecommendations: Recommendation[];
    topBuySignals: Recommendation[];
    topSellSignals: Recommendation[];
    recentRecommendations: Recommendation[];

    filters: {
        market?: number;
        sector?: number;
        search?: string;
    };
}

export interface AssetShowPageProps {
    // Existing...
    asset: Asset;
    price: AssetPriceData;
    predictions: AssetPredictionData[];
    indicators: TechnicalIndicators;
    priceHistory: PriceHistoryPoint[];

    // New...
    recommendation: Recommendation | null;
    activeSignals: Signal[];
    detectedPatterns: PatternDetection | null;
    anomalies: Anomaly[];

    chartPeriod: ChartPeriod;
}
```

---

## Page Modifications

### Welcome.vue Changes

1. Add tab component (Recommendations | Predictions)
2. Import and use `RecommendationsTable` for recommendations tab
3. Keep existing predictions table for predictions tab
4. Add dynamic sidebar that switches based on active tab
5. Share filters between both tabs

### assets/Show.vue Changes

1. Add `RecommendationCard` component after price header, before chart
2. Pass recommendation, signals, patterns, anomalies as props
3. Handle deferred loading with skeleton states

---

## i18n Additions

Add to `resources/js/i18n/en.json`:

```json
{
    "recommendations": {
        "title": "Recommendations",
        "noData": "No recommendation available",
        "noDataDescription": "Waiting for sufficient market data to generate a recommendation for this asset.",
        "viewAnalysis": "View Analysis",
        "hideAnalysis": "Hide Analysis",
        "whyThisRecommendation": "Why This Recommendation",
        "activeSignals": "Active Signals",
        "detectedPatterns": "Detected Patterns",
        "anomalies": "Anomalies",
        "entry": "Entry",
        "stopLoss": "Stop-Loss",
        "target": "Target",
        "riskReward": "Risk/Reward",
        "confidence": "Confidence",
        "updated": "Updated",
        "actions": {
            "STRONG_BUY": "Strong Buy",
            "BUY": "Buy",
            "HOLD": "Hold",
            "SELL": "Sell",
            "STRONG_SELL": "Strong Sell"
        },
        "riskRewardLabels": {
            "excellent": "Excellent",
            "good": "Good",
            "fair": "Fair",
            "poor": "Poor"
        }
    },
    "sidebar": {
        "topBuySignals": "Top Buy Signals",
        "topSellSignals": "Top Sell Signals",
        "recentRecommendations": "Recent Recommendations"
    }
}
```

Add equivalent translations to `resources/js/i18n/ar.json`.

---

## Future: Supabase Real-Time Integration

The design supports future real-time updates:

1. **RecommendationCard.vue** is a standalone component that can subscribe to Supabase channels
2. **Database views** can be used as Supabase subscription targets
3. **Deferred props** can be replaced with reactive Supabase subscriptions

Example future integration:

```typescript
// In RecommendationCard.vue setup
const supabase = useSupabaseClient();

const { data: recommendation } = useSubscription(
    supabase
        .channel('recommendations')
        .on('postgres_changes', {
            event: '*',
            schema: 'public',
            table: 'latest_recommendations',
            filter: `pid=eq.${props.assetPid}`,
        }, (payload) => {
            // Update reactive recommendation
        })
);
```

---

## Implementation Order

1. **Database Layer**
   - Create database views (migrations)
   - Create Eloquent models
   - Add relationships to Asset model

2. **Backend API**
   - Update HomeController with recommendation props
   - Update AssetController with recommendation + analysis props
   - Create filter request class

3. **Frontend Foundation**
   - Add TypeScript types
   - Create `useRecommendationFormatters` composable
   - Add i18n translations

4. **Frontend Components**
   - Create `RecommendationCard.vue`
   - Create `RecommendationsTable.vue`
   - Create `RecommendationSidebar.vue`

5. **Page Integration**
   - Update `Welcome.vue` with tabs and dynamic sidebar
   - Update `assets/Show.vue` with RecommendationCard

6. **Testing & Polish**
   - Test empty states
   - Test mobile responsiveness
   - Verify deferred loading works correctly
   - Run full test suite

---

## Success Criteria

1. Homepage displays recommendations in a tabbed interface with predictions
2. Sidebar dynamically updates based on active tab
3. Asset detail page shows recommendation card with expandable analysis
4. All data loads with appropriate skeleton states
5. Empty states display helpful messages
6. Mobile experience is clean with collapsible sections
7. All existing functionality remains intact
8. i18n works for both English and Arabic
