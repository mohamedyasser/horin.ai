# Vue Component Integration Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Update all Vue page components to consume real backend data via Inertia props instead of mock data, using the new TypeScript interfaces.

**Architecture:** Each Vue component currently uses hardcoded mock data. We'll replace mock data with Inertia props, add Deferred component for lazy-loaded props with loading skeletons, and update TypeScript interfaces to match the backend data structure.

**Tech Stack:** Vue 3, Inertia.js v2, TypeScript, Tailwind CSS

---

## Task 1: Update TypeScript Interfaces

**Files:**
- Modify: `resources/js/types/index.d.ts`
- Reference: `resources/js/types/predictions.ts`

**Step 1: Add new interfaces to match backend responses**

Add these interfaces to `resources/js/types/index.d.ts` after line 176:

```typescript
// Pagination types (matches PaginationHelper output)
export interface PaginationMeta {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
}

export interface PaginatedResponse<T> {
    data: T[];
    meta: PaginationMeta;
}

// Home page types
export interface HomeStats {
    totalMarkets: number;
    totalAssets: number;
    totalPredictions: number;
    totalSectors: number;
}

export interface CountryPreview {
    id: string;
    name: string;
    code: string;
}

export interface MarketPreview {
    id: string;
    name: string;
    code: string;
    country: CountryPreview;
    isOpen: boolean;
    assetCount: number;
    predictionCount: number;
}

export interface SectorPreview {
    id: string;
    name: string;
    assetCount: number;
    predictionCount: number;
}

export interface AssetPreview {
    id: string;
    symbol: string;
    name: string;
    market?: { code: string };
}

export interface FeaturedPrediction {
    id: string;
    asset: AssetPreview;
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    expectedGainPercent: number;
    timestamp: string | null;
}

export interface TopMover {
    id: string;
    symbol: string;
    name: string;
    market: { code: string };
    currentPrice: number;
    priceChangePercent: number;
}

export interface RecentPrediction {
    id: string;
    asset: AssetPreview;
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    timestamp: string | null;
}

// Market detail types
export interface MarketDetail {
    id: string;
    name: string;
    code: string;
    country: CountryPreview | null;
    isOpen: boolean;
    openAt: string | null;
    closeAt: string | null;
    tvLink: string | null;
    assetCount: number;
    predictionCount: number;
}

export interface AssetListItem {
    id: string;
    symbol: string;
    name: string;
    sector?: { id: string; name: string } | null;
    market?: { id: string; code: string; name: string } | null;
    latestPrice?: { last: number; pcp: string } | null;
    latestPrediction?: {
        predictedPrice: number;
        confidence: number;
        horizon: number;
        horizonLabel: string;
    } | null;
}

// Sector detail types
export interface MarketsBreakdown {
    marketId: string;
    marketCode: string;
    marketName: string;
    count: number;
}

export interface SectorDetail {
    id: string;
    name: string;
    description: string | null;
    assetCount: number;
    predictionCount: number;
    marketsBreakdown: MarketsBreakdown[];
}

// Predictions page types
export interface HorizonOption {
    value: number;
    label: string;
}

export interface PredictionFilterOptions {
    markets: { id: string; code: string; name: string }[];
    sectors: { id: string; name: string }[];
    horizons: HorizonOption[];
}

export interface PredictionFiltersState {
    marketId: string | null;
    sectorId: string | null;
    horizon: number | null;
    minConfidence: number;
}

export interface PredictionSortState {
    field: 'confidence' | 'timestamp';
    direction: 'asc' | 'desc';
}

export interface PredictionListItem {
    id: string;
    asset: {
        id: string;
        symbol: string;
        name: string;
        market: { id: string; code: string; name: string } | null;
        sector: { id: string; name: string } | null;
        currentPrice: number | null;
    };
    predictedPrice: number;
    expectedGainPercent: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    timestamp: string;
}

// Search page types
export interface SearchResult {
    id: string;
    symbol: string;
    name: string;
    market: { id: string; code: string; name: string } | null;
    sector: { id: string; name: string } | null;
    latestPrice: { last: number; pcp: string } | null;
}

// Asset detail types
export interface AssetDetailData {
    id: string;
    symbol: string;
    name: string;
    type: string;
    currency: string;
    market: { id: string; code: string; name: string } | null;
    sector: { id: string; name: string } | null;
    country: CountryPreview | null;
}

export interface AssetPriceData {
    last: number;
    changePercent: string;
    high: number;
    low: number;
    previousClose: number;
    volume: string;
    updatedAt: string;
}

export interface AssetPredictionData {
    horizon: number;
    horizonLabel: string;
    predictedPrice: number;
    confidence: number;
    expectedGainPercent: number;
    timestamp: string | null;
}

export interface AssetIndicatorsData {
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
}

export interface PriceHistoryPoint {
    timestamp: number;
    close: number;
    high: number;
    low: number;
    open: number;
    volume: number;
}

export interface PredictionHistoryItem {
    predictedPrice: number;
    confidence: number;
    horizon: number;
    horizonLabel: string;
    timestamp: string | null;
}
```

**Step 2: Commit**

```bash
git add resources/js/types/index.d.ts
git commit -m "feat: add TypeScript interfaces for backend integration"
```

---

## Task 2: Update Welcome.vue (Home Page)

**Files:**
- Modify: `resources/js/pages/Welcome.vue`

**Step 1: Update script section**

Replace the script section with proper Inertia props handling:

```typescript
<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
// ... keep existing imports

import type {
    HomeStats,
    MarketPreview,
    SectorPreview,
    FeaturedPrediction,
    TopMover,
    RecentPrediction,
} from '@/types';

const { t, locale } = useI18n();

interface Props {
    stats: HomeStats;
    markets: MarketPreview[];
    sectors: SectorPreview[];
    featuredPredictions?: FeaturedPrediction[];
    topMovers?: TopMover[];
    recentPredictions?: RecentPrediction[];
}

const props = defineProps<Props>();

// Remove all mock data declarations (mockMarkets, mockSectors, mockPredictions, etc.)

// Use props directly
const markets = computed(() => props.markets);
const sectors = computed(() => props.sectors);
const featuredPredictions = computed(() => props.featuredPredictions ?? []);
const topMovers = computed(() => props.topMovers ?? []);
const recentPredictions = computed(() => props.recentPredictions ?? []);
</script>
```

**Step 2: Update template for deferred props**

Wrap lazy-loaded sections with Deferred component:

```vue
<!-- Featured Predictions Section -->
<Deferred data="featuredPredictions">
    <template #fallback>
        <div class="space-y-4">
            <div v-for="i in 5" :key="i" class="animate-pulse">
                <div class="h-16 bg-muted rounded-lg"></div>
            </div>
        </div>
    </template>
    <!-- Existing prediction cards using featuredPredictions -->
</Deferred>

<!-- Top Movers Section -->
<Deferred data="topMovers">
    <template #fallback>
        <div class="space-y-3">
            <div v-for="i in 5" :key="i" class="animate-pulse">
                <div class="h-12 bg-muted rounded"></div>
            </div>
        </div>
    </template>
    <!-- Existing top movers list using topMovers -->
</Deferred>

<!-- Recent Predictions Section -->
<Deferred data="recentPredictions">
    <template #fallback>
        <div class="space-y-3">
            <div v-for="i in 5" :key="i" class="animate-pulse">
                <div class="h-12 bg-muted rounded"></div>
            </div>
        </div>
    </template>
    <!-- Existing recent predictions list using recentPredictions -->
</Deferred>
```

**Step 3: Update data access patterns**

Change mock data references to use props:

- `mockMarkets` → `markets` (computed from `props.markets`)
- `mockPredictions` → `featuredPredictions` (computed from `props.featuredPredictions`)
- Market name: use `market.name` directly (backend returns localized)
- Sector name: use `sector.name` directly (backend returns localized)

**Step 4: Commit**

```bash
git add resources/js/pages/Welcome.vue
git commit -m "feat: integrate Welcome.vue with backend props"
```

---

## Task 3: Update Markets.vue (Markets List Page)

**Files:**
- Modify: `resources/js/pages/Markets.vue`

**Step 1: Update props interface**

```typescript
interface Props {
    markets: MarketPreview[];
}

const props = defineProps<Props>();

// Remove mock data, use props.markets directly
const markets = computed(() => props.markets);
```

**Step 2: Update template**

- Remove canLogin/canRegister props (not needed for this page)
- Use `market.name` directly (already localized from backend)
- Use `market.country.name` for country display
- Use `market.isOpen` for status badge
- Use `market.assetCount` and `market.predictionCount`

**Step 3: Commit**

```bash
git add resources/js/pages/Markets.vue
git commit -m "feat: integrate Markets.vue with backend props"
```

---

## Task 4: Update markets/Show.vue (Market Detail Page)

**Files:**
- Modify: `resources/js/pages/markets/Show.vue`

**Step 1: Update props interface**

```typescript
import type { MarketDetail, AssetListItem, PaginationMeta } from '@/types';

interface Props {
    market: MarketDetail;
    assets?: {
        data: AssetListItem[];
        meta: PaginationMeta;
    };
}

const props = defineProps<Props>();
```

**Step 2: Update template with Deferred**

```vue
<Deferred data="assets">
    <template #fallback>
        <div class="space-y-4">
            <div v-for="i in 10" :key="i" class="animate-pulse">
                <div class="h-16 bg-muted rounded-lg"></div>
            </div>
        </div>
    </template>
    <!-- Assets table -->
    <table v-if="props.assets?.data.length">
        <!-- Table rows using props.assets.data -->
    </table>
    <!-- Pagination using props.assets.meta -->
</Deferred>
```

**Step 3: Commit**

```bash
git add resources/js/pages/markets/Show.vue
git commit -m "feat: integrate market detail with backend props"
```

---

## Task 5: Update Sectors.vue (Sectors List Page)

**Files:**
- Modify: `resources/js/pages/Sectors.vue`

**Step 1: Update props interface**

```typescript
interface SectorListItem {
    id: string;
    name: string;
    description: string | null;
    assetCount: number;
    predictionCount: number;
    marketsBreakdown: MarketsBreakdown[];
}

interface Props {
    sectors: SectorListItem[];
}

const props = defineProps<Props>();
```

**Step 2: Remove mock data and use props**

- Remove `mockSectors` array
- Use `props.sectors` directly
- Update template to use `sector.name` (already localized)
- Update markets breakdown display

**Step 3: Commit**

```bash
git add resources/js/pages/Sectors.vue
git commit -m "feat: integrate Sectors.vue with backend props"
```

---

## Task 6: Update sectors/Show.vue (Sector Detail Page)

**Files:**
- Modify: `resources/js/pages/sectors/Show.vue`

**Step 1: Update props interface**

```typescript
interface Props {
    sector: SectorDetail;
    markets: { id: string; code: string; name: string }[];
    filters: { marketId: string | null };
    assets?: {
        data: AssetListItem[];
        meta: PaginationMeta;
    };
}

const props = defineProps<Props>();
```

**Step 2: Update template with Deferred and filtering**

```vue
<Deferred data="assets">
    <template #fallback>
        <!-- Loading skeleton -->
    </template>
    <!-- Assets table with market filter -->
</Deferred>
```

**Step 3: Add filter handling with router.reload**

```typescript
import { router } from '@inertiajs/vue3';

const selectedMarket = ref(props.filters.marketId);

watch(selectedMarket, (newVal) => {
    router.reload({
        data: { market_id: newVal },
        only: ['assets'],
    });
});
```

**Step 4: Commit**

```bash
git add resources/js/pages/sectors/Show.vue
git commit -m "feat: integrate sector detail with backend props"
```

---

## Task 7: Update Predictions.vue (Predictions List Page)

**Files:**
- Modify: `resources/js/pages/Predictions.vue`

**Step 1: Update props interface**

```typescript
interface Props {
    filterOptions: PredictionFilterOptions;
    filters: PredictionFiltersState;
    sort: PredictionSortState;
    predictions?: {
        data: PredictionListItem[];
        meta: PaginationMeta;
    };
}

const props = defineProps<Props>();
```

**Step 2: Remove mock data and use props**

- Remove `mockPredictions` array
- Use filter options from `props.filterOptions`
- Initialize filter state from `props.filters`
- Initialize sort state from `props.sort`

**Step 3: Add filter/sort handling with router.reload**

```typescript
const filters = reactive({
    marketId: props.filters.marketId,
    sectorId: props.filters.sectorId,
    horizon: props.filters.horizon,
    minConfidence: props.filters.minConfidence,
});

const sort = reactive({
    field: props.sort.field,
    direction: props.sort.direction,
});

const applyFilters = () => {
    router.reload({
        data: {
            market_id: filters.marketId,
            sector_id: filters.sectorId,
            horizon: filters.horizon,
            min_confidence: filters.minConfidence,
            sort: sort.field,
            direction: sort.direction,
        },
        only: ['predictions'],
    });
};
```

**Step 4: Update template with Deferred**

```vue
<Deferred data="predictions">
    <template #fallback>
        <!-- Table skeleton -->
    </template>
    <!-- Predictions table -->
</Deferred>
```

**Step 5: Commit**

```bash
git add resources/js/pages/Predictions.vue
git commit -m "feat: integrate Predictions.vue with backend props"
```

---

## Task 8: Update Search.vue (Search Page)

**Files:**
- Modify: `resources/js/pages/Search.vue`

**Step 1: Update props interface**

```typescript
interface Props {
    query: string;
    results?: {
        data: SearchResult[];
        meta: PaginationMeta;
    };
    totalCount?: number;
}

const props = defineProps<Props>();
```

**Step 2: Update search with URL sync**

```typescript
const searchQuery = ref(props.query);

const performSearch = useDebounceFn(() => {
    router.visit(route('search', { q: searchQuery.value }), {
        preserveState: true,
        only: ['results', 'totalCount'],
    });
}, 300);

watch(searchQuery, () => {
    if (searchQuery.value) {
        performSearch();
    }
});
```

**Step 3: Update template with Deferred**

```vue
<Deferred data="results">
    <template #fallback>
        <div class="space-y-4">
            <div v-for="i in 10" :key="i" class="animate-pulse">
                <div class="h-16 bg-muted rounded-lg"></div>
            </div>
        </div>
    </template>
    <!-- Search results table -->
</Deferred>
```

**Step 4: Commit**

```bash
git add resources/js/pages/Search.vue
git commit -m "feat: integrate Search.vue with backend props"
```

---

## Task 9: Update assets/Show.vue (Asset Detail Page)

**Files:**
- Modify: `resources/js/pages/assets/Show.vue`

**Step 1: Update props interface**

```typescript
interface Props {
    asset: AssetDetailData;
    price: AssetPriceData | null;
    chartPeriod: number;
    predictions?: AssetPredictionData[];
    indicators?: AssetIndicatorsData | null;
    priceHistory?: PriceHistoryPoint[];
    predictionHistory?: PredictionHistoryItem[];
}

const props = defineProps<Props>();
```

**Step 2: Remove mock data and use props**

- Remove large mock asset object
- Use `props.asset`, `props.price`, etc. directly
- Update indicator display to handle new structure (nested macd, bollingerBands)

**Step 3: Update template with Deferred for lazy props**

```vue
<!-- Predictions -->
<Deferred data="predictions">
    <template #fallback>
        <div class="space-y-4">
            <div v-for="i in 4" :key="i" class="animate-pulse">
                <div class="h-24 bg-muted rounded-lg"></div>
            </div>
        </div>
    </template>
    <!-- Predictions cards -->
</Deferred>

<!-- Indicators -->
<Deferred data="indicators">
    <template #fallback>
        <div class="space-y-3">
            <div v-for="i in 5" :key="i" class="animate-pulse">
                <div class="h-10 bg-muted rounded"></div>
            </div>
        </div>
    </template>
    <!-- Indicators display -->
</Deferred>

<!-- Price History Chart -->
<Deferred data="priceHistory">
    <template #fallback>
        <div class="h-64 bg-muted rounded-lg animate-pulse"></div>
    </template>
    <!-- Chart component -->
</Deferred>
```

**Step 4: Add chart period handling**

```typescript
const chartPeriod = ref(props.chartPeriod);

const changeChartPeriod = (period: number) => {
    chartPeriod.value = period;
    router.reload({
        data: { period },
        only: ['priceHistory'],
    });
};
```

**Step 5: Commit**

```bash
git add resources/js/pages/assets/Show.vue
git commit -m "feat: integrate asset detail with backend props"
```

---

## Task 10: Run Build and Verify

**Step 1: Run TypeScript check**

```bash
npm run build
```

Expected: Build succeeds without TypeScript errors.

**Step 2: Fix any type errors**

If errors occur, fix them by:
- Ensuring prop types match backend response structure
- Adding null checks where needed
- Updating computed properties

**Step 3: Commit any fixes**

```bash
git add -A
git commit -m "fix: resolve TypeScript errors in Vue components"
```

---

## Task 11: Test Each Page Manually

**Step 1: Start dev server**

```bash
npm run dev
```

**Step 2: Test each page**

1. Home page (`/ar` or `/en`) - Verify stats, markets, sectors load; deferred sections show loading then data
2. Markets page (`/ar/markets`) - Verify markets list displays
3. Market detail (`/ar/markets/{id}`) - Verify market info and assets load
4. Sectors page (`/ar/sectors`) - Verify sectors list displays
5. Sector detail (`/ar/sectors/{id}`) - Verify sector info and assets load
6. Predictions page (`/ar/predictions`) - Verify filters and predictions load
7. Search page (`/ar/search`) - Verify search works
8. Asset detail (`/ar/assets/{id}`) - Verify all sections load

**Step 3: Document any issues**

Create issues in todo list if any page fails to render correctly.

---

## Task 12: Final Cleanup

**Step 1: Remove unused mock data imports**

Search for and remove any remaining mock data that is no longer used.

**Step 2: Run Pint and ESLint**

```bash
vendor/bin/pint --dirty
npm run lint -- --fix
```

**Step 3: Final commit**

```bash
git add -A
git commit -m "chore: cleanup unused mock data and lint fixes"
```

---

## Summary

| Task | Component | Key Changes |
|------|-----------|-------------|
| 1 | TypeScript | Add interfaces for all backend responses |
| 2 | Welcome.vue | Props + Deferred for lazy sections |
| 3 | Markets.vue | Props for markets list |
| 4 | markets/Show.vue | Props + Deferred for assets |
| 5 | Sectors.vue | Props for sectors list |
| 6 | sectors/Show.vue | Props + Deferred + filter handling |
| 7 | Predictions.vue | Props + Deferred + filter/sort |
| 8 | Search.vue | Props + debounced search |
| 9 | assets/Show.vue | Props + Deferred for all lazy sections |
| 10 | Build | TypeScript validation |
| 11 | Testing | Manual verification |
| 12 | Cleanup | Remove mock data, lint |
