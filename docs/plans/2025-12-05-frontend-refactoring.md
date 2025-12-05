# Frontend Vue.js Refactoring Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Refactor the Vue.js frontend to eliminate code duplication, fix broken functionality, improve type safety, and enhance accessibility following SOLID and DRY principles.

**Architecture:** Create shared composables for repeated logic (formatters, stats), extract reusable components (tables, sidebars), fix broken pagination, and add proper error handling and accessibility support.

**Tech Stack:** Vue 3, TypeScript, Inertia.js, Tailwind CSS, reka-ui

---

## Phase 1: Critical Fixes (Issues #1, #2, #3)

### Task 1: Create Prediction Formatters Composable

**Files:**
- Create: `resources/js/composables/usePredictionFormatters.ts`

**Step 1: Create the composable file**

```typescript
// resources/js/composables/usePredictionFormatters.ts

/**
 * Shared formatting utilities for prediction data display.
 * Consolidates duplicated helper functions from 6+ page components.
 */
export function usePredictionFormatters() {
    /**
     * Format a gain percentage with sign prefix
     * @param gain - The gain percentage value
     * @returns Formatted string like "+5.2%" or "-3.1%"
     */
    const formatGain = (gain: number): string => {
        const sign = gain >= 0 ? '+' : '';
        return `${sign}${gain.toFixed(1)}%`;
    };

    /**
     * Get Tailwind color classes based on confidence level
     * @param confidence - Confidence percentage (0-100)
     * @returns Tailwind CSS classes for text color
     */
    const getConfidenceColor = (confidence: number): string => {
        if (confidence >= 85) return 'text-green-600 dark:text-green-400';
        if (confidence >= 70) return 'text-yellow-600 dark:text-yellow-400';
        return 'text-red-600 dark:text-red-400';
    };

    /**
     * Get Tailwind color classes for market open/closed status
     * @param isOpen - Whether the market is currently open
     * @returns Tailwind CSS classes for badge styling
     */
    const getStatusColor = (isOpen: boolean): string => {
        return isOpen
            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
            : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400';
    };

    /**
     * Calculate expected gain percentage from predicted and current prices
     * @param predictedPrice - The predicted future price
     * @param currentPrice - The current price (nullable)
     * @returns Gain percentage or 0 if current price is invalid
     */
    const calculateGainPercent = (predictedPrice: number, currentPrice: number | null): number => {
        if (!currentPrice || currentPrice === 0) return 0;
        return ((predictedPrice - currentPrice) / currentPrice) * 100;
    };

    /**
     * Get Tailwind color classes based on gain direction
     * @param gain - The gain percentage
     * @returns Tailwind CSS classes for positive (green) or negative (red)
     */
    const getGainColor = (gain: number): string => {
        return gain >= 0
            ? 'text-green-600 dark:text-green-400'
            : 'text-red-600 dark:text-red-400';
    };

    return {
        formatGain,
        getConfidenceColor,
        getStatusColor,
        calculateGainPercent,
        getGainColor,
    };
}
```

**Step 2: Verify the file compiles**

Run: `npm run build 2>&1 | head -20`
Expected: No TypeScript errors for the new file

**Step 3: Commit**

```bash
git add resources/js/composables/usePredictionFormatters.ts
git commit -m "feat: create usePredictionFormatters composable for shared formatting utilities"
```

---

### Task 2: Update Welcome.vue to Use Formatters Composable

**Files:**
- Modify: `resources/js/pages/Welcome.vue`

**Step 1: Replace local helper functions with composable**

Find and replace the script section. Remove these local functions:
```typescript
// REMOVE these lines (around lines 177-186):
const formatGain = (gain: number) => {
    const sign = gain >= 0 ? '+' : '';
    return `${sign}${gain.toFixed(1)}%`;
};

const getConfidenceColor = (confidence: number) => {
    if (confidence >= 85) return 'text-green-600 dark:text-green-400';
    if (confidence >= 70) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
};
```

Add import at the top of the script section:
```typescript
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
```

Add composable initialization after other composables:
```typescript
const { formatGain, getConfidenceColor, getGainColor } = usePredictionFormatters();
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/Welcome.vue
git commit -m "refactor(Welcome): use usePredictionFormatters composable"
```

---

### Task 3: Update Predictions.vue to Use Formatters Composable

**Files:**
- Modify: `resources/js/pages/Predictions.vue`

**Step 1: Replace local helper functions with composable**

Remove these local functions (around lines 76-85):
```typescript
// REMOVE:
const formatGain = (gain: number) => { ... };
const getConfidenceColor = (confidence: number) => { ... };
```

Add import:
```typescript
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
```

Add composable initialization:
```typescript
const { formatGain, getConfidenceColor } = usePredictionFormatters();
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/Predictions.vue
git commit -m "refactor(Predictions): use usePredictionFormatters composable"
```

---

### Task 4: Update markets/Show.vue to Use Formatters Composable

**Files:**
- Modify: `resources/js/pages/markets/Show.vue`

**Step 1: Replace local helper functions with composable**

Remove these local functions (around lines 78-98):
```typescript
// REMOVE:
const formatGain = (gain: number) => { ... };
const getConfidenceColor = (confidence: number) => { ... };
const getStatusColor = (isOpen: boolean) => { ... };
```

Add import:
```typescript
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
```

Add composable initialization:
```typescript
const { formatGain, getConfidenceColor, getStatusColor } = usePredictionFormatters();
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/markets/Show.vue
git commit -m "refactor(markets/Show): use usePredictionFormatters composable"
```

---

### Task 5: Update sectors/Show.vue to Use Formatters Composable

**Files:**
- Modify: `resources/js/pages/sectors/Show.vue`

**Step 1: Replace local helper functions with composable**

Remove these local functions (around lines 78-92):
```typescript
// REMOVE:
const formatGain = (gain: number) => { ... };
const getConfidenceColor = (confidence: number) => { ... };
```

Add import:
```typescript
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
```

Add composable initialization:
```typescript
const { formatGain, getConfidenceColor } = usePredictionFormatters();
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/sectors/Show.vue
git commit -m "refactor(sectors/Show): use usePredictionFormatters composable"
```

---

### Task 6: Update assets/Show.vue to Use Formatters Composable

**Files:**
- Modify: `resources/js/pages/assets/Show.vue`

**Step 1: Replace local helper functions with composable**

Remove these local functions (around lines 68-91):
```typescript
// REMOVE:
const formatGain = (gain: number) => { ... };
const getConfidenceColor = (confidence: number) => { ... };
const calculateGainPercent = (...) => { ... };
```

Add import:
```typescript
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
```

Add composable initialization:
```typescript
const { formatGain, getConfidenceColor, calculateGainPercent, getGainColor } = usePredictionFormatters();
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/assets/Show.vue
git commit -m "refactor(assets/Show): use usePredictionFormatters composable"
```

---

### Task 7: Update Markets.vue to Use Formatters Composable

**Files:**
- Modify: `resources/js/pages/Markets.vue`

**Step 1: Replace local helper functions with composable**

Remove (around line 94):
```typescript
// REMOVE:
const getStatusColor = (isOpen: boolean) => { ... };
```

Add import:
```typescript
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
```

Add composable initialization:
```typescript
const { getStatusColor } = usePredictionFormatters();
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/Markets.vue
git commit -m "refactor(Markets): use usePredictionFormatters composable"
```

---

### Task 8: Create Prediction Stats Composable

**Files:**
- Create: `resources/js/composables/usePredictionStats.ts`

**Step 1: Create the composable file**

```typescript
// resources/js/composables/usePredictionStats.ts
import { computed, type Ref, type ComputedRef } from 'vue';

interface PredictionWithGain {
    id: string;
    expectedGainPercent: number;
    confidence: number;
    asset: {
        symbol: string;
        market?: { code: string };
    };
    [key: string]: unknown;
}

interface UsePredictionStatsOptions {
    /** Number of items to include in top lists (default: 5) */
    limit?: number;
}

interface UsePredictionStatsReturn<T> {
    /** Top N predictions sorted by expected gain (highest first) */
    topGainers: ComputedRef<T[]>;
    /** Top N predictions sorted by confidence (highest first) */
    mostConfident: ComputedRef<T[]>;
}

/**
 * Composable for computing prediction statistics (top gainers, most confident).
 * Eliminates duplicated sidebar logic across multiple pages.
 *
 * @param predictions - Reactive reference to predictions array
 * @param options - Configuration options
 * @returns Computed properties for top gainers and most confident
 */
export function usePredictionStats<T extends PredictionWithGain>(
    predictions: Ref<T[]> | ComputedRef<T[]>,
    options: UsePredictionStatsOptions = {}
): UsePredictionStatsReturn<T> {
    const { limit = 5 } = options;

    const topGainers = computed(() =>
        [...predictions.value]
            .sort((a, b) => b.expectedGainPercent - a.expectedGainPercent)
            .slice(0, limit)
    );

    const mostConfident = computed(() =>
        [...predictions.value]
            .sort((a, b) => b.confidence - a.confidence)
            .slice(0, limit)
    );

    return {
        topGainers,
        mostConfident,
    };
}
```

**Step 2: Verify the file compiles**

Run: `npm run build 2>&1 | head -20`
Expected: No TypeScript errors

**Step 3: Commit**

```bash
git add resources/js/composables/usePredictionStats.ts
git commit -m "feat: create usePredictionStats composable for sidebar statistics"
```

---

### Task 9: Update Predictions.vue to Use Stats Composable

**Files:**
- Modify: `resources/js/pages/Predictions.vue`

**Step 1: Replace local computed properties with composable**

Remove these computed properties (around lines 63-73):
```typescript
// REMOVE:
const topGainers = computed(() =>
    [...predictions.value]
        .sort((a, b) => b.expectedGainPercent - a.expectedGainPercent)
        .slice(0, 5)
);

const mostConfident = computed(() =>
    [...predictions.value]
        .sort((a, b) => b.confidence - a.confidence)
        .slice(0, 5)
);
```

Add import:
```typescript
import { usePredictionStats } from '@/composables/usePredictionStats';
```

Add composable initialization after `predictions` computed:
```typescript
const { topGainers, mostConfident } = usePredictionStats(predictions);
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/Predictions.vue
git commit -m "refactor(Predictions): use usePredictionStats composable"
```

---

### Task 10: Update markets/Show.vue to Use Stats Composable

**Files:**
- Modify: `resources/js/pages/markets/Show.vue`

**Step 1: Replace local computed properties with composable**

Remove topGainers and mostConfident computed properties (around lines 59-75).

Add import:
```typescript
import { usePredictionStats } from '@/composables/usePredictionStats';
```

Add composable initialization (you may need to adapt based on the data structure):
```typescript
const { topGainers, mostConfident } = usePredictionStats(assets);
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/markets/Show.vue
git commit -m "refactor(markets/Show): use usePredictionStats composable"
```

---

### Task 11: Update sectors/Show.vue to Use Stats Composable

**Files:**
- Modify: `resources/js/pages/sectors/Show.vue`

**Step 1: Replace local computed properties with composable**

Remove topGainers and mostConfident computed properties (around lines 59-75).

Add import:
```typescript
import { usePredictionStats } from '@/composables/usePredictionStats';
```

Add composable initialization:
```typescript
const { topGainers, mostConfident } = usePredictionStats(assets);
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/pages/sectors/Show.vue
git commit -m "refactor(sectors/Show): use usePredictionStats composable"
```

---

### Task 12: Fix Pagination in Predictions.vue

**Files:**
- Modify: `resources/js/pages/Predictions.vue`

**Step 1: Add router and locale imports if not present**

Ensure these are imported:
```typescript
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
const { t, locale } = useI18n();
```

**Step 2: Update Previous button with click handler**

Find the Previous button (around line 287-293) and update:

```vue
<Button
    variant="outline"
    size="sm"
    :disabled="predictionsMeta.currentPage <= 1"
    @click="router.visit(`/${locale}/predictions?page=${predictionsMeta.currentPage - 1}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['predictions']
    })"
>
    {{ t('common.previous') }}
</Button>
```

**Step 3: Update Next button with click handler**

Find the Next button (around line 297-303) and update:

```vue
<Button
    variant="outline"
    size="sm"
    :disabled="predictionsMeta.currentPage >= predictionsMeta.lastPage"
    @click="router.visit(`/${locale}/predictions?page=${predictionsMeta.currentPage + 1}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['predictions']
    })"
>
    {{ t('common.next') }}
</Button>
```

**Step 4: Verify build succeeds and pagination works**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 5: Commit**

```bash
git add resources/js/pages/Predictions.vue
git commit -m "fix(Predictions): add click handlers to pagination buttons"
```

---

### Task 13: Fix Pagination in sectors/Show.vue

**Files:**
- Modify: `resources/js/pages/sectors/Show.vue`

**Step 1: Update Previous button with click handler**

Find the Previous button and update similar to Predictions.vue:

```vue
<Button
    variant="outline"
    size="sm"
    :disabled="assetsMeta.currentPage <= 1"
    @click="router.visit(`/${locale}/sectors/${sector.id}?page=${assetsMeta.currentPage - 1}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['assets']
    })"
>
    {{ t('common.previous') }}
</Button>
```

**Step 2: Update Next button with click handler**

```vue
<Button
    variant="outline"
    size="sm"
    :disabled="assetsMeta.currentPage >= assetsMeta.lastPage"
    @click="router.visit(`/${locale}/sectors/${sector.id}?page=${assetsMeta.currentPage + 1}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['assets']
    })"
>
    {{ t('common.next') }}
</Button>
```

**Step 3: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 4: Commit**

```bash
git add resources/js/pages/sectors/Show.vue
git commit -m "fix(sectors/Show): add click handlers to pagination buttons"
```

---

## Phase 2: Error Handling & Accessibility (Issues #9, #10)

### Task 14: Add Error Handling to useServerSearch

**Files:**
- Modify: `resources/js/composables/useServerSearch.ts`

**Step 1: Add error callback to router.visit**

Update the router.visit call (around line 50):

```typescript
router.visit(window.location.pathname, {
    data,
    preserveState: true,
    preserveScroll: true,
    only,
    onFinish: () => {
        isSearching.value = false;
    },
    onError: (errors) => {
        isSearching.value = false;
        console.error('Search request failed:', errors);
    },
});
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/composables/useServerSearch.ts
git commit -m "feat(useServerSearch): add error handling to prevent stuck loading state"
```

---

### Task 15: Add Error Handling to useServerFilter

**Files:**
- Modify: `resources/js/composables/useServerFilter.ts`

**Step 1: Add error callback to router.visit**

Update the router.visit call:

```typescript
router.visit(window.location.pathname, {
    data,
    preserveState: true,
    preserveScroll: true,
    only: only.length > 0 ? only : undefined,
    onFinish: () => {
        isFiltering.value = false;
    },
    onError: (errors) => {
        isFiltering.value = false;
        console.error('Filter request failed:', errors);
    },
});
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/composables/useServerFilter.ts
git commit -m "feat(useServerFilter): add error handling to prevent stuck loading state"
```

---

### Task 16: Create Accessible Table Row Component

**Files:**
- Create: `resources/js/components/ClickableTableRow.vue`

**Step 1: Create the component**

```vue
<script setup lang="ts">
interface Props {
    /** Accessible label describing the row action */
    ariaLabel: string;
    /** Whether the row is currently selected/active */
    active?: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    click: [];
}>();

const handleClick = () => {
    emit('click');
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        emit('click');
    }
};
</script>

<template>
    <tr
        role="button"
        tabindex="0"
        :aria-label="ariaLabel"
        class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-ring focus:ring-inset"
        :class="{ 'bg-muted/50': active }"
        @click="handleClick"
        @keydown="handleKeydown"
    >
        <slot />
    </tr>
</template>
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/ClickableTableRow.vue
git commit -m "feat: create ClickableTableRow component with keyboard accessibility"
```

---

### Task 17: Update Welcome.vue to Use Accessible Table Rows

**Files:**
- Modify: `resources/js/pages/Welcome.vue`

**Step 1: Import ClickableTableRow component**

Add import:
```typescript
import ClickableTableRow from '@/components/ClickableTableRow.vue';
```

**Step 2: Replace tr elements with ClickableTableRow**

Find the prediction table rows (around line 365) and replace:

```vue
<!-- OLD -->
<tr
    v-for="prediction in sortedPredictions"
    :key="prediction.id"
    class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer"
    @click="router.visit(`/${locale}/assets/${prediction.asset.symbol}`)"
>

<!-- NEW -->
<ClickableTableRow
    v-for="prediction in sortedPredictions"
    :key="prediction.id"
    :aria-label="t('assetDetail.viewDetails', { symbol: prediction.asset.symbol })"
    @click="router.visit(`/${locale}/assets/${prediction.asset.symbol}`)"
>
```

Don't forget to close with `</ClickableTableRow>` instead of `</tr>`.

**Step 3: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 4: Commit**

```bash
git add resources/js/pages/Welcome.vue
git commit -m "refactor(Welcome): use ClickableTableRow for keyboard accessibility"
```

---

## Phase 3: Component Extraction (Issues #5, #6)

### Task 18: Create SidebarStatCard Component

**Files:**
- Create: `resources/js/components/SidebarStatCard.vue`

**Step 1: Create the component**

```vue
<script setup lang="ts">
import { type Component } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import LocalizedLink from '@/components/LocalizedLink.vue';

interface Props {
    /** Card title */
    title: string;
    /** Optional description text */
    description?: string;
    /** Icon component to display */
    icon: Component;
    /** Tailwind classes for icon color */
    iconClass?: string;
}

defineProps<Props>();
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
                <component :is="icon" :class="['size-4', iconClass]" />
                {{ title }}
            </CardTitle>
            <p v-if="description" class="text-xs text-muted-foreground">
                {{ description }}
            </p>
        </CardHeader>
        <CardContent class="space-y-3">
            <slot />
        </CardContent>
    </Card>
</template>
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/SidebarStatCard.vue
git commit -m "feat: create SidebarStatCard component for reusable sidebar cards"
```

---

### Task 19: Create PredictionListItem Component

**Files:**
- Create: `resources/js/components/PredictionListItem.vue`

**Step 1: Create the component**

```vue
<script setup lang="ts">
import LocalizedLink from '@/components/LocalizedLink.vue';

interface Props {
    /** Asset symbol */
    symbol: string;
    /** Link href */
    href: string;
    /** Optional market code to display */
    marketCode?: string;
}

defineProps<Props>();
</script>

<template>
    <LocalizedLink
        :href="href"
        class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
    >
        <div>
            <span class="font-medium">{{ symbol }}</span>
            <span v-if="marketCode" class="ms-1 text-xs text-muted-foreground">
                {{ marketCode }}
            </span>
        </div>
        <slot name="value" />
    </LocalizedLink>
</template>
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/PredictionListItem.vue
git commit -m "feat: create PredictionListItem component for sidebar list items"
```

---

### Task 20: Create PaginationControls Component

**Files:**
- Create: `resources/js/components/PaginationControls.vue`

**Step 1: Create the component**

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useI18n } from 'vue-i18n';

interface Props {
    /** Current page number */
    currentPage: number;
    /** Total number of pages */
    lastPage: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'page-change': [page: number];
}>();

const { t } = useI18n();

const goToPreviousPage = () => {
    if (props.currentPage > 1) {
        emit('page-change', props.currentPage - 1);
    }
};

const goToNextPage = () => {
    if (props.currentPage < props.lastPage) {
        emit('page-change', props.currentPage + 1);
    }
};
</script>

<template>
    <div v-if="lastPage > 1" class="mt-4 flex items-center justify-center gap-2">
        <Button
            variant="outline"
            size="sm"
            :disabled="currentPage <= 1"
            @click="goToPreviousPage"
        >
            {{ t('common.previous') }}
        </Button>
        <span class="text-sm text-muted-foreground">
            {{ currentPage }} / {{ lastPage }}
        </span>
        <Button
            variant="outline"
            size="sm"
            :disabled="currentPage >= lastPage"
            @click="goToNextPage"
        >
            {{ t('common.next') }}
        </Button>
    </div>
</template>
```

**Step 2: Verify build succeeds**

Run: `npm run build 2>&1 | tail -10`
Expected: Build completes without errors

**Step 3: Commit**

```bash
git add resources/js/components/PaginationControls.vue
git commit -m "feat: create PaginationControls component for reusable pagination"
```

---

## Phase 4: Final Verification

### Task 21: Run Full Build and Verify

**Step 1: Run PHP linting**

Run: `vendor/bin/pint --dirty`
Expected: PASS with no changes needed

**Step 2: Run full frontend build**

Run: `npm run build`
Expected: Build completes successfully without errors

**Step 3: Run any available tests**

Run: `php artisan test 2>&1 | head -30`
Expected: Tests pass (or known failures unrelated to this refactor)

**Step 4: Final commit**

```bash
git add -A
git commit -m "chore: complete frontend refactoring for DRY and SOLID principles"
```

---

## Summary of New Files Created

| File | Purpose |
|------|---------|
| `composables/usePredictionFormatters.ts` | Shared formatting utilities (formatGain, getConfidenceColor, etc.) |
| `composables/usePredictionStats.ts` | Computed stats for sidebars (topGainers, mostConfident) |
| `components/ClickableTableRow.vue` | Accessible table row with keyboard navigation |
| `components/SidebarStatCard.vue` | Reusable sidebar card container |
| `components/PredictionListItem.vue` | Reusable sidebar list item |
| `components/PaginationControls.vue` | Reusable pagination controls |

## Files Modified

| File | Changes |
|------|---------|
| `pages/Welcome.vue` | Use composables, accessible table rows |
| `pages/Predictions.vue` | Use composables, fix pagination |
| `pages/Markets.vue` | Use formatters composable |
| `pages/markets/Show.vue` | Use composables |
| `pages/sectors/Show.vue` | Use composables, fix pagination |
| `pages/assets/Show.vue` | Use formatters composable |
| `composables/useServerSearch.ts` | Add error handling |
| `composables/useServerFilter.ts` | Add error handling |

## Estimated Time

- Phase 1 (Critical): ~45 minutes (13 tasks)
- Phase 2 (Error Handling & Accessibility): ~20 minutes (4 tasks)
- Phase 3 (Component Extraction): ~15 minutes (3 tasks)
- Phase 4 (Verification): ~10 minutes (1 task)

**Total: ~90 minutes**
