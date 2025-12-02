# Inertia Pages Data Loading Plan (MVP - Predictions Only)

## Overview

This document outlines the data loading strategy for all Inertia pages in the Horin application.

> **MVP Scope:** This plan focuses on **Predictions only**. Recommendations feature is deferred to a later phase.

### Loading Strategies Used

| Strategy | Description | Use Case |
|----------|-------------|----------|
| **Eager** | Load in controller, pass directly to page | Critical data, small datasets, above-fold content |
| **Deferred** | Load after initial page render via `Inertia::lazy()` | Heavy queries, secondary content, large datasets |

### Common Patterns

- All entities support bilingual (AR/EN) via `name` accessor based on locale
- Link field between Assets and time-series data: `Asset.inv_id` ↔ `pid`
- Pagination: 10 items per page (consistent across all paginated views)
- **Use relationships with eager loading** - no per-row queries
- **Use aggregated counts** - no N+1 count queries
- **Use centralized helpers** - Horizon mapping, pagination meta

### Timestamp Handling

> **Important:** Be consistent with timestamp formats across the codebase.

| Source | Format | Conversion |
|--------|--------|------------|
| `asset_prices.timestamp` | Epoch milliseconds (bigint) | `Carbon::createFromTimestamp($ts / 1000)` |
| `predicted_asset_prices.timestamp` | Epoch milliseconds (bigint) | `Carbon::createFromTimestamp($ts / 1000)` |
| `instant_indicators.timestamp` | Epoch milliseconds (bigint) | `Carbon::createFromTimestamp($ts / 1000)` |
| `created_at` / `updated_at` | ISO 8601 (datetime) | `->toISOString()` |

### Horizon Values (Standardized)

| Value (minutes) | Label | Description |
|-----------------|-------|-------------|
| 1440 | 1D | 1 Day |
| 10080 | 1W | 1 Week |
| 43200 | 1M | 1 Month |
| 129600 | 3M | 3 Months |

---

## Required Database Indexes

Before implementing, create these indexes for performance:

```sql
-- Predictions table indexes
CREATE INDEX predicted_asset_prices_pid_timestamp_idx
    ON predicted_asset_prices (pid, timestamp DESC);

CREATE INDEX predicted_asset_prices_horizon_timestamp_idx
    ON predicted_asset_prices (horizon, timestamp DESC);

CREATE INDEX predicted_asset_prices_confidence_idx
    ON predicted_asset_prices (confidence);

-- Covering index for heavy prediction list queries (optional, PostgreSQL 11+)
CREATE INDEX predicted_asset_prices_covering_idx
    ON predicted_asset_prices (pid, horizon, timestamp DESC)
    INCLUDE (price_prediction, confidence);

-- Asset prices table indexes
CREATE INDEX asset_prices_pid_timestamp_idx
    ON asset_prices (pid, timestamp DESC);

-- Assets table indexes for whereHas queries
CREATE INDEX assets_market_id_idx ON assets (market_id);
CREATE INDEX assets_sector_id_idx ON assets (sector_id);

-- Assets FTS index (see Search section)
CREATE INDEX assets_fts_idx ON assets USING GIN(fts);
```

### Migration for Indexes

```php
// database/migrations/xxxx_add_performance_indexes.php

public function up(): void
{
    // Predictions indexes
    DB::statement('CREATE INDEX IF NOT EXISTS predicted_asset_prices_pid_timestamp_idx ON predicted_asset_prices (pid, timestamp DESC)');
    DB::statement('CREATE INDEX IF NOT EXISTS predicted_asset_prices_horizon_timestamp_idx ON predicted_asset_prices (horizon, timestamp DESC)');
    DB::statement('CREATE INDEX IF NOT EXISTS predicted_asset_prices_confidence_idx ON predicted_asset_prices (confidence)');

    // Asset prices index
    DB::statement('CREATE INDEX IF NOT EXISTS asset_prices_pid_timestamp_idx ON asset_prices (pid, timestamp DESC)');

    // Assets foreign key indexes (for whereHas queries)
    DB::statement('CREATE INDEX IF NOT EXISTS assets_market_id_idx ON assets (market_id)');
    DB::statement('CREATE INDEX IF NOT EXISTS assets_sector_id_idx ON assets (sector_id)');
}

public function down(): void
{
    DB::statement('DROP INDEX IF EXISTS predicted_asset_prices_pid_timestamp_idx');
    DB::statement('DROP INDEX IF EXISTS predicted_asset_prices_horizon_timestamp_idx');
    DB::statement('DROP INDEX IF EXISTS predicted_asset_prices_confidence_idx');
    DB::statement('DROP INDEX IF EXISTS asset_prices_pid_timestamp_idx');
    DB::statement('DROP INDEX IF EXISTS assets_market_id_idx');
    DB::statement('DROP INDEX IF EXISTS assets_sector_id_idx');
}
```

---

## Centralized Helpers

### Horizon Helper

```php
// App\Support\Horizon.php

namespace App\Support;

final class Horizon
{
    public const ONE_DAY = 1440;
    public const ONE_WEEK = 10080;
    public const ONE_MONTH = 43200;
    public const THREE_MONTHS = 129600;

    public const ALL = [
        self::ONE_DAY,
        self::ONE_WEEK,
        self::ONE_MONTH,
        self::THREE_MONTHS,
    ];

    public const LABELS = [
        self::ONE_DAY => '1D',
        self::ONE_WEEK => '1W',
        self::ONE_MONTH => '1M',
        self::THREE_MONTHS => '3M',
    ];

    public static function label(int $minutes): string
    {
        return self::LABELS[$minutes] ?? "{$minutes}m";
    }

    public static function options(): array
    {
        return array_map(
            fn($value) => ['value' => $value, 'label' => self::LABELS[$value]],
            self::ALL
        );
    }
}
```

### Pagination Meta Helper

```php
// App\Support\PaginationHelper.php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PaginationHelper
{
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    public static function empty(int $perPage = 10): array
    {
        return [
            'currentPage' => 1,
            'lastPage' => 1,
            'perPage' => $perPage,
            'total' => 0,
        ];
    }
}
```

---

## Aggregated Counts Strategy

Instead of N+1 count queries per market/sector, use single grouped queries with short caching.

### PredictionStatsService (with Cache)

```php
// App\Services\PredictionStatsService.php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PredictionStatsService
{
    private const CACHE_TTL = 60; // seconds

    /**
     * Get prediction counts grouped by market_id (cached)
     */
    public static function countsByMarket(): Collection
    {
        return Cache::remember('prediction_counts_by_market', self::CACHE_TTL, function () {
            return DB::table('predicted_asset_prices as p')
                ->join('assets as a', 'p.pid', '=', 'a.inv_id')
                ->select('a.market_id', DB::raw('COUNT(*) as prediction_count'))
                ->groupBy('a.market_id')
                ->pluck('prediction_count', 'market_id');
        });
    }

    /**
     * Get prediction counts grouped by sector_id (cached)
     */
    public static function countsBySector(): Collection
    {
        return Cache::remember('prediction_counts_by_sector', self::CACHE_TTL, function () {
            return DB::table('predicted_asset_prices as p')
                ->join('assets as a', 'p.pid', '=', 'a.inv_id')
                ->select('a.sector_id', DB::raw('COUNT(*) as prediction_count'))
                ->groupBy('a.sector_id')
                ->pluck('prediction_count', 'sector_id');
        });
    }

    /**
     * Get prediction count for a specific market (uses cached map)
     */
    public static function countForMarket(string $marketId): int
    {
        return self::countsByMarket()->get($marketId, 0);
    }

    /**
     * Get prediction count for a specific sector (uses cached map)
     */
    public static function countForSector(string $sectorId): int
    {
        return self::countsBySector()->get($sectorId, 0);
    }

    /**
     * Clear all prediction count caches
     */
    public static function clearCache(): void
    {
        Cache::forget('prediction_counts_by_market');
        Cache::forget('prediction_counts_by_sector');
    }
}
```

---

## Page 1: Home Page (`Welcome.vue`)

**Route:** `/{locale}`

### Sections

| Section | Items | Sort/Filter | Loading |
|---------|-------|-------------|---------|
| Stats Summary | 4 stats | - | Eager |
| Featured Predictions | 5 | Sortable: confidence (default) / recent | Deferred |
| Market Overview | All (~6) | - | Eager |
| Top Movers | 5 | Highest price change % (SQL) | Deferred |
| Sector Highlights | All (~17) | - | Eager |
| Recent Predictions | 5 | Latest by timestamp | Deferred |

### Props Structure

```typescript
interface HomePageProps {
  // Eager
  stats: {
    totalMarkets: number;
    totalAssets: number;
    totalPredictions: number;
    totalSectors: number;
  };
  markets: Array<{
    id: string;
    name: string;
    code: string;
    country: {
      id: string;
      name: string;
      code: string;
    };
    isOpen: boolean;
    assetCount: number;
    predictionCount: number;
  }>;
  sectors: Array<{
    id: string;
    name: string;
    assetCount: number;
    predictionCount: number;
  }>;

  // Deferred
  featuredPredictions: Array<{
    id: string;
    asset: {
      id: string;
      symbol: string;
      name: string;
      market: { code: string };
    };
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    expectedGainPercent: number;
    timestamp: string;
  }>;
  topMovers: Array<{
    id: string;
    symbol: string;
    name: string;
    market: { code: string };
    currentPrice: number;
    priceChangePercent: number;
  }>;
  recentPredictions: Array<{
    id: string;
    asset: {
      id: string;
      symbol: string;
      name: string;
    };
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    timestamp: string;
  }>;
}
```

### Controller Implementation

```php
// App\Http\Controllers\HomeController.php

use App\Services\PredictionStatsService;

public function __invoke(Request $request)
{
    // Get aggregated prediction counts (single query each)
    $predictionCountsByMarket = PredictionStatsService::countsByMarket();
    $predictionCountsBySector = PredictionStatsService::countsBySector();

    // Eager - Stats (4 simple count queries)
    $stats = [
        'totalMarkets' => Market::count(),
        'totalAssets' => Asset::count(),
        'totalPredictions' => PredictedAssetPrice::count(),
        'totalSectors' => Sector::count(),
    ];

    // Eager - Markets with country and counts
    $markets = Market::with('country')
        ->withCount('assets')
        ->get()
        ->map(fn($market) => [
            'id' => $market->id,
            'name' => $market->name,
            'code' => $market->code,
            'country' => [
                'id' => $market->country->id,
                'name' => $market->country->name,
                'code' => $market->country->code,
            ],
            'isOpen' => $market->isOpenNow(),
            'assetCount' => $market->assets_count,
            'predictionCount' => $predictionCountsByMarket->get($market->id, 0),
        ]);

    // Eager - Sectors with counts
    $sectors = Sector::withCount('assets')
        ->get()
        ->map(fn($sector) => [
            'id' => $sector->id,
            'name' => $sector->name,
            'assetCount' => $sector->assets_count,
            'predictionCount' => $predictionCountsBySector->get($sector->id, 0),
        ]);

    return Inertia::render('Welcome', [
        'stats' => $stats,
        'markets' => $markets,
        'sectors' => $sectors,

        // Deferred - Featured Predictions
        'featuredPredictions' => Inertia::lazy(fn() => $this->getFeaturedPredictions($request)),

        // Deferred - Top Movers (SQL-based sorting)
        'topMovers' => Inertia::lazy(fn() => $this->getTopMovers()),

        // Deferred - Recent Predictions
        'recentPredictions' => Inertia::lazy(fn() => $this->getRecentPredictions()),
    ]);
}

private function getFeaturedPredictions(Request $request): array
{
    $sortBy = $request->input('sort', 'confidence');
    $sortBy = in_array($sortBy, ['confidence', 'timestamp']) ? $sortBy : 'confidence';

    return PredictedAssetPrice::with(['asset.market'])
        ->orderByDesc($sortBy)
        ->limit(5)
        ->get()
        ->map(fn($p) => $this->formatPrediction($p))
        ->toArray();
}

private function getTopMovers(): array
{
    // Use derived table for latest prices - more efficient than whereIn subquery
    // This approach joins with a subquery that finds max timestamp per pid,
    // avoiding the expensive correlated subquery pattern
    return DB::table('asset_prices as ap')
        ->joinSub(
            DB::table('asset_prices')
                ->select('pid', DB::raw('MAX(timestamp) as max_ts'))
                ->groupBy('pid'),
            'latest',
            fn($join) => $join->on('ap.pid', '=', 'latest.pid')
                              ->on('ap.timestamp', '=', 'latest.max_ts')
        )
        ->join('assets as a', 'ap.pid', '=', 'a.inv_id')
        ->join('markets as m', 'a.market_id', '=', 'm.id')
        ->orderByDesc(DB::raw('CAST(ap.pcp AS DECIMAL)'))
        ->limit(5)
        ->select([
            'a.id', 'a.symbol', 'a.name_en', 'a.name_ar',
            'm.code as market_code',
            'ap.last as current_price', 'ap.pcp as price_change_percent'
        ])
        ->get()
        ->map(fn($row) => [
            'id' => $row->id,
            'symbol' => $row->symbol,
            'name' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
            'market' => ['code' => $row->market_code],
            'currentPrice' => (float) $row->current_price,
            'priceChangePercent' => (float) $row->price_change_percent,
        ])
        ->toArray();
}

private function getRecentPredictions(): array
{
    return PredictedAssetPrice::with('asset')
        ->orderByDesc('timestamp')
        ->limit(5)
        ->get()
        ->map(fn($p) => [
            'id' => $p->pid . '-' . $p->timestamp,
            'asset' => [
                'id' => $p->asset->id,
                'symbol' => $p->asset->symbol,
                'name' => $p->asset->name,
            ],
            'predictedPrice' => $p->price_prediction,
            'confidence' => $p->confidence,
            'horizon' => $p->horizon,
            'horizonLabel' => $this->horizonLabel($p->horizon),
            'timestamp' => $p->created_at?->toISOString(),
        ])
        ->toArray();
}

private function horizonLabel(int $minutes): string
{
    return match($minutes) {
        1440 => '1D',
        10080 => '1W',
        43200 => '1M',
        129600 => '3M',
        default => "{$minutes}m",
    };
}

private function formatPrediction($prediction): array
{
    $currentPrice = $prediction->asset->latestPrice?->last ?? 0;
    $expectedGain = $currentPrice > 0
        ? (($prediction->price_prediction - $currentPrice) / $currentPrice) * 100
        : 0;

    return [
        'id' => $prediction->pid . '-' . $prediction->timestamp,
        'asset' => [
            'id' => $prediction->asset->id,
            'symbol' => $prediction->asset->symbol,
            'name' => $prediction->asset->name,
            'market' => ['code' => $prediction->asset->market->code],
        ],
        'predictedPrice' => $prediction->price_prediction,
        'confidence' => $prediction->confidence,
        'horizon' => $prediction->horizon,
        'horizonLabel' => $this->horizonLabel($prediction->horizon),
        'expectedGainPercent' => round($expectedGain, 2),
        'timestamp' => $prediction->created_at?->toISOString(),
    ];
}
```

---

## Page 2: Markets Page (`Markets.vue`)

**Route:** `/{locale}/markets`

### Per Market Card

| Field | Source |
|-------|--------|
| Market name (localized) | `market.name` |
| Country name + flag | `market.country.name`, `market.country.code` |
| Market code | `market.code` |
| Open/Closed status | `market.isOpenNow()` |
| Asset count | `market.assets_count` |
| Prediction count | Aggregated query |

### Props Structure

```typescript
interface MarketsPageProps {
  markets: Array<{
    id: string;
    name: string;
    code: string;
    country: {
      id: string;
      name: string;
      code: string;
    };
    isOpen: boolean;
    assetCount: number;
    predictionCount: number;
  }>;
}
```

### Controller Implementation

```php
// App\Http\Controllers\MarketController.php

public function index()
{
    // Single aggregated query for prediction counts
    $predictionCounts = PredictionStatsService::countsByMarket();

    $markets = Market::with('country')
        ->withCount('assets')
        ->get()
        ->map(fn($market) => [
            'id' => $market->id,
            'name' => $market->name,
            'code' => $market->code,
            'country' => [
                'id' => $market->country->id,
                'name' => $market->country->name,
                'code' => $market->country->code,
            ],
            'isOpen' => $market->isOpenNow(),
            'assetCount' => $market->assets_count,
            'predictionCount' => $predictionCounts->get($market->id, 0),
        ]);

    return Inertia::render('Markets', [
        'markets' => $markets,
    ]);
}
```

### Loading Strategy

| Data | Loading | Queries |
|------|---------|---------|
| Markets with country | **Eager** | 1 query with eager load |
| Asset counts | **Eager** | Part of same query (withCount) |
| Prediction counts | **Eager** | 1 aggregated query |

**Total: 2 queries** (down from N+1)

---

## Page 3: Market Detail (`markets/Show.vue`)

**Route:** `/{locale}/markets/{market}`

### Market Header

| Field | Source |
|-------|--------|
| Market name (localized) | `market.name` |
| Country name + flag | `market.country.name`, `market.country.code` |
| Market code | `market.code` |
| Open/Closed status | `market.isOpenNow()` |
| Trading hours | `market.open_at`, `market.close_at` |
| TradingView link | `market.tv_link` |
| Total assets | `market.assets_count` |
| Total predictions | Aggregated query |

### Assets List (per asset)

| Field | Source |
|-------|--------|
| Symbol | `asset.symbol` |
| Name (localized) | `asset.name` |
| Sector | `asset.sector.name` |
| Latest price | `asset.latestPrice.last` |
| Price change % | `asset.latestPrice.pcp` |
| Latest prediction | `asset.latestPrediction.price_prediction` |
| Confidence | `asset.latestPrediction.confidence` |

### Pagination: 10 assets per page

### Props Structure

```typescript
interface MarketDetailPageProps {
  // Eager
  market: {
    id: string;
    name: string;
    code: string;
    country: {
      id: string;
      name: string;
      code: string;
    };
    isOpen: boolean;
    openAt: string | null;
    closeAt: string | null;
    tvLink: string | null;
    assetCount: number;
    predictionCount: number;
  };

  // Deferred
  assets: {
    data: Array<{
      id: string;
      symbol: string;
      name: string;
      sector: { id: string; name: string } | null;
      latestPrice: {
        last: number;
        pcp: string;
      } | null;
      latestPrediction: {
        predictedPrice: number;
        confidence: number;
        horizon: number;
        horizonLabel: string;
      } | null;
    }>;
    meta: PaginationMeta;
  };
}
```

### Controller Implementation

```php
public function show(string $locale, Market $market, Request $request)
{
    $market->load('country');
    $market->loadCount('assets');

    return Inertia::render('markets/Show', [
        // Eager - Market header
        'market' => [
            'id' => $market->id,
            'name' => $market->name,
            'code' => $market->code,
            'country' => [
                'id' => $market->country->id,
                'name' => $market->country->name,
                'code' => $market->country->code,
            ],
            'isOpen' => $market->isOpenNow(),
            'openAt' => $market->open_at,
            'closeAt' => $market->close_at,
            'tvLink' => $market->tv_link,
            'assetCount' => $market->assets_count,
            'predictionCount' => PredictionStatsService::countForMarket($market->id),
        ],

        // Deferred - Assets with relationships (NO N+1)
        'assets' => Inertia::lazy(fn() => $this->getMarketAssets($market, $request)),
    ]);
}

private function getMarketAssets(Market $market, Request $request): array
{
    $assets = Asset::where('market_id', $market->id)
        ->with(['sector', 'latestPrice', 'latestPrediction'])
        ->paginate(10);

    return [
        'data' => $assets->map(fn($asset) => [
            'id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'sector' => $asset->sector ? [
                'id' => $asset->sector->id,
                'name' => $asset->sector->name,
            ] : null,
            'latestPrice' => $asset->latestPrice ? [
                'last' => (float) $asset->latestPrice->last,
                'pcp' => $asset->latestPrice->pcp,
            ] : null,
            'latestPrediction' => $asset->latestPrediction ? [
                'predictedPrice' => $asset->latestPrediction->price_prediction,
                'confidence' => $asset->latestPrediction->confidence,
                'horizon' => $asset->latestPrediction->horizon,
                'horizonLabel' => $this->horizonLabel($asset->latestPrediction->horizon),
            ] : null,
        ])->toArray(),
        'meta' => [
            'currentPage' => $assets->currentPage(),
            'lastPage' => $assets->lastPage(),
            'perPage' => $assets->perPage(),
            'total' => $assets->total(),
        ],
    ];
}
```

### Loading Strategy

| Data | Loading | Queries |
|------|---------|---------|
| Market header | **Eager** | 1 query |
| Prediction count | **Eager** | 1 aggregated query |
| Assets (paginated) | **Deferred** | 1 query with eager loads |

**Total: 3 queries** (using relationships, no N+1)

---

## Page 4: Sectors Page (`Sectors.vue`)

**Route:** `/{locale}/sectors`

### Per Sector Card

| Field | Source |
|-------|--------|
| Sector name (localized) | `sector.name` |
| Description (localized) | `sector.description` |
| Asset count | `sector.assets_count` |
| Prediction count | Aggregated query |
| Markets breakdown | Aggregated query |

### Props Structure

```typescript
interface SectorsPageProps {
  sectors: Array<{
    id: string;
    name: string;
    description: string;
    assetCount: number;
    predictionCount: number;
    marketsBreakdown: Array<{
      marketId: string;
      marketCode: string;
      marketName: string;
      count: number;
    }>;
  }>;
}
```

### Controller Implementation

```php
public function index()
{
    // Aggregated prediction counts (1 query)
    $predictionCounts = PredictionStatsService::countsBySector();

    // Aggregated markets breakdown (1 query)
    $marketsBreakdown = DB::table('assets')
        ->join('markets', 'assets.market_id', '=', 'markets.id')
        ->whereNotNull('assets.sector_id')
        ->select(
            'assets.sector_id',
            'markets.id as market_id',
            'markets.code as market_code',
            'markets.name_en',
            'markets.name_ar',
            DB::raw('COUNT(*) as count')
        )
        ->groupBy('assets.sector_id', 'markets.id', 'markets.code', 'markets.name_en', 'markets.name_ar')
        ->get()
        ->groupBy('sector_id');

    $sectors = Sector::withCount('assets')
        ->get()
        ->map(fn($sector) => [
            'id' => $sector->id,
            'name' => $sector->name,
            'description' => app()->getLocale() === 'ar'
                ? $sector->description_ar
                : $sector->description_en,
            'assetCount' => $sector->assets_count,
            'predictionCount' => $predictionCounts->get($sector->id, 0),
            'marketsBreakdown' => ($marketsBreakdown->get($sector->id) ?? collect())
                ->map(fn($row) => [
                    'marketId' => $row->market_id,
                    'marketCode' => $row->market_code,
                    'marketName' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
                    'count' => $row->count,
                ])->toArray(),
        ]);

    return Inertia::render('Sectors', [
        'sectors' => $sectors,
    ]);
}
```

### Loading Strategy

| Data | Loading | Queries |
|------|---------|---------|
| Sectors with asset count | **Eager** | 1 query |
| Prediction counts | **Eager** | 1 aggregated query |
| Markets breakdown | **Eager** | 1 aggregated query |

**Total: 3 queries** (down from N+1)

---

## Page 5: Sector Detail (`sectors/Show.vue`)

**Route:** `/{locale}/sectors/{sector}`

### Sector Header

| Field | Source |
|-------|--------|
| Sector name (localized) | `sector.name` |
| Description (localized) | `sector.description` |
| Total assets | `sector.assets_count` |
| Total predictions | Aggregated query |
| Markets breakdown | Aggregated query |

### Assets List (per asset)

| Field | Source |
|-------|--------|
| Symbol | `asset.symbol` |
| Name (localized) | `asset.name` |
| Market | `asset.market.name` |
| Latest price | `asset.latestPrice.last` |
| Price change % | `asset.latestPrice.pcp` |
| Latest prediction | `asset.latestPrediction.price_prediction` |
| Confidence | `asset.latestPrediction.confidence` |

### Filters: By Market
### Pagination: 10 assets per page

### Props Structure

```typescript
interface SectorDetailPageProps {
  // Eager
  sector: {
    id: string;
    name: string;
    description: string;
    assetCount: number;
    predictionCount: number;
    marketsBreakdown: Array<{
      marketId: string;
      marketCode: string;
      marketName: string;
      count: number;
    }>;
  };

  // For filter dropdown
  markets: Array<{
    id: string;
    code: string;
    name: string;
  }>;

  // Deferred
  assets: {
    data: Array<{
      id: string;
      symbol: string;
      name: string;
      market: { id: string; code: string; name: string };
      latestPrice: {
        last: number;
        pcp: string;
      } | null;
      latestPrediction: {
        predictedPrice: number;
        confidence: number;
        horizon: number;
        horizonLabel: string;
      } | null;
    }>;
    meta: PaginationMeta;
  };

  // Current filter state
  filters: {
    marketId: string | null;
  };
}
```

### Controller Implementation

```php
public function show(string $locale, Sector $sector, Request $request)
{
    $sector->loadCount('assets');

    // Markets breakdown (1 query)
    $marketsBreakdown = DB::table('assets')
        ->join('markets', 'assets.market_id', '=', 'markets.id')
        ->where('assets.sector_id', $sector->id)
        ->select(
            'markets.id as market_id',
            'markets.code as market_code',
            'markets.name_en',
            'markets.name_ar',
            DB::raw('COUNT(*) as count')
        )
        ->groupBy('markets.id', 'markets.code', 'markets.name_en', 'markets.name_ar')
        ->get();

    // Markets for filter dropdown
    $markets = Market::select('id', 'code', 'name_en', 'name_ar')->get();

    return Inertia::render('sectors/Show', [
        // Eager
        'sector' => [
            'id' => $sector->id,
            'name' => $sector->name,
            'description' => app()->getLocale() === 'ar'
                ? $sector->description_ar
                : $sector->description_en,
            'assetCount' => $sector->assets_count,
            'predictionCount' => PredictionStatsService::countForSector($sector->id),
            'marketsBreakdown' => $marketsBreakdown->map(fn($row) => [
                'marketId' => $row->market_id,
                'marketCode' => $row->market_code,
                'marketName' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
                'count' => $row->count,
            ])->toArray(),
        ],
        'markets' => $markets->map(fn($m) => [
            'id' => $m->id,
            'code' => $m->code,
            'name' => $m->name,
        ]),
        'filters' => [
            'marketId' => $request->input('market_id'),
        ],

        // Deferred
        'assets' => Inertia::lazy(fn() => $this->getSectorAssets($sector, $request)),
    ]);
}

private function getSectorAssets(Sector $sector, Request $request): array
{
    $query = Asset::where('sector_id', $sector->id)
        ->with(['market', 'latestPrice', 'latestPrediction']);

    if ($request->filled('market_id')) {
        $query->where('market_id', $request->input('market_id'));
    }

    $assets = $query->paginate(10);

    return [
        'data' => $assets->map(fn($asset) => [
            'id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'market' => [
                'id' => $asset->market->id,
                'code' => $asset->market->code,
                'name' => $asset->market->name,
            ],
            'latestPrice' => $asset->latestPrice ? [
                'last' => (float) $asset->latestPrice->last,
                'pcp' => $asset->latestPrice->pcp,
            ] : null,
            'latestPrediction' => $asset->latestPrediction ? [
                'predictedPrice' => $asset->latestPrediction->price_prediction,
                'confidence' => $asset->latestPrediction->confidence,
                'horizon' => $asset->latestPrediction->horizon,
                'horizonLabel' => $this->horizonLabel($asset->latestPrediction->horizon),
            ] : null,
        ])->toArray(),
        'meta' => [
            'currentPage' => $assets->currentPage(),
            'lastPage' => $assets->lastPage(),
            'perPage' => $assets->perPage(),
            'total' => $assets->total(),
        ],
    ];
}
```

---

## Page 6: Predictions Page (`Predictions.vue`)

**Route:** `/{locale}/predictions`

> **MVP Note:** Recommendation filter removed. Focus on predictions only.

### Per Prediction Row

| Field | Source |
|-------|--------|
| Asset symbol | `prediction.asset.symbol` |
| Asset name (localized) | `prediction.asset.name` |
| Market | `prediction.asset.market.name` |
| Sector | `prediction.asset.sector.name` |
| Predicted price | `prediction.price_prediction` |
| Current price | `prediction.asset.latestPrice.last` |
| Expected gain % | Computed |
| Confidence | `prediction.confidence` |
| Horizon (1D, 1W, 1M, 3M) | `prediction.horizon` |
| Timestamp | `prediction.timestamp` |

### Filters (MVP)

| Filter | Type |
|--------|------|
| By Market | Single select |
| By Sector | Single select |
| By Horizon | Single select (1D, 1W, 1M, 3M) |
| By Confidence | Min threshold (0-100) |

### Sorting Options (MVP)

| Sort | Direction | Status |
|------|-----------|--------|
| Confidence | High to Low (default) | ✅ Available |
| Most recent | Newest first | ✅ Available |
| Expected gain % | High to Low | ⏳ Deferred (requires SQL join with latest prices) |

> **Note:** Expected gain % sorting is deferred to post-MVP. Correct implementation requires joining with latest prices in SQL before pagination. Current PHP-based sorting only works within a single page, not across the full dataset.

### Pagination: 10 predictions per page

### Props Structure

```typescript
interface PredictionsPageProps {
  // Eager - Filter options
  filterOptions: {
    markets: Array<{ id: string; code: string; name: string }>;
    sectors: Array<{ id: string; name: string }>;
    horizons: Array<{ value: number; label: string }>;
  };

  // Deferred
  predictions: {
    data: Array<{
      id: string;
      asset: {
        id: string;
        symbol: string;
        name: string;
        market: { id: string; code: string; name: string };
        sector: { id: string; name: string } | null;
        currentPrice: number | null;
      };
      predictedPrice: number;
      expectedGainPercent: number;
      confidence: number;
      horizon: number;
      horizonLabel: string;
      timestamp: string;
    }>;
    meta: PaginationMeta;
  };

  // Current filter/sort state
  filters: {
    marketId: string | null;
    sectorId: string | null;
    horizon: number | null;
    minConfidence: number;
  };
  sort: {
    field: 'confidence' | 'timestamp'; // expectedGain deferred to post-MVP
    direction: 'asc' | 'desc';
  };
}
```

### Controller Implementation

```php
public function index(Request $request)
{
    // Eager - Filter options
    $filterOptions = [
        'markets' => Market::select('id', 'code', 'name_en', 'name_ar')
            ->get()
            ->map(fn($m) => ['id' => $m->id, 'code' => $m->code, 'name' => $m->name]),
        'sectors' => Sector::select('id', 'name_en', 'name_ar')
            ->get()
            ->map(fn($s) => ['id' => $s->id, 'name' => $s->name]),
        'horizons' => [
            ['value' => 1440, 'label' => '1D'],
            ['value' => 10080, 'label' => '1W'],
            ['value' => 43200, 'label' => '1M'],
            ['value' => 129600, 'label' => '3M'],
        ],
    ];

    $filters = [
        'marketId' => $request->input('market_id'),
        'sectorId' => $request->input('sector_id'),
        'horizon' => $request->input('horizon'),
        'minConfidence' => (int) $request->input('min_confidence', 0),
    ];

    $sort = [
        'field' => $request->input('sort', 'confidence'),
        'direction' => $request->input('direction', 'desc'),
    ];

    return Inertia::render('Predictions', [
        'filterOptions' => $filterOptions,
        'filters' => $filters,
        'sort' => $sort,
        'predictions' => Inertia::lazy(fn() => $this->getPredictions($filters, $sort)),
    ]);
}

private function getPredictions(array $filters, array $sort): array
{
    $query = PredictedAssetPrice::with([
        'asset.market',
        'asset.sector',
        'asset.latestPrice',
    ]);

    // Apply filters
    if ($filters['marketId']) {
        $query->whereHas('asset', fn($q) => $q->where('market_id', $filters['marketId']));
    }
    if ($filters['sectorId']) {
        $query->whereHas('asset', fn($q) => $q->where('sector_id', $filters['sectorId']));
    }
    if ($filters['horizon']) {
        $query->where('horizon', $filters['horizon']);
    }
    if ($filters['minConfidence'] > 0) {
        $query->where('confidence', '>=', $filters['minConfidence']);
    }

    // Apply sorting (MVP: confidence and timestamp only)
    // Note: expectedGain sorting deferred - requires SQL join with latest prices
    $sortField = match($sort['field']) {
        'timestamp' => 'timestamp',
        default => 'confidence',
    };
    $query->orderBy($sortField, $sort['direction']);

    $predictions = $query->paginate(10);

    // Transform with computed fields
    $data = $predictions->map(function ($p) {
        $currentPrice = $p->asset->latestPrice?->last;
        $expectedGain = $currentPrice && $currentPrice > 0
            ? (($p->price_prediction - $currentPrice) / $currentPrice) * 100
            : 0;

        return [
            'id' => $p->pid . '-' . $p->timestamp,
            'asset' => [
                'id' => $p->asset->id,
                'symbol' => $p->asset->symbol,
                'name' => $p->asset->name,
                'market' => [
                    'id' => $p->asset->market->id,
                    'code' => $p->asset->market->code,
                    'name' => $p->asset->market->name,
                ],
                'sector' => $p->asset->sector ? [
                    'id' => $p->asset->sector->id,
                    'name' => $p->asset->sector->name,
                ] : null,
                'currentPrice' => $currentPrice ? (float) $currentPrice : null,
            ],
            'predictedPrice' => $p->price_prediction,
            'expectedGainPercent' => round($expectedGain, 2),
            'confidence' => $p->confidence,
            'horizon' => $p->horizon,
            'horizonLabel' => Horizon::label($p->horizon),
            'timestamp' => Carbon::createFromTimestamp($p->timestamp / 1000)->toISOString(),
        ];
    });

    return [
        'data' => $data->toArray(),
        'meta' => PaginationHelper::meta($predictions),
    ];
}
```

---

## Page 7: Search Page (`Search.vue`)

**Route:** `/{locale}/search`

> **MVP Simplification:** Search **Assets only**. Markets and Sectors tabs deferred to later phase.

### Search Behavior

- Submit on **Enter key** or **button click**
- Uses **PostgreSQL Full-Text Search**

### Results: Assets Only (MVP)

| Field | Source |
|-------|--------|
| Symbol | `asset.symbol` |
| Name (localized) | `asset.name` |
| Market | `asset.market.name` |
| Sector | `asset.sector.name` |
| Latest price | `asset.latestPrice.last` |
| Price change % | `asset.latestPrice.pcp` |

### Pagination: 10 results per page

### Props Structure

```typescript
interface SearchPageProps {
  query: string;

  // Deferred
  results: {
    data: Array<{
      id: string;
      symbol: string;
      name: string;
      market: { id: string; code: string; name: string };
      sector: { id: string; name: string } | null;
      latestPrice: {
        last: number;
        pcp: string;
      } | null;
    }>;
    meta: PaginationMeta;
  };

  totalCount: number;
}
```

### Controller Implementation

```php
public function index(Request $request)
{
    $query = trim($request->input('q', ''));

    if (empty($query)) {
        return Inertia::render('Search', [
            'query' => '',
            'results' => ['data' => [], 'meta' => PaginationHelper::empty()],
            'totalCount' => 0,
        ]);
    }

    return Inertia::render('Search', [
        'query' => $query,
        'results' => Inertia::lazy(fn() => $this->searchAssets($query, $request)),
        'totalCount' => Inertia::lazy(fn() => $this->countAssets($query)),
    ]);
}

private function searchAssets(string $query, Request $request): array
{
    // Use 'simple' config for language-agnostic matching (works with Arabic)
    // Symbol gets exact prefix match via ILIKE for better UX
    $assets = Asset::where(function ($q) use ($query) {
            $q->whereRaw("fts @@ websearch_to_tsquery('simple', ?)", [$query])
              ->orWhere('symbol', 'ILIKE', $query . '%');
        })
        ->with(['market', 'sector', 'latestPrice'])
        ->paginate(10);

    return [
        'data' => $assets->map(fn($asset) => [
            'id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'market' => [
                'id' => $asset->market->id,
                'code' => $asset->market->code,
                'name' => $asset->market->name,
            ],
            'sector' => $asset->sector ? [
                'id' => $asset->sector->id,
                'name' => $asset->sector->name,
            ] : null,
            'latestPrice' => $asset->latestPrice ? [
                'last' => (float) $asset->latestPrice->last,
                'pcp' => $asset->latestPrice->pcp,
            ] : null,
        ])->toArray(),
        'meta' => PaginationHelper::meta($assets),
    ];
}

private function countAssets(string $query): int
{
    return Asset::where(function ($q) use ($query) {
            $q->whereRaw("fts @@ websearch_to_tsquery('simple', ?)", [$query])
              ->orWhere('symbol', 'ILIKE', $query . '%');
        })->count();
}

// Note: Use PaginationHelper::empty() instead of emptyMeta()
```

### FTS Migration

```php
// database/migrations/xxxx_add_fts_to_assets_table.php

public function up(): void
{
    // Add tsvector column
    DB::statement("ALTER TABLE assets ADD COLUMN IF NOT EXISTS fts tsvector");

    // Populate existing data with weighted search
    // Using 'simple' config for language-agnostic matching (works with Arabic text)
    DB::statement("
        UPDATE assets SET fts =
            setweight(to_tsvector('simple', coalesce(symbol, '')), 'A') ||
            setweight(to_tsvector('simple', coalesce(name_en, '')), 'B') ||
            setweight(to_tsvector('simple', coalesce(name_ar, '')), 'B') ||
            setweight(to_tsvector('simple', coalesce(description_en, '')), 'C') ||
            setweight(to_tsvector('simple', coalesce(description_ar, '')), 'C')
    ");

    // Create GIN index
    DB::statement("CREATE INDEX IF NOT EXISTS assets_fts_idx ON assets USING GIN(fts)");

    // Create trigger for auto-update
    DB::statement("
        CREATE OR REPLACE FUNCTION assets_fts_trigger() RETURNS trigger AS \$\$
        BEGIN
            NEW.fts :=
                setweight(to_tsvector('simple', coalesce(NEW.symbol, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(NEW.name_en, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(NEW.name_ar, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(NEW.description_en, '')), 'C') ||
                setweight(to_tsvector('simple', coalesce(NEW.description_ar, '')), 'C');
            RETURN NEW;
        END
        \$\$ LANGUAGE plpgsql
    ");

    DB::statement("
        DROP TRIGGER IF EXISTS assets_fts_update ON assets;
        CREATE TRIGGER assets_fts_update
        BEFORE INSERT OR UPDATE ON assets
        FOR EACH ROW EXECUTE FUNCTION assets_fts_trigger()
    ");
}

public function down(): void
{
    DB::statement("DROP TRIGGER IF EXISTS assets_fts_update ON assets");
    DB::statement("DROP FUNCTION IF EXISTS assets_fts_trigger()");
    DB::statement("DROP INDEX IF EXISTS assets_fts_idx");
    DB::statement("ALTER TABLE assets DROP COLUMN IF EXISTS fts");
}
```

> **FTS Language Note:** Using `'simple'` configuration instead of `'english'` for language-agnostic matching. The `simple` config tokenizes by whitespace without stemming, which works correctly for both English and Arabic text. Symbol searches also use ILIKE prefix matching for better UX when typing stock symbols.

---

## Page 8: Asset Detail (`assets/Show.vue`)

**Route:** `/{locale}/assets/{asset}`

> **MVP Note:** Recommendation section hidden. Data structure kept for future use.

### Asset Header

| Field | Source |
|-------|--------|
| Symbol | `asset.symbol` |
| Name (localized) | `asset.name` |
| Market | `asset.market.name` |
| Sector | `asset.sector.name` |
| Country | `asset.country.name` |
| Currency | `asset.currency` |
| Asset type | `asset.type` |

### Price Section

| Field | Source |
|-------|--------|
| Current price | `asset.latestPrice.last` |
| Price change % | `asset.latestPrice.pcp` |
| Day high | `asset.latestPrice.high` |
| Day low | `asset.latestPrice.low` |
| Previous close | `asset.latestPrice.last_close` |
| Volume | `asset.latestPrice.turnover` |

### Predictions Section

| Field | Source |
|-------|--------|
| All horizons (1D, 1W, 1M, 3M) | Multiple predictions |
| Predicted price per horizon | `prediction.price_prediction` |
| Confidence per horizon | `prediction.confidence` |
| Expected gain % | Computed |

### Indicators Section

| Field | Source |
|-------|--------|
| RSI | `indicator.rsi` |
| MACD | `indicator.macd_line`, `indicator.macd_signal`, `indicator.macd_histogram` |
| EMA | `indicator.ema` |
| SMA | `indicator.sma` |
| ATR | `indicator.atr` |
| Bollinger Bands | `indicator.bb_upper`, `indicator.bb_middle`, `indicator.bb_lower` |

### History Section

| Field | Details |
|-------|---------|
| Price chart | Selectable: 30, 90, 365 days (default: 30) |
| Prediction history | Last 10 predictions |

### Props Structure

```typescript
interface AssetDetailPageProps {
  // Eager
  asset: {
    id: string;
    symbol: string;
    name: string;
    type: string;
    currency: string;
    market: {
      id: string;
      code: string;
      name: string;
    };
    sector: {
      id: string;
      name: string;
    } | null;
    country: {
      id: string;
      name: string;
      code: string;
    };
  };

  price: {
    last: number;
    changePercent: string;
    high: number;
    low: number;
    previousClose: number;
    volume: string;
    updatedAt: string;
  } | null;

  // Deferred
  predictions: Array<{
    horizon: number;
    horizonLabel: string;
    predictedPrice: number;
    confidence: number;
    expectedGainPercent: number;
    timestamp: string;
  }>;

  indicators: {
    rsi: number | null;
    macd: {
      line: number | null;
      signal: number | null;
      histogram: number | null;
    };
    ema: number | null;
    sma: number | null;
    atr: number | null;
    bollingerBands: {
      upper: number | null;
      middle: number | null;
      lower: number | null;
    };
    updatedAt: string;
  } | null;

  priceHistory: Array<{
    timestamp: number;
    close: number;
    high: number;
    low: number;
    open: number;
    volume: number;
  }>;

  predictionHistory: Array<{
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    timestamp: string;
  }>;

  // Chart period selection
  chartPeriod: 30 | 90 | 365;
}
```

### Controller Implementation

```php
public function show(string $locale, Asset $asset, Request $request)
{
    $asset->load(['market', 'sector', 'country', 'latestPrice']);
    $chartPeriod = (int) $request->input('period', 30);

    return Inertia::render('assets/Show', [
        // Eager - Asset header
        'asset' => [
            'id' => $asset->id,
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'type' => $asset->type,
            'currency' => $asset->currency,
            'market' => [
                'id' => $asset->market->id,
                'code' => $asset->market->code,
                'name' => $asset->market->name,
            ],
            'sector' => $asset->sector ? [
                'id' => $asset->sector->id,
                'name' => $asset->sector->name,
            ] : null,
            'country' => $asset->country ? [
                'id' => $asset->country->id,
                'name' => $asset->country->name,
                'code' => $asset->country->code,
            ] : null,
        ],

        // Eager - Latest price
        'price' => $asset->latestPrice ? [
            'last' => (float) $asset->latestPrice->last,
            'changePercent' => $asset->latestPrice->pcp,
            'high' => (float) $asset->latestPrice->high,
            'low' => (float) $asset->latestPrice->low,
            'previousClose' => (float) $asset->latestPrice->last_close,
            'volume' => $asset->latestPrice->turnover,
            'updatedAt' => Carbon::createFromTimestamp($asset->latestPrice->timestamp / 1000)->toISOString(),
        ] : null,

        'chartPeriod' => $chartPeriod,

        // Deferred
        'predictions' => Inertia::lazy(fn() => $this->getAssetPredictions($asset)),
        'indicators' => Inertia::lazy(fn() => $this->getAssetIndicators($asset)),
        'priceHistory' => Inertia::lazy(fn() => $this->getPriceHistory($asset, $chartPeriod)),
        'predictionHistory' => Inertia::lazy(fn() => $this->getPredictionHistory($asset)),
    ]);
}

private function getAssetPredictions(Asset $asset): array
{
    $currentPrice = $asset->latestPrice?->last ?? 0;

    return PredictedAssetPrice::where('pid', $asset->inv_id)
        ->whereIn('horizon', [1440, 10080, 43200, 129600])
        ->orderByDesc('timestamp')
        ->get()
        ->groupBy('horizon')
        ->map(function ($group) use ($currentPrice) {
            $p = $group->first();
            $expectedGain = $currentPrice > 0
                ? (($p->price_prediction - $currentPrice) / $currentPrice) * 100
                : 0;

            return [
                'horizon' => $p->horizon,
                'horizonLabel' => $this->horizonLabel($p->horizon),
                'predictedPrice' => $p->price_prediction,
                'confidence' => $p->confidence,
                'expectedGainPercent' => round($expectedGain, 2),
                'timestamp' => $p->created_at?->toISOString(),
            ];
        })
        ->values()
        ->toArray();
}

private function getAssetIndicators(Asset $asset): ?array
{
    $indicator = InstantIndicator::where('pid', $asset->inv_id)
        ->orderByDesc('timestamp')
        ->first();

    if (!$indicator) {
        return null;
    }

    return [
        'rsi' => $indicator->rsi,
        'macd' => [
            'line' => $indicator->macd_line,
            'signal' => $indicator->macd_signal,
            'histogram' => $indicator->macd_histogram,
        ],
        'ema' => $indicator->ema,
        'sma' => $indicator->sma,
        'atr' => $indicator->atr,
        'bollingerBands' => [
            'upper' => $indicator->bb_upper,
            'middle' => $indicator->bb_middle,
            'lower' => $indicator->bb_lower,
        ],
        'updatedAt' => Carbon::createFromTimestamp($indicator->timestamp / 1000)->toISOString(),
    ];
}

private function getPriceHistory(Asset $asset, int $days): array
{
    $startTimestamp = now()->subDays($days)->timestamp * 1000;

    return AssetPrice::where('pid', $asset->inv_id)
        ->where('timestamp', '>=', $startTimestamp)
        ->orderBy('timestamp')
        ->get()
        ->map(fn($price) => [
            'timestamp' => $price->timestamp,
            'close' => (float) $price->last,
            'high' => (float) $price->high,
            'low' => (float) $price->low,
            'open' => (float) $price->last_close,
            'volume' => (float) ($price->turnover_numeric ?? 0),
        ])
        ->toArray();
}

private function getPredictionHistory(Asset $asset): array
{
    return PredictedAssetPrice::where('pid', $asset->inv_id)
        ->orderByDesc('timestamp')
        ->limit(10)
        ->get()
        ->map(fn($p) => [
            'predictedPrice' => $p->price_prediction,
            'confidence' => $p->confidence,
            'horizon' => $p->horizon,
            'horizonLabel' => $this->horizonLabel($p->horizon),
            'timestamp' => $p->created_at?->toISOString(),
        ])
        ->toArray();
}
```

---

## Summary Table

| Page | Route | Eager Data | Deferred Data | Pagination | Queries |
|------|-------|------------|---------------|------------|---------|
| Home | `/{locale}` | Stats, Markets, Sectors | Featured (5), Movers (5), Recent (5) | - | ~6 |
| Markets | `/{locale}/markets` | All markets with counts | - | - | 2 |
| Market Detail | `/{locale}/markets/{market}` | Market header | Assets list | 10/page | 3 |
| Sectors | `/{locale}/sectors` | All sectors with counts | - | - | 3 |
| Sector Detail | `/{locale}/sectors/{sector}` | Sector header, Markets | Assets list (filterable) | 10/page | 4 |
| Predictions | `/{locale}/predictions` | Filter options | Predictions list | 10/page | 2 |
| Search | `/{locale}/search` | Query | Assets results | 10/page | 2 |
| Asset Detail | `/{locale}/assets/{asset}` | Asset, Price | Predictions, Indicators, Charts, History | - | 5 |

---

## Required Model Updates

### Asset Model - Add Missing Relationships

```php
// app/Models/Asset.php

public function latestPrediction(): HasOne
{
    return $this->hasOne(PredictedAssetPrice::class, 'pid', 'inv_id')
        ->ofMany('timestamp', 'max');
}

public function predictions(): HasMany
{
    return $this->hasMany(PredictedAssetPrice::class, 'pid', 'inv_id');
}

public function latestIndicator(): HasOne
{
    return $this->hasOne(InstantIndicator::class, 'pid', 'inv_id')
        ->ofMany('timestamp', 'max');
}

public function priceHistory(): HasMany
{
    return $this->hasMany(AssetPrice::class, 'pid', 'inv_id');
}

// Accessor for description
public function getDescriptionAttribute(): ?string
{
    return app()->getLocale() === 'ar'
        ? $this->description_ar
        : $this->description_en;
}
```

### AssetPrice Model - Add Relationship

```php
// app/Models/AssetPrice.php

protected $fillable = [
    'pid', 'last_dir', 'last_numeric', 'last', 'bid', 'ask',
    'high', 'low', 'last_close', 'pc', 'pcp', 'pc_col',
    'time', 'timestamp', 'turnover', 'turnover_numeric',
];

public function asset(): BelongsTo
{
    return $this->belongsTo(Asset::class, 'pid', 'inv_id');
}
```

---

## Shared TypeScript Types

Create a shared types file:

```typescript
// resources/js/types/predictions.d.ts

export interface PaginationMeta {
  currentPage: number;
  lastPage: number;
  perPage: number;
  total: number;
}

export interface MarketPreview {
  id: string;
  code: string;
  name: string;
}

export interface SectorPreview {
  id: string;
  name: string;
}

export interface CountryPreview {
  id: string;
  name: string;
  code: string;
}

export interface AssetPreview {
  id: string;
  symbol: string;
  name: string;
  market?: MarketPreview;
  sector?: SectorPreview | null;
}

export interface PriceData {
  last: number;
  pcp: string;
  high?: number;
  low?: number;
  previousClose?: number;
  volume?: string;
}

export interface PredictionData {
  predictedPrice: number;
  confidence: number;
  horizon: number;
  horizonLabel: string;
  expectedGainPercent?: number;
  timestamp?: string;
}

export interface HorizonOption {
  value: number;
  label: string;
}

export const HORIZONS: HorizonOption[] = [
  { value: 1440, label: '1D' },
  { value: 10080, label: '1W' },
  { value: 43200, label: '1M' },
  { value: 129600, label: '3M' },
];
```

---

## Next Steps

### Phase 1: Infrastructure
1. Create performance indexes migration (includes assets market_id/sector_id indexes)
2. Create FTS migration for assets (using 'simple' config for Arabic support)
3. Create `App\Support\Horizon` helper class
4. Create `App\Support\PaginationHelper` helper class
5. Create `App\Services\PredictionStatsService` with cache

### Phase 2: Models
6. Update Asset model with new relationships (latestPrediction, predictions, latestIndicator, priceHistory)
7. Update AssetPrice model with asset relationship
8. Add shared TypeScript types to `resources/js/types/predictions.d.ts`

### Phase 3: Controllers
9. Create HomeController
10. Create MarketController (index, show)
11. Create SectorController (index, show)
12. Create PredictionController
13. Create SearchController
14. Create AssetController
15. Update routes/web.php with controller bindings

### Phase 4: Frontend
16. Update Vue pages to consume new props
17. Add loading skeletons for deferred props
18. Test bilingual support (AR/EN)

---

## Future Performance Improvements (Post-MVP)

These optimizations can be implemented after MVP if needed:

| Improvement | Description | When to Apply |
|-------------|-------------|---------------|
| **Latest prices materialized view** | Create `latest_asset_prices` view updated by price ingestion | If TopMovers query becomes slow |
| **Expected gain sort in SQL** | Join predictions with latest prices in SQL, sort before pagination | If users need accurate cross-page sorting |
| **Covering indexes** | Add `INCLUDE` clauses to indexes for heavy queries | If heap access becomes bottleneck |
| **Redis caching** | Replace file cache with Redis for PredictionStatsService | If high traffic on hot pages |
| **Query result caching** | Cache entire query results for filter combinations | If same filters are queried repeatedly |

---

## MVP Deferred Features

The following features are **not included in MVP** and will be implemented in a later phase:

- **Recommendations** (Buy/Sell/Hold) - InstantRecommendation model exists but not exposed in UI
- **Search for Markets and Sectors** - Only Assets search in MVP
- **Multi-select filters** - Single select for simplicity
- **Realtime updates** - Supabase JS integration
- **Expected gain sorting** - Requires SQL join implementation
