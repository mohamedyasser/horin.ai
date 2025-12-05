# Materialized Views Implementation Plan

## Overview

Implement materialized views for latest asset prices and predictions with freshness indicators to improve query performance while handling market-closed scenarios gracefully.

## Current Performance Issues

| Page | Current Time | Root Cause |
|------|--------------|------------|
| Markets Index | 3.3s | `latestPrice` subquery per asset |
| Sectors Index | 3.5s | `latestPrice` + prediction subqueries |
| Asset Show | 4.2s | Multiple subqueries + 1.9MB price history |
| Home/Welcome | 3.4s | Multiple COUNT queries + LATERAL JOIN |

## Implementation Tasks

### Task 1: Create Materialized Views Migration

**File:** `database/migrations/2025_12_03_XXXXXX_create_price_materialized_views.php`

Create two materialized views:

#### 1.1 Latest Asset Prices View

```sql
CREATE MATERIALIZED VIEW mv_latest_asset_prices AS
SELECT DISTINCT ON (pid)
    pid,
    last AS price,
    pcp AS percent_change,
    pc AS price_change,
    high,
    low,
    last_close,
    turnover_numeric AS volume,
    timestamp,
    to_timestamp(timestamp / 1000) AS price_time,
    -- Freshness indicators
    CASE
        WHEN timestamp >= (EXTRACT(EPOCH FROM (NOW() - INTERVAL '1 hour')) * 1000)::bigint
        THEN 'live'
        WHEN timestamp >= (EXTRACT(EPOCH FROM CURRENT_DATE) * 1000)::bigint
        THEN 'today'
        WHEN timestamp >= (EXTRACT(EPOCH FROM (CURRENT_DATE - INTERVAL '1 day')) * 1000)::bigint
        THEN 'yesterday'
        ELSE 'older'
    END AS freshness,
    ROUND(EXTRACT(EPOCH FROM (NOW() - to_timestamp(timestamp / 1000))) / 3600)::int AS hours_ago
FROM asset_prices
ORDER BY pid, timestamp DESC;

CREATE UNIQUE INDEX idx_mv_latest_prices_pid ON mv_latest_asset_prices (pid);
```

#### 1.2 Latest Predictions View

```sql
CREATE MATERIALIZED VIEW mv_latest_predictions AS
SELECT DISTINCT ON (pid)
    pid,
    symbol,
    model_name,
    price_prediction,
    confidence,
    horizon,
    prediction_time,
    timestamp,
    created_at,
    -- Freshness indicators
    CASE
        WHEN timestamp >= (EXTRACT(EPOCH FROM CURRENT_DATE) * 1000)::bigint
        THEN 'current'
        WHEN timestamp >= (EXTRACT(EPOCH FROM (CURRENT_DATE - INTERVAL '1 day')) * 1000)::bigint
        THEN 'yesterday'
        ELSE 'older'
    END AS freshness,
    (CURRENT_DATE - prediction_time::date)::int AS days_old
FROM predicted_asset_prices
ORDER BY pid, timestamp DESC;

CREATE UNIQUE INDEX idx_mv_latest_pred_pid ON mv_latest_predictions (pid);
```

#### 1.3 Refresh Function

```sql
CREATE OR REPLACE FUNCTION refresh_price_views()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_latest_asset_prices;
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_latest_predictions;
END;
$$ LANGUAGE plpgsql;
```

---

### Task 2: Create Laravel Models for Materialized Views

#### 2.1 LatestAssetPrice Model

**File:** `app/Models/LatestAssetPrice.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestAssetPrice extends Model
{
    protected $table = 'mv_latest_asset_prices';
    protected $primaryKey = 'pid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'high' => 'float',
            'low' => 'float',
            'last_close' => 'float',
            'volume' => 'float',
            'hours_ago' => 'integer',
            'price_time' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    public function isLive(): bool
    {
        return $this->freshness === 'live';
    }

    public function isToday(): bool
    {
        return in_array($this->freshness, ['live', 'today']);
    }
}
```

#### 2.2 LatestPrediction Model

**File:** `app/Models/LatestPrediction.php`

```php
<?php

namespace App\Models;

use App\Support\Horizon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestPrediction extends Model
{
    protected $table = 'mv_latest_predictions';
    protected $primaryKey = 'pid';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'price_prediction' => 'float',
            'confidence' => 'float',
            'horizon' => 'integer',
            'days_old' => 'integer',
            'prediction_time' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    public function getHorizonLabelAttribute(): string
    {
        return Horizon::label($this->horizon);
    }

    public function isCurrent(): bool
    {
        return $this->freshness === 'current';
    }
}
```

---

### Task 3: Update Asset Model with New Relationships

**File:** `app/Models/Asset.php`

Add new relationships that use the materialized views:

```php
// Add new relationships using materialized views
public function cachedPrice(): HasOne
{
    return $this->hasOne(LatestAssetPrice::class, 'pid', 'inv_id');
}

public function cachedPrediction(): HasOne
{
    return $this->hasOne(LatestPrediction::class, 'pid', 'inv_id');
}
```

Keep existing `latestPrice()` and `latestPrediction()` as fallbacks.

---

### Task 4: Update Controllers to Use Materialized Views

#### 4.1 MarketController

**File:** `app/Http/Controllers/MarketController.php`

Change `getMarketAssets()`:
```php
// Before
->with(['sector', 'latestPrice', 'latestPrediction'])

// After
->with(['sector', 'cachedPrice', 'cachedPrediction'])
```

Update mapping to include freshness:
```php
'latestPrice' => $asset->cachedPrice ? [
    'last' => $asset->cachedPrice->price,
    'pcp' => $asset->cachedPrice->percent_change,
    'freshness' => $asset->cachedPrice->freshness,
    'hoursAgo' => $asset->cachedPrice->hours_ago,
] : null,
```

#### 4.2 SectorController

**File:** `app/Http/Controllers/SectorController.php`

Replace the manual prediction fetch with:
```php
$query = Asset::where('sector_id', $sector->id)
    ->with(['market', 'cachedPrice', 'cachedPrediction']);
```

Remove the separate `$latestPredictions` query block (lines 119-128).

#### 4.3 HomeController

**File:** `app/Http/Controllers/HomeController.php`

Update `getTopMovers()` to use materialized view:
```php
private function getTopMovers(): array
{
    return Asset::with(['market', 'cachedPrice'])
        ->whereHas('cachedPrice')
        ->get()
        ->sortByDesc(fn ($a) => (float) str_replace('%', '', $a->cachedPrice->percent_change ?? '0'))
        ->take(5)
        ->map(fn ($asset) => [
            'id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'market' => ['code' => $asset->market->code],
            'currentPrice' => $asset->cachedPrice->price,
            'priceChangePercent' => (float) str_replace('%', '', $asset->cachedPrice->percent_change),
            'freshness' => $asset->cachedPrice->freshness,
        ])
        ->values()
        ->toArray();
}
```

#### 4.4 AssetController

**File:** `app/Http/Controllers/AssetController.php`

Update `show()` to use cached price:
```php
$asset->load(['market', 'sector', 'country', 'cachedPrice']);

// In the response:
'price' => $asset->cachedPrice ? [
    'last' => $asset->cachedPrice->price,
    'changePercent' => $asset->cachedPrice->percent_change,
    'high' => $asset->cachedPrice->high,
    'low' => $asset->cachedPrice->low,
    'previousClose' => $asset->cachedPrice->last_close,
    'volume' => $asset->cachedPrice->volume,
    'updatedAt' => $asset->cachedPrice->price_time->toISOString(),
    'freshness' => $asset->cachedPrice->freshness,
    'hoursAgo' => $asset->cachedPrice->hours_ago,
] : null,
```

#### 4.5 SearchController

**File:** `app/Http/Controllers/SearchController.php`

Update `searchWithIlike()` to use cached relationships:
```php
->with(['market', 'sector', 'cachedPrice'])
```

---

### Task 5: Create Refresh Command

**File:** `app/Console/Commands/RefreshPriceViews.php`

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshPriceViews extends Command
{
    protected $signature = 'views:refresh-prices';
    protected $description = 'Refresh materialized views for latest prices and predictions';

    public function handle(): int
    {
        $this->info('Refreshing materialized views...');

        $start = microtime(true);
        DB::statement('SELECT refresh_price_views()');
        $duration = round((microtime(true) - $start) * 1000);

        $this->info("Views refreshed in {$duration}ms");

        return self::SUCCESS;
    }
}
```

---

### Task 6: Schedule Automatic Refresh

**File:** `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('views:refresh-prices')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();
```

---

### Task 7: Update Frontend Components (Optional)

Add freshness indicators to Vue components:

```vue
<template>
  <div class="flex items-center gap-2">
    <span>{{ price }}</span>
    <span v-if="freshness === 'live'" class="badge badge-success">Live</span>
    <span v-else-if="freshness === 'today'" class="badge badge-info">Today</span>
    <span v-else class="badge badge-warning">{{ hoursAgo }}h ago</span>
  </div>
</template>
```

---

## File Changes Summary

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/2025_12_03_*_create_price_materialized_views.php` | Create | Migration for materialized views |
| `app/Models/LatestAssetPrice.php` | Create | Model for price view |
| `app/Models/LatestPrediction.php` | Create | Model for prediction view |
| `app/Models/Asset.php` | Modify | Add `cachedPrice()` and `cachedPrediction()` relationships |
| `app/Http/Controllers/MarketController.php` | Modify | Use cached relationships |
| `app/Http/Controllers/SectorController.php` | Modify | Use cached relationships, remove manual query |
| `app/Http/Controllers/HomeController.php` | Modify | Simplify `getTopMovers()` |
| `app/Http/Controllers/AssetController.php` | Modify | Use cached price |
| `app/Http/Controllers/SearchController.php` | Modify | Use cached relationships |
| `app/Console/Commands/RefreshPriceViews.php` | Create | Command to refresh views |
| `routes/console.php` | Modify | Schedule refresh |

---

## Expected Performance Improvement

| Page | Current | Expected | Improvement |
|------|---------|----------|-------------|
| Markets Index | 3.3s | ~200ms | **16x faster** |
| Sectors Index | 3.5s | ~250ms | **14x faster** |
| Market Show | 3.7s | ~300ms | **12x faster** |
| Sector Show | 4.2s | ~350ms | **12x faster** |
| Home/Welcome | 3.4s | ~200ms | **17x faster** |
| Search | 71ms | ~50ms | 1.4x faster |

---

## Rollback Strategy

If issues arise:
1. Change `cachedPrice` back to `latestPrice` in controllers
2. Change `cachedPrediction` back to `latestPrediction` in controllers
3. Drop migration: `php artisan migrate:rollback`

---

## Testing Checklist

- [ ] Verify views created successfully in Supabase
- [ ] Test all pages load correctly with cached data
- [ ] Test freshness indicators display correctly
- [ ] Test market-closed scenario (weekend/holiday)
- [ ] Test refresh command works
- [ ] Verify scheduled refresh runs
- [ ] Performance test with production data
- [ ] Test rollback procedure
