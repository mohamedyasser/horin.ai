# Performance Analysis Report

**Generated**: December 3, 2025
**Application**: Horin Frontend
**Environment**: Local development with remote Supabase PostgreSQL

---

## Summary Table

| Page | First Byte (TTFB) | Load Complete | Deferred XHR | Payload Size | Rating |
|------|-------------------|---------------|--------------|--------------|--------|
| **Search** | 71ms | 116ms | - | 9KB | ✅ Excellent |
| **Predictions** | 2,806ms | 2,873ms | 6,625ms | 13KB | ❌ Critical |
| **Markets Index** | 3,359ms | 3,500ms | - | 12KB | ❌ Critical |
| **Home/Welcome** | 3,425ms | 3,506ms | - | 13KB | ❌ Critical |
| **Sectors Index** | 3,485ms | 3,558ms | - | 29KB | ❌ Critical |
| **Market Show** | 3,743ms | 3,801ms | 6,499ms | 10KB | ❌ Critical |
| **Sector Show** | 4,211ms | 4,256ms | 4,272ms | 11KB | ❌ Critical |
| **Asset Show** | 4,233ms | 4,280ms | 5,124ms | **1.9MB!** | 🚨 Severe |

---

## Database Statistics

| Table | Row Count |
|-------|-----------|
| Markets | 7 |
| Assets | 979 |
| Sectors | 17 |
| Predictions | 0 |
| Asset Prices | 2,734,407 |

---

## Critical Issues

### 1. Remote Database Latency (Primary Issue)

The database is hosted on **Supabase (remote PostgreSQL)**. The Search page loads in 71ms proving your PHP/Laravel code is fast - the bottleneck is network latency to the remote DB for every query.

**Evidence**:
- Search page (minimal queries): 71ms
- Other pages (multiple queries): 2,800-4,200ms
- Each query adds ~100-300ms of network latency

### 2. Asset Show Page: 1.9MB Payload

`AssetController::getPriceHistory()` fetches **ALL price records** for the last 30 days from a 2.7M row table without limiting.

**Location**: `app/Http/Controllers/AssetController.php:118-135`

```php
return AssetPrice::where('pid', $asset->inv_id)
    ->where('timestamp', '>=', $startTimestamp)
    ->orderBy('timestamp')
    ->get()  // ← Fetches ALL rows, no pagination
```

### 3. N+1 Queries in HomeController

`HomeController::__invoke()` runs 4 COUNT queries + 2 eager loads + multiple relationship accesses:

**Location**: `app/Http/Controllers/HomeController.php:20-28`

```php
$stats = [
    'totalMarkets' => Market::count(),      // Query 1
    'totalAssets' => Asset::count(),        // Query 2
    'totalPredictions' => PredictedAssetPrice::count(), // Query 3
    'totalSectors' => Sector::count(),      // Query 4
];
```

Each market also calls `$market->isOpenNow()` which may trigger additional queries.

### 4. Missing Translation Keys

Browser logs show 20+ warnings about missing i18n keys:
- `markets.assets` - missing in both `ar` and `en` locales
- `markets.country` - missing in both `ar` and `en` locales

---

## Recommendations

### High Priority (Quick Wins)

#### 1. Limit price history data in AssetController

Instead of fetching ALL prices, sample or aggregate the data:

```php
// Option A: Daily aggregation
private function getPriceHistory(Asset $asset, int $days): array
{
    $startTimestamp = now()->subDays($days)->timestamp * 1000;

    return DB::table('asset_prices')
        ->where('pid', $asset->inv_id)
        ->where('timestamp', '>=', $startTimestamp)
        ->selectRaw("
            DATE(to_timestamp(timestamp/1000)) as date,
            FIRST_VALUE(last) OVER (PARTITION BY DATE(to_timestamp(timestamp/1000)) ORDER BY timestamp) as open,
            MAX(high) as high,
            MIN(low) as low,
            LAST_VALUE(last) OVER (PARTITION BY DATE(to_timestamp(timestamp/1000)) ORDER BY timestamp) as close,
            SUM(turnover_numeric) as volume
        ")
        ->groupBy('date')
        ->orderBy('date')
        ->get()
        ->toArray();
}

// Option B: Sample every Nth record
private function getPriceHistory(Asset $asset, int $days): array
{
    $startTimestamp = now()->subDays($days)->timestamp * 1000;

    // Limit to ~500 data points max
    return AssetPrice::where('pid', $asset->inv_id)
        ->where('timestamp', '>=', $startTimestamp)
        ->whereRaw('MOD(timestamp, 3600000) = 0') // Hourly samples
        ->orderBy('timestamp')
        ->limit(500)
        ->get()
        ->map(fn ($price) => [
            'timestamp' => $price->timestamp,
            'close' => (float) $price->last,
            'high' => (float) $price->high,
            'low' => (float) $price->low,
            'open' => (float) $price->last_close,
            'volume' => (float) ($price->turnover_numeric ?? 0),
        ])
        ->toArray();
}
```

#### 2. Increase PredictionStatsService cache TTL

**Location**: `app/Services/PredictionStatsService.php:11`

```php
// Change from 60 seconds to 5 minutes
private const CACHE_TTL = 300;
```

#### 3. Combine COUNT queries into a single query

**Location**: `app/Http/Controllers/HomeController.php`

```php
// Before: 4 separate queries
$stats = [
    'totalMarkets' => Market::count(),
    'totalAssets' => Asset::count(),
    'totalPredictions' => PredictedAssetPrice::count(),
    'totalSectors' => Sector::count(),
];

// After: 1 single query
$stats = DB::selectOne("
    SELECT
        (SELECT COUNT(*) FROM markets) as \"totalMarkets\",
        (SELECT COUNT(*) FROM assets) as \"totalAssets\",
        (SELECT COUNT(*) FROM predicted_asset_prices) as \"totalPredictions\",
        (SELECT COUNT(*) FROM sectors) as \"totalSectors\"
");
$stats = (array) $stats;
```

#### 4. Add missing i18n keys

Add to your locale files:
- `resources/lang/en.json` or equivalent
- `resources/lang/ar.json` or equivalent

```json
{
    "markets.assets": "Assets",
    "markets.country": "Country"
}
```

---

### Medium Priority (Structural)

#### 5. Cache market/sector lists

Markets and sectors rarely change, so cache them:

```php
// In MarketController::index()
$markets = Cache::remember('markets_index_data', 300, function () {
    $predictionCounts = PredictionStatsService::countsByMarket();

    return Market::with('country')
        ->withCount('assets')
        ->get()
        ->map(fn ($market) => [
            'id' => $market->id,
            'name' => $market->name,
            'code' => $market->code,
            'country' => $market->country ? [
                'id' => $market->country->id,
                'name' => $market->country->name,
                'code' => $market->country->code,
            ] : null,
            'isOpen' => $market->isOpenNow(),
            'assetCount' => $market->assets_count,
            'predictionCount' => $predictionCounts->get($market->id, 0),
        ]);
});
```

#### 6. Optimize Market.isOpenNow() method

Ensure this method doesn't make additional database queries. It should only use data already loaded on the model.

#### 7. Consider smaller pagination for deferred props

```php
// Market assets: reduce from 10 to 5 if appropriate
$assets = Asset::where('market_id', $market->id)
    ->with(['sector', 'latestPrice', 'latestPrediction'])
    ->paginate(5);
```

---

### Long-term Solutions

#### 8. Database Connection Pooling

Consider using PgBouncer or Supabase's connection pooling to reduce connection overhead:

```env
# Use pooler connection string
DB_HOST=aws-0-us-east-1.pooler.supabase.com
DB_PORT=6543
```

#### 9. Materialized Views for Stats

Create materialized views for frequently accessed aggregated data:

```sql
CREATE MATERIALIZED VIEW market_stats AS
SELECT
    m.id as market_id,
    COUNT(DISTINCT a.id) as asset_count,
    COUNT(DISTINCT p.pid) as prediction_count
FROM markets m
LEFT JOIN assets a ON a.market_id = m.id
LEFT JOIN predicted_asset_prices p ON p.pid = a.inv_id
GROUP BY m.id;

-- Refresh periodically
REFRESH MATERIALIZED VIEW CONCURRENTLY market_stats;
```

#### 10. Redis/Memcached Caching Layer

Add a proper caching layer for frequently accessed data:

```php
// config/cache.php - use Redis
'default' => env('CACHE_DRIVER', 'redis'),
```

#### 11. Consider Database Region

If your Laravel app is deployed in a different region than Supabase, consider:
- Moving your app closer to the database
- Moving the database closer to your app
- Using a database in the same cloud provider/region

---

## Database Indexes (Already Present)

The following indexes exist and should be utilized:

| Table | Index | Columns |
|-------|-------|---------|
| asset_prices | asset_prices_pkey | (pid, timestamp) |
| asset_prices | asset_prices_pid_timestamp_idx | (pid, timestamp) |
| asset_prices | idx_asset_prices_pid | (pid) |
| predicted_asset_prices | predicted_asset_prices_pkey | (pid, timestamp) |
| predicted_asset_prices | idx_predicted_asset_prices_pid | (pid) |
| predicted_asset_prices | predicted_asset_prices_confidence_idx | (confidence) |

---

## Appendix: Raw Performance Data

### Home/Welcome Page
- Response Start: 3,425ms
- DOM Interactive: 3,437ms
- DOM Content Loaded: 3,506ms
- Load Complete: 3,506ms
- Transfer Size: 12,651 bytes

### Markets Index
- Response Start: 3,359ms
- DOM Interactive: 3,478ms
- DOM Content Loaded: 3,499ms
- Load Complete: 3,500ms
- Transfer Size: 11,884 bytes

### Market Show
- Response Start: 3,743ms
- DOM Interactive: 3,752ms
- DOM Content Loaded: 3,800ms
- Load Complete: 3,801ms
- Transfer Size: 9,775 bytes
- Deferred XHR: 6,499ms (3,883 bytes)

### Predictions
- Response Start: 2,806ms
- DOM Interactive: 2,814ms
- DOM Content Loaded: 2,871ms
- Load Complete: 2,873ms
- Transfer Size: 12,770 bytes
- Deferred XHR: 6,625ms (5,321 bytes)

### Sectors Index
- Response Start: 3,485ms
- DOM Interactive: 3,494ms
- DOM Content Loaded: 3,553ms
- Load Complete: 3,558ms
- Transfer Size: 28,958 bytes

### Sector Show
- Response Start: 4,211ms
- DOM Interactive: 4,221ms
- DOM Content Loaded: 4,256ms
- Load Complete: 4,256ms
- Transfer Size: 10,623 bytes
- Deferred XHR: 4,272ms (582 bytes)

### Asset Show
- Response Start: 4,233ms
- DOM Interactive: 4,242ms
- DOM Content Loaded: 4,280ms
- Load Complete: 4,280ms
- Transfer Size: 10,225 bytes
- Deferred XHR: 5,124ms (1,965,884 bytes / ~1.9MB)

### Search
- Response Start: 71ms
- DOM Interactive: 79ms
- DOM Content Loaded: 114ms
- Load Complete: 116ms
- Transfer Size: 9,283 bytes
