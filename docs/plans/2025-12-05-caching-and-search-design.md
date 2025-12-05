# Caching & Search Optimization Design

**Date:** 2025-12-05
**Status:** Approved

## Overview

Comprehensive caching strategy using Redis for static data and Meilisearch for server-side search across all pages.

## Infrastructure

- **Server:** Dedicated, 32 cores, 64GB RAM
- **Cache:** Redis (configured, switching from file driver)
- **Search:** Meilisearch (configured, switching from collection driver)
- **Queue:** Redis (for async Scout indexing)

## Cache Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    Request Flow                          │
├─────────────────────────────────────────────────────────┤
│  Browser → Laravel → Redis Cache → PostgreSQL           │
│                         ↓                                │
│              Meilisearch (search only)                   │
└─────────────────────────────────────────────────────────┘
```

### What Gets Cached Where

| Data Type | Cache Location | TTL | Invalidation |
|-----------|---------------|-----|--------------|
| Countries, Markets, Sectors | Redis | 24 hours | Event-driven |
| Assets (list/lookups) | Redis | 24 hours | Event-driven |
| Prediction aggregates (counts) | Redis | 5 minutes | Time-based only |
| Asset search | Meilisearch | Real-time | Scout observers |
| Prices & Predictions | Materialized views | 1 minute | pg_cron |

### Event-Driven Invalidation

All static caches cleared together when any static model changes:

```
Country created/updated/deleted  ─┐
Market created/updated/deleted   ─┼─→ Clear ALL static caches
Sector created/updated/deleted   ─┤
Asset created/updated/deleted    ─┘
```

### Cache Keys

```
static:countries              → All countries
static:markets                → All markets
static:sectors                → All sectors
static:assets:all             → All assets
static:assets:market:{id}     → Assets by market
static:assets:sector:{id}     → Assets by sector
prediction_counts_by_market   → Aggregate counts (existing)
prediction_counts_by_sector   → Aggregate counts (existing)
```

## Static Cache Implementation

### StaticDataCacheService

```php
// app/Services/StaticDataCacheService.php

class StaticDataCacheService
{
    private const TTL = 86400; // 24 hours
    private const PREFIX = 'static:';

    public static function countries(): Collection
    public static function markets(): Collection
    public static function sectors(): Collection
    public static function assets(): Collection
    public static function assetsByMarket(string $marketId): Collection
    public static function assetsBySector(string $sectorId): Collection

    public static function clearAll(): void
}
```

### StaticDataObserver

```php
// app/Observers/StaticDataObserver.php

class StaticDataObserver
{
    public function created($model): void { $this->clearCache(); }
    public function updated($model): void { $this->clearCache(); }
    public function deleted($model): void { $this->clearCache(); }

    private function clearCache(): void
    {
        StaticDataCacheService::clearAll();
    }
}
```

### Registration

```php
// AppServiceProvider::boot()
Country::observe(StaticDataObserver::class);
Market::observe(StaticDataObserver::class);
Sector::observe(StaticDataObserver::class);
Asset::observe(StaticDataObserver::class);
```

## Search Implementation

### Environment Configuration

```env
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your-master-key
SCOUT_QUEUE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

### SearchService

```php
// app/Services/SearchService.php

class SearchService
{
    // Global search (Search.vue, Welcome.vue)
    public static function searchAssets(
        string $query,
        int $perPage = 15
    ): LengthAwarePaginator

    // Scoped search (markets/Show, sectors/Show)
    public static function searchAssetsInMarket(
        string $marketId,
        ?string $query,
        int $perPage = 10
    ): LengthAwarePaginator

    public static function searchAssetsInSector(
        string $sectorId,
        ?string $query,
        int $perPage = 10
    ): LengthAwarePaginator

    // Homepage search with predictions
    public static function searchAssetsWithPredictions(
        string $query,
        ?string $marketCode = null,
        int $limit = 20
    ): Collection
}
```

### Pages Using Server-Side Search

| Page | Search Type |
|------|-------------|
| Welcome.vue | Server-side (global search bar) |
| Search.vue | Server-side |
| markets/Show.vue | Server-side (scoped to market) |
| sectors/Show.vue | Server-side (scoped to sector) |
| Predictions.vue | Server-side |
| Markets.vue | Client-side (only ~7 items) |
| Sectors.vue | Client-side (only ~15 items) |

## Frontend Changes

### useServerSearch Composable

```typescript
// resources/js/composables/useServerSearch.ts

export function useServerSearch(delay = 300) {
    const searchQuery = ref('')

    const debouncedSearch = useDebounceFn((query: string) => {
        router.visit(window.location.pathname, {
            data: { search: query || undefined },
            preserveState: true,
            preserveScroll: true,
        })
    }, delay)

    watch(searchQuery, debouncedSearch)

    return { searchQuery }
}
```

### Controller Pattern

```php
// Controllers accept search param and return filtered results
$assets = SearchService::searchAssetsInMarket(
    $market->id,
    $request->input('search'),
    perPage: 10
);

return Inertia::render('markets/Show', [
    'assets' => $assets,
    'filters' => ['search' => $request->input('search')],
]);
```

## Redis Configuration

```conf
# /etc/redis/redis.conf
maxmemory 4gb
maxmemory-policy allkeys-lru
```

## Artisan Commands

```php
// app/Console/Commands/CacheStaticData.php

php artisan cache:static          # Warm all static caches
php artisan cache:static --clear  # Clear all static caches
```

## Deployment Steps

```bash
# 1. Ensure Redis is running
sudo systemctl start redis

# 2. Ensure Meilisearch is running
sudo systemctl start meilisearch

# 3. Update .env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your-master-key
SCOUT_QUEUE=true

# 4. Import assets to Meilisearch
php artisan scout:import "App\Models\Asset"

# 5. Warm static caches
php artisan cache:static

# 6. Start queue worker (via Supervisor)
php artisan queue:work redis --queue=scout,default
```

## Verification

```bash
# Check Redis is caching
redis-cli KEYS "static:*"

# Check Meilisearch index
curl http://127.0.0.1:7700/indexes/assets/stats

# Test search
php artisan tinker
>>> Asset::search('ARAMCO')->get()
```

## Files to Create

```
app/
├── Services/
│   ├── StaticDataCacheService.php
│   └── SearchService.php
├── Observers/
│   └── StaticDataObserver.php
└── Console/Commands/
    └── CacheStaticData.php

resources/js/
└── composables/
    └── useServerSearch.ts
```

## Files to Modify

```
.env
app/Providers/AppServiceProvider.php
app/Http/Controllers/MarketController.php
app/Http/Controllers/SectorController.php
app/Http/Controllers/PredictionController.php
app/Http/Controllers/HomeController.php
resources/js/pages/markets/Show.vue
resources/js/pages/sectors/Show.vue
resources/js/pages/Predictions.vue
resources/js/pages/Welcome.vue
```
