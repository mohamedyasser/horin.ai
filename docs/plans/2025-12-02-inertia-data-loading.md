# Inertia Data Loading Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement backend data loading for all 8 Inertia pages with eager/deferred loading, aggregated counts, and bilingual support.

**Architecture:** Controllers return Inertia responses with eager data (critical) and lazy props (deferred). PredictionStatsService provides cached aggregated counts. All entities use bilingual `name` accessors.

**Tech Stack:** Laravel 12, Inertia v2, PostgreSQL FTS, Vue 3, TypeScript

---

## Pre-Implementation Status

The following have already been created:
- `database/migrations/2025_12_02_203355_add_performance_indexes.php`
- `database/migrations/2025_12_02_203431_add_fts_to_assets_table.php`
- `app/Support/Horizon.php`

---

## Task 1: Create PaginationHelper

**Files:**
- Create: `app/Support/PaginationHelper.php`

**Step 1: Create the helper class**

```php
<?php

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

**Step 2: Format with Pint**

Run: `vendor/bin/pint app/Support/PaginationHelper.php`

**Step 3: Commit**

```bash
git add app/Support/PaginationHelper.php
git commit -m "feat: add PaginationHelper for consistent pagination meta"
```

---

## Task 2: Create PredictionStatsService

**Files:**
- Create: `app/Services/PredictionStatsService.php`

**Step 1: Create Services directory and service class**

```php
<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PredictionStatsService
{
    private const CACHE_TTL = 60;

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

    public static function countForMarket(string $marketId): int
    {
        return self::countsByMarket()->get($marketId, 0);
    }

    public static function countForSector(string $sectorId): int
    {
        return self::countsBySector()->get($sectorId, 0);
    }

    public static function clearCache(): void
    {
        Cache::forget('prediction_counts_by_market');
        Cache::forget('prediction_counts_by_sector');
    }
}
```

**Step 2: Format with Pint**

Run: `vendor/bin/pint app/Services/PredictionStatsService.php`

**Step 3: Commit**

```bash
git add app/Services/PredictionStatsService.php
git commit -m "feat: add PredictionStatsService with cached aggregated counts"
```

---

## Task 3: Update Asset Model with New Relationships

**Files:**
- Modify: `app/Models/Asset.php`

**Step 1: Add missing relationships**

Add after the existing `latestPrice()` method (around line 127):

```php
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

public function getDescriptionAttribute(): ?string
{
    return app()->getLocale() === 'ar'
        ? $this->description_ar
        : $this->description_en;
}
```

**Step 2: Add 'description' to $appends**

Change line 44 from:
```php
protected $appends = ['name'];
```
To:
```php
protected $appends = ['name', 'description'];
```

**Step 3: Format with Pint**

Run: `vendor/bin/pint app/Models/Asset.php`

**Step 4: Commit**

```bash
git add app/Models/Asset.php
git commit -m "feat: add prediction and indicator relationships to Asset model"
```

---

## Task 4: Update AssetPrice Model

**Files:**
- Modify: `app/Models/AssetPrice.php`

**Step 1: Read current file to find what to modify**

Check if `asset()` relationship exists. If not, add:

```php
public function asset(): BelongsTo
{
    return $this->belongsTo(Asset::class, 'pid', 'inv_id');
}
```

**Step 2: Add BelongsTo import if missing**

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

**Step 3: Format with Pint**

Run: `vendor/bin/pint app/Models/AssetPrice.php`

**Step 4: Commit**

```bash
git add app/Models/AssetPrice.php
git commit -m "feat: add asset relationship to AssetPrice model"
```

---

## Task 5: Add Shared TypeScript Types

**Files:**
- Create: `resources/js/types/predictions.ts`

**Step 1: Create the types file**

```typescript
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

**Step 2: Commit**

```bash
git add resources/js/types/predictions.ts
git commit -m "feat: add shared TypeScript types for predictions"
```

---

## Task 6: Create HomeController

**Files:**
- Create: `app/Http/Controllers/HomeController.php`

**Step 1: Generate controller**

Run: `php artisan make:controller HomeController --invokable --no-interaction`

**Step 2: Implement the controller**

Replace the generated file content with the full implementation from the plan document (see `tasks/InertiaDataLoadingPlan.md` Page 1 section).

Key methods:
- `__invoke(Request $request)` - returns Inertia response with eager stats/markets/sectors and lazy featured/topMovers/recent
- `getFeaturedPredictions(Request $request): array`
- `getTopMovers(): array`
- `getRecentPredictions(): array`
- `formatPrediction($prediction): array`

**Step 3: Add required imports**

```php
use App\Models\Asset;
use App\Models\Market;
use App\Models\PredictedAssetPrice;
use App\Models\Sector;
use App\Services\PredictionStatsService;
use App\Support\Horizon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
```

**Step 4: Format with Pint**

Run: `vendor/bin/pint app/Http/Controllers/HomeController.php`

**Step 5: Commit**

```bash
git add app/Http/Controllers/HomeController.php
git commit -m "feat: add HomeController with eager and deferred data loading"
```

---

## Task 7: Create MarketController

**Files:**
- Create: `app/Http/Controllers/MarketController.php`

**Step 1: Generate controller**

Run: `php artisan make:controller MarketController --no-interaction`

**Step 2: Implement index() and show() methods**

See `tasks/InertiaDataLoadingPlan.md` Pages 2-3 sections for full implementation.

Key methods:
- `index()` - returns all markets with prediction counts
- `show(string $locale, Market $market, Request $request)` - returns market header + lazy assets
- `getMarketAssets(Market $market, Request $request): array`

**Step 3: Format with Pint**

Run: `vendor/bin/pint app/Http/Controllers/MarketController.php`

**Step 4: Commit**

```bash
git add app/Http/Controllers/MarketController.php
git commit -m "feat: add MarketController with index and show actions"
```

---

## Task 8: Create SectorController

**Files:**
- Create: `app/Http/Controllers/SectorController.php`

**Step 1: Generate controller**

Run: `php artisan make:controller SectorController --no-interaction`

**Step 2: Implement index() and show() methods**

See `tasks/InertiaDataLoadingPlan.md` Pages 4-5 sections for full implementation.

Key methods:
- `index()` - returns all sectors with prediction counts and markets breakdown
- `show(string $locale, Sector $sector, Request $request)` - returns sector header + lazy assets with market filter
- `getSectorAssets(Sector $sector, Request $request): array`

**Step 3: Format with Pint**

Run: `vendor/bin/pint app/Http/Controllers/SectorController.php`

**Step 4: Commit**

```bash
git add app/Http/Controllers/SectorController.php
git commit -m "feat: add SectorController with index and show actions"
```

---

## Task 9: Create PredictionController

**Files:**
- Create: `app/Http/Controllers/PredictionController.php`

**Step 1: Generate controller**

Run: `php artisan make:controller PredictionController --no-interaction`

**Step 2: Implement index() method with filters**

See `tasks/InertiaDataLoadingPlan.md` Page 6 section for full implementation.

Key methods:
- `index(Request $request)` - returns filter options + lazy predictions with pagination
- `getPredictions(array $filters, array $sort): array`

**Step 3: Format with Pint**

Run: `vendor/bin/pint app/Http/Controllers/PredictionController.php`

**Step 4: Commit**

```bash
git add app/Http/Controllers/PredictionController.php
git commit -m "feat: add PredictionController with filtering and sorting"
```

---

## Task 10: Create SearchController

**Files:**
- Create: `app/Http/Controllers/SearchController.php`

**Step 1: Generate controller**

Run: `php artisan make:controller SearchController --no-interaction`

**Step 2: Implement index() method with FTS**

See `tasks/InertiaDataLoadingPlan.md` Page 7 section for full implementation.

Key methods:
- `index(Request $request)` - returns query + lazy results using PostgreSQL FTS
- `searchAssets(string $query, Request $request): array`
- `countAssets(string $query): int`

**Step 3: Format with Pint**

Run: `vendor/bin/pint app/Http/Controllers/SearchController.php`

**Step 4: Commit**

```bash
git add app/Http/Controllers/SearchController.php
git commit -m "feat: add SearchController with PostgreSQL FTS"
```

---

## Task 11: Create AssetController

**Files:**
- Create: `app/Http/Controllers/AssetController.php`

**Step 1: Generate controller**

Run: `php artisan make:controller AssetController --no-interaction`

**Step 2: Implement show() method**

See `tasks/InertiaDataLoadingPlan.md` Page 8 section for full implementation.

Key methods:
- `show(string $locale, Asset $asset, Request $request)` - returns asset header + price + lazy predictions/indicators/history
- `getAssetPredictions(Asset $asset): array`
- `getAssetIndicators(Asset $asset): ?array`
- `getPriceHistory(Asset $asset, int $days): array`
- `getPredictionHistory(Asset $asset): array`

**Step 3: Format with Pint**

Run: `vendor/bin/pint app/Http/Controllers/AssetController.php`

**Step 4: Commit**

```bash
git add app/Http/Controllers/AssetController.php
git commit -m "feat: add AssetController with comprehensive asset data"
```

---

## Task 12: Update Routes

**Files:**
- Modify: `routes/web.php`

**Step 1: Replace closure routes with controller bindings**

Update the localized routes group to use controllers:

```php
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\AssetController;

// Inside the locale group:
Route::get('/', HomeController::class)->name('home');
Route::get('markets', [MarketController::class, 'index'])->name('markets');
Route::get('markets/{market}', [MarketController::class, 'show'])->name('markets.show');
Route::get('sectors', [SectorController::class, 'index'])->name('sectors');
Route::get('sectors/{sector}', [SectorController::class, 'show'])->name('sectors.show');
Route::get('predictions', [PredictionController::class, 'index'])->name('predictions');
Route::get('search', [SearchController::class, 'index'])->name('search');
Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');
```

**Step 2: Format with Pint**

Run: `vendor/bin/pint routes/web.php`

**Step 3: Verify routes**

Run: `php artisan route:list --path=ar`
Expected: All routes show controller classes instead of Closure

**Step 4: Commit**

```bash
git add routes/web.php
git commit -m "feat: update routes to use controllers"
```

---

## Task 13: Run Migrations

**Step 1: Run migrations**

Run: `php artisan migrate`
Expected: Both migrations run successfully

**Step 2: Verify indexes exist**

Run: `php artisan tinker --execute="DB::select(\"SELECT indexname FROM pg_indexes WHERE tablename = 'assets'\")"`
Expected: Shows `assets_fts_idx`, `assets_market_id_idx`, `assets_sector_id_idx`

**Step 3: Commit migration status (if using migration tracking)**

No commit needed - migrations are already committed.

---

## Task 14: Test Home Page Data Loading

**Step 1: Visit home page**

Run: `php artisan serve`
Visit: `http://localhost:8000/ar`

Expected: Page loads without errors, Vue devtools shows:
- `stats` object with 4 counts
- `markets` array
- `sectors` array
- Deferred props loading after initial render

**Step 2: Check network tab**

Verify lazy props are loaded via partial reload (Inertia::lazy behavior)

---

## Task 15: Test Markets and Sectors Pages

**Step 1: Visit markets index**

Visit: `http://localhost:8000/ar/markets`
Expected: All markets displayed with asset counts and prediction counts

**Step 2: Visit market detail**

Click on a market card
Expected: Market header visible, assets list loads (deferred)

**Step 3: Visit sectors index**

Visit: `http://localhost:8000/ar/sectors`
Expected: All sectors with markets breakdown

**Step 4: Visit sector detail**

Click on a sector card
Expected: Sector header, market filter dropdown, assets list

---

## Task 16: Test Predictions Page

**Step 1: Visit predictions page**

Visit: `http://localhost:8000/ar/predictions`
Expected: Filter dropdowns populated, predictions table loads

**Step 2: Test filters**

Apply market filter, sector filter, horizon filter
Expected: Results update correctly

**Step 3: Test sorting**

Change sort to "Most recent"
Expected: Results reorder by timestamp

---

## Task 17: Test Search Page

**Step 1: Visit search page**

Visit: `http://localhost:8000/ar/search`
Expected: Empty state shown

**Step 2: Search for asset by symbol**

Type "COMI" and press Enter
Expected: Assets matching symbol prefix appear

**Step 3: Search in Arabic**

Type Arabic company name
Expected: Results include Arabic name matches

---

## Task 18: Test Asset Detail Page

**Step 1: Visit asset detail**

Visit: `http://localhost:8000/ar/assets/{asset-id}`
Expected: Asset header and price visible immediately

**Step 2: Verify deferred sections load**

Check that predictions, indicators, and charts load after initial page render

**Step 3: Test chart period selection**

Change period to 90 days
Expected: Chart updates with more data points

---

## Summary

| Task | Component | Estimated Steps |
|------|-----------|-----------------|
| 1 | PaginationHelper | 3 |
| 2 | PredictionStatsService | 3 |
| 3 | Asset Model | 4 |
| 4 | AssetPrice Model | 4 |
| 5 | TypeScript Types | 2 |
| 6 | HomeController | 5 |
| 7 | MarketController | 4 |
| 8 | SectorController | 4 |
| 9 | PredictionController | 4 |
| 10 | SearchController | 4 |
| 11 | AssetController | 4 |
| 12 | Routes | 4 |
| 13 | Migrations | 2 |
| 14-18 | Testing | 10 |

**Total: ~57 steps**

---

## Reference

Full controller implementations and TypeScript interfaces are in:
`tasks/InertiaDataLoadingPlan.md`
