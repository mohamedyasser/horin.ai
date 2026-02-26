<script setup lang="ts">
import AssetDisplay from '@/components/AssetDisplay.vue';
import ClickableTableRow from '@/components/ClickableTableRow.vue';
import FilterButtonBar from '@/components/FilterButtonBar.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SearchableSelect } from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useServerSearch } from '@/composables/useServerSearch';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { PaginationMeta } from '@/types';
import { Deferred, Head, router } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    ChevronDown,
    Loader2,
    Search,
    SlidersHorizontal,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface MarketOption {
    id: string;
    code: string;
    name: string;
    country?: {
        id: string;
        name: string;
        code: string;
    } | null;
}

interface SectorOption {
    id: string;
    name: string;
}

interface CountryOption {
    id: string;
    name: string;
    code: string;
}

interface RecommendationItem {
    id: string;
    pid: string;
    asset: {
        id: string;
        symbol: string;
        name: string;
        market?: {
            id: string;
            code: string;
            name: string;
        } | null;
        sector?: {
            id: string;
            name: string;
        } | null;
        currentPrice?: number | null;
        priceChange?: string | null;
    };
    score: number;
    recommendation: string;
    createdAt?: string | null;
}

interface Props {
    canLogin: boolean;
    canRegister: boolean;
    markets: MarketOption[];
    sectors: SectorOption[];
    countries: CountryOption[];
    filters?: {
        search?: string | null;
        market?: string | null;
        sector?: string | null;
        country?: string | null;
        recommendation?: string | null;
    };
    recommendations?: {
        data: RecommendationItem[];
        meta: PaginationMeta;
    };
    topBuySignals?: RecommendationItem[];
    topSellSignals?: RecommendationItem[];
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
    markets: () => [],
    sectors: () => [],
    countries: () => [],
});

// Server-side search
const { searchQuery, isSearching } = useServerSearch({
    initialValue: props.filters?.search,
    preserveParams: ['market', 'sector', 'country', 'recommendation'],
    only: ['recommendations', 'filters'],
});

// State
const filterOpen = ref(false);
const selectedMarket = ref<string | null>(props.filters?.market ?? null);
const selectedSector = ref<string | null>(props.filters?.sector ?? null);
const selectedCountry = ref<string | null>(props.filters?.country ?? null);
const selectedRecommendation = ref<string | null>(
    props.filters?.recommendation ?? null,
);
const sortBy = ref<'score' | 'newest'>('score');

// Recommendation type options
const recommendationOptions = [
    { value: 'STRONG_BUY', label: t('recommendations.actions.STRONG_BUY') },
    { value: 'BUY', label: t('recommendations.actions.BUY') },
    { value: 'ACCUMULATE', label: t('recommendations.actions.ACCUMULATE') },
    { value: 'HOLD', label: t('recommendations.actions.HOLD') },
    { value: 'REDUCE', label: t('recommendations.actions.REDUCE') },
    { value: 'SELL', label: t('recommendations.actions.SELL') },
    { value: 'STRONG_SELL', label: t('recommendations.actions.STRONG_SELL') },
];

// Computed - options for searchable selects
const marketOptions = computed(() =>
    props.markets.map((m) => ({ value: m.code, label: m.name })),
);

const sectorOptions = computed(() =>
    props.sectors.map((s) => ({ value: s.id, label: s.name })),
);

const countryOptions = computed(() =>
    props.countries.map((c) => ({ value: c.id, label: c.name })),
);

// Count active filters
const activeFilterCount = computed(() => {
    let count = 0;
    if (selectedMarket.value) count++;
    if (selectedSector.value) count++;
    if (selectedCountry.value) count++;
    if (selectedRecommendation.value) count++;
    return count;
});

// Apply filters
const applyFilters = () => {
    const currentParams = new URLSearchParams(window.location.search);
    const data: Record<string, string | undefined> = {};

    const search = currentParams.get('search');
    if (search) data.search = search;

    if (selectedMarket.value) data.market = selectedMarket.value;
    if (selectedSector.value) data.sector = selectedSector.value;
    if (selectedCountry.value) data.country = selectedCountry.value;
    if (selectedRecommendation.value)
        data.recommendation = selectedRecommendation.value;

    router.visit(window.location.pathname, {
        data,
        preserveState: true,
        preserveScroll: true,
        only: ['recommendations', 'filters'],
    });

    filterOpen.value = false;
};

// Clear all filters
const clearFilters = () => {
    selectedMarket.value = null;
    selectedSector.value = null;
    selectedCountry.value = null;
    selectedRecommendation.value = null;
    applyFilters();
};

// Quick market filter from button bar
const filterByMarket = (marketCode: string | null) => {
    selectedMarket.value = marketCode;
    applyFilters();
};

// Computed - use props data directly (already filtered by server)
const recommendations = computed(() => props.recommendations?.data ?? []);
const recommendationsMeta = computed(() => props.recommendations?.meta);

// Sort recommendations client-side
const sortedRecommendations = computed(() => {
    const result = [...recommendations.value];

    if (sortBy.value === 'score') {
        result.sort((a, b) => b.score - a.score);
    } else {
        result.sort((a, b) => {
            const dateA = a.createdAt ? new Date(a.createdAt).getTime() : 0;
            const dateB = b.createdAt ? new Date(b.createdAt).getTime() : 0;
            return dateB - dateA;
        });
    }

    return result;
});

// Helper functions
const getRecommendationVariant = (
    recommendation: string,
): 'gain' | 'loss' | 'outline' => {
    switch (recommendation) {
        case 'STRONG_BUY':
        case 'BUY':
        case 'ACCUMULATE':
            return 'gain';
        case 'HOLD':
            return 'outline';
        case 'REDUCE':
        case 'SELL':
        case 'STRONG_SELL':
            return 'loss';
        default:
            return 'outline';
    }
};

const getScoreColor = (score: number) => {
    if (score >= 70) return 'text-gain';
    if (score >= 40) return 'text-foreground';
    return 'text-loss';
};

const formatPriceChange = (change: string | null | undefined) => {
    if (!change) return null;
    const numericValue = parseFloat(change.replace('%', ''));
    return isNaN(numericValue) ? null : numericValue;
};

// Recommendation distribution
const distribution = computed(() => {
    const counts: Record<string, number> = {};
    for (const rec of recommendations.value) {
        const key = rec.recommendation;
        counts[key] = (counts[key] || 0) + 1;
    }
    const total = recommendations.value.length;
    const buyCount = (counts['STRONG_BUY'] || 0) + (counts['BUY'] || 0) + (counts['ACCUMULATE'] || 0);
    const holdCount = counts['HOLD'] || 0;
    const sellCount = (counts['REDUCE'] || 0) + (counts['SELL'] || 0) + (counts['STRONG_SELL'] || 0);
    return { buyCount, holdCount, sellCount, total };
});

// Pagination
const goToPage = (page: number) => {
    const currentParams = new URLSearchParams(window.location.search);
    const data: Record<string, string> = {};

    // Preserve all current params
    currentParams.forEach((value, key) => {
        data[key] = value;
    });

    data.page = String(page);

    router.visit(window.location.pathname, {
        data,
        preserveState: true,
        preserveScroll: true,
        only: ['recommendations', 'filters'],
    });
};
</script>

<template>
    <Head :title="t('recommendations.title')">
        <meta name="description" :content="t('meta.recommendations')" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <!-- Hero Section -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-7xl px-6 pt-10 pb-6 text-center">
                <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ t('recommendations.title') }}
                </h1>
                <p class="mt-2 text-base text-muted-foreground">
                    {{ t('recommendations.subtitle') }}
                </p>

                <!-- Search Bar -->
                <div class="relative mx-auto mt-5 max-w-xl">
                    <Search
                        v-if="!isSearching"
                        class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Loader2
                        v-else
                        class="absolute start-3 top-1/2 size-5 -translate-y-1/2 animate-spin text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('predictions.searchPlaceholder')"
                        class="h-11 ps-10 text-base"
                    />
                </div>
            </div>
        </section>

        <!-- Distribution Summary -->
        <section v-if="distribution.total > 0" class="border-b border-border">
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-6 px-6 py-3 text-sm sm:gap-10">
                <div class="flex items-center gap-1.5">
                    <TrendingUp class="size-3.5 text-gain" />
                    <span class="font-medium text-gain tabular-nums">{{ distribution.buyCount }}</span>
                    <span class="text-xs text-muted-foreground">{{ t('recommendations.buyLabel') }}</span>
                </div>
                <div class="h-4 w-px bg-border" />
                <div class="flex items-center gap-1.5">
                    <span class="font-medium tabular-nums">{{ distribution.holdCount }}</span>
                    <span class="text-xs text-muted-foreground">{{ t('recommendations.holdLabel') }}</span>
                </div>
                <div class="h-4 w-px bg-border" />
                <div class="flex items-center gap-1.5">
                    <TrendingDown class="size-3.5 text-loss" />
                    <span class="font-medium text-loss tabular-nums">{{ distribution.sellCount }}</span>
                    <span class="text-xs text-muted-foreground">{{ t('recommendations.sellLabel') }}</span>
                </div>
            </div>
        </section>

        <!-- Market Filter Bar -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-7xl px-6 py-4">
                <FilterButtonBar
                    :model-value="selectedMarket"
                    :options="marketOptions"
                    :all-label-key="'home.allMarkets'"
                    @update:model-value="filterByMarket"
                />
            </div>
        </section>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-6 py-6">
            <div class="grid gap-6 lg:grid-cols-4">
                <!-- Recommendations Table -->
                <div class="lg:col-span-3">
                    <!-- Controls -->
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold">
                            {{ t('recommendations.title') }}
                        </h2>
                        <div class="flex items-center gap-2">
                            <!-- Filter Button -->
                            <Dialog v-model:open="filterOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        <SlidersHorizontal
                                            class="me-1 size-4"
                                        />
                                        {{ t('home.filters') }}
                                        <span
                                            v-if="activeFilterCount > 0"
                                            class="ms-1 rounded-full bg-primary px-1.5 text-xs text-primary-foreground"
                                        >
                                            {{ activeFilterCount }}
                                        </span>
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>{{
                                            t('home.filterPredictions')
                                        }}</DialogTitle>
                                    </DialogHeader>
                                    <div class="grid gap-4 py-4">
                                        <!-- Market Select -->
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t('predictions.market')
                                            }}</Label>
                                            <SearchableSelect
                                                v-model="selectedMarket"
                                                :options="marketOptions"
                                                :placeholder="
                                                    t('predictions.allMarkets')
                                                "
                                                :empty-text="
                                                    t('home.noResults')
                                                "
                                            />
                                        </div>

                                        <!-- Sector Select -->
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t('predictions.sector')
                                            }}</Label>
                                            <SearchableSelect
                                                v-model="selectedSector"
                                                :options="sectorOptions"
                                                :placeholder="
                                                    t('predictions.allSectors')
                                                "
                                                :empty-text="
                                                    t('home.noResults')
                                                "
                                            />
                                        </div>

                                        <!-- Country Select -->
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t('markets.country')
                                            }}</Label>
                                            <SearchableSelect
                                                v-model="selectedCountry"
                                                :options="countryOptions"
                                                :placeholder="
                                                    t('markets.allCountries')
                                                "
                                                :empty-text="
                                                    t('home.noResults')
                                                "
                                            />
                                        </div>

                                        <!-- Recommendation Type Select -->
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t('recommendations.title')
                                            }}</Label>
                                            <SearchableSelect
                                                v-model="selectedRecommendation"
                                                :options="recommendationOptions"
                                                :placeholder="t('common.all')"
                                                :empty-text="
                                                    t('home.noResults')
                                                "
                                            />
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            @click="clearFilters"
                                        >
                                            {{ t('common.clear') }}
                                        </Button>
                                        <Button @click="applyFilters">
                                            {{ t('common.apply') }}
                                        </Button>
                                    </div>
                                </DialogContent>
                            </Dialog>

                            <!-- Sort Dropdown -->
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        {{ t('predictions.sortBy') }}
                                        <ChevronDown class="ms-1 size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="sortBy = 'score'">
                                        {{ t('recommendations.score') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        @click="sortBy = 'newest'"
                                    >
                                        {{ t('predictions.newest') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <Deferred data="recommendations">
                        <template #fallback>
                            <!-- Loading skeleton -->
                            <div class="rounded-md border border-border">
                                <div class="animate-pulse space-y-4 p-4">
                                    <div
                                        v-for="i in 10"
                                        :key="i"
                                        class="flex items-center gap-4"
                                    >
                                        <div
                                            class="h-10 w-20 rounded bg-muted"
                                        />
                                        <div
                                            class="h-4 flex-1 rounded bg-muted"
                                        />
                                        <div
                                            class="h-4 w-16 rounded bg-muted"
                                        />
                                        <div
                                            class="h-4 w-16 rounded bg-muted"
                                        />
                                        <div
                                            class="h-4 w-16 rounded bg-muted"
                                        />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="rounded-md border border-border">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr
                                            class="border-b border-border bg-muted/50"
                                        >
                                            <th
                                                class="px-4 py-3 text-start text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        'predictions.table.symbol',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-start text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t('predictions.table.name')
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        'predictions.table.market',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-end text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        'predictions.table.lastPrice',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-end text-sm font-medium text-muted-foreground"
                                            >
                                                {{ t('search.table.change') }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-sm font-medium text-muted-foreground"
                                            >
                                                {{ t('recommendations.title') }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-end text-sm font-medium text-muted-foreground"
                                            >
                                                {{ t('recommendations.score') }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        'predictions.table.action',
                                                    )
                                                }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <ClickableTableRow
                                            v-for="item in sortedRecommendations"
                                            :key="item.id"
                                            :aria-label="`${t('common.viewDetailsFor')} ${item.asset.symbol} - ${item.asset.name}`"
                                            @click="
                                                router.visit(
                                                    `/${locale}/assets/${item.asset.symbol}`,
                                                )
                                            "
                                        >
                                            <td class="px-4 py-3">
                                                <AssetDisplay
                                                    :symbol="item.asset.symbol"
                                                    :sector-name="
                                                        item.asset.sector?.name
                                                    "
                                                    :show-name="false"
                                                    :show-logo="false"
                                                    size="md"
                                                />
                                            </td>
                                            <td
                                                class="px-4 py-3 text-sm text-muted-foreground"
                                            >
                                                {{ item.asset.name }}
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span
                                                    v-if="item.asset.market"
                                                    class="rounded bg-muted px-2 py-0.5 text-xs font-medium"
                                                >
                                                    {{ item.asset.market.code }}
                                                </span>
                                            </td>
                                            <td
                                                dir="ltr"
                                                class="px-4 py-3 text-end text-sm tabular-nums"
                                            >
                                                {{
                                                    item.asset.currentPrice?.toFixed(
                                                        2,
                                                    ) ?? '-'
                                                }}
                                            </td>
                                            <td class="px-4 py-3 text-end">
                                                <span
                                                    v-if="
                                                        formatPriceChange(
                                                            item.asset
                                                                .priceChange,
                                                        ) !== null
                                                    "
                                                    dir="ltr"
                                                    class="inline-flex items-center gap-0.5 text-sm font-medium tabular-nums"
                                                    :class="
                                                        formatPriceChange(
                                                            item.asset
                                                                .priceChange,
                                                        )! >= 0
                                                            ? 'text-gain'
                                                            : 'text-loss'
                                                    "
                                                >
                                                    <ArrowUpRight
                                                        v-if="
                                                            formatPriceChange(
                                                                item.asset
                                                                    .priceChange,
                                                            )! >= 0
                                                        "
                                                        class="size-3"
                                                    />
                                                    <ArrowDownRight
                                                        v-else
                                                        class="size-3"
                                                    />
                                                    {{
                                                        Math.abs(
                                                            formatPriceChange(
                                                                item.asset
                                                                    .priceChange,
                                                            )!,
                                                        ).toFixed(2)
                                                    }}%
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-sm text-muted-foreground"
                                                    >-</span
                                                >
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <Badge
                                                    :variant="
                                                        getRecommendationVariant(
                                                            item.recommendation,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            `recommendations.actions.${item.recommendation}`,
                                                        )
                                                    }}
                                                </Badge>
                                            </td>
                                            <td
                                                dir="ltr"
                                                class="px-4 py-3 text-end"
                                            >
                                                <span
                                                    :class="
                                                        getScoreColor(
                                                            item.score,
                                                        )
                                                    "
                                                    class="font-medium tabular-nums"
                                                >
                                                    {{ item.score.toFixed(1) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <Button
                                                    as-child
                                                    variant="ghost"
                                                    size="sm"
                                                    @click.stop
                                                >
                                                    <LocalizedLink
                                                        :href="`/assets/${item.asset.symbol}`"
                                                    >
                                                        {{
                                                            t(
                                                                'predictions.viewDetails',
                                                            )
                                                        }}
                                                    </LocalizedLink>
                                                </Button>
                                            </td>
                                        </ClickableTableRow>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="sortedRecommendations.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <Search
                                    class="size-12 text-muted-foreground/50"
                                />
                                <p class="mt-4 text-muted-foreground">
                                    {{ t('predictions.noResults') }}
                                </p>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div
                            v-if="
                                recommendationsMeta &&
                                recommendationsMeta.lastPage > 1
                            "
                            class="mt-4 flex items-center justify-center gap-2"
                        >
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="recommendationsMeta.currentPage <= 1"
                                @click="
                                    goToPage(
                                        recommendationsMeta.currentPage - 1,
                                    )
                                "
                            >
                                {{ t('common.previous') }}
                            </Button>
                            <span
                                dir="ltr"
                                class="text-sm text-muted-foreground"
                            >
                                {{ recommendationsMeta.currentPage }} /
                                {{ recommendationsMeta.lastPage }}
                            </span>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="
                                    recommendationsMeta.currentPage >=
                                    recommendationsMeta.lastPage
                                "
                                @click="
                                    goToPage(
                                        recommendationsMeta.currentPage + 1,
                                    )
                                "
                            >
                                {{ t('common.next') }}
                            </Button>
                        </div>
                    </Deferred>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Top Buy Signals -->
                    <Deferred data="topBuySignals">
                        <template #fallback>
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle
                                        class="flex items-center gap-2 text-base"
                                    >
                                        <TrendingUp
                                            class="size-4 text-foreground"
                                        />
                                        {{ t('sidebar.topBuySignals') }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div
                                        v-for="i in 5"
                                        :key="i"
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            class="h-4 w-16 animate-pulse rounded bg-muted"
                                        />
                                        <div
                                            class="h-5 w-20 animate-pulse rounded bg-muted"
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </template>

                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle
                                    class="flex items-center gap-2 text-base"
                                >
                                    <TrendingUp
                                        class="size-4 text-foreground"
                                    />
                                    {{ t('sidebar.topBuySignals') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <LocalizedLink
                                    v-for="item in props.topBuySignals"
                                    :key="item.id"
                                    :href="`/assets/${item.asset.symbol}`"
                                    class="-mx-2 flex items-center justify-between rounded-md px-2 py-1 transition-colors hover:bg-muted"
                                >
                                    <AssetDisplay
                                        :symbol="item.asset.symbol"
                                        :market-code="item.asset.market?.code"
                                        :show-name="false"
                                        :show-logo="false"
                                        size="sm"
                                    />
                                    <Badge
                                        :variant="
                                            getRecommendationVariant(
                                                item.recommendation,
                                            )
                                        "
                                        class="text-xs"
                                    >
                                        {{
                                            t(
                                                `recommendations.actions.${item.recommendation}`,
                                            )
                                        }}
                                    </Badge>
                                </LocalizedLink>
                                <p
                                    v-if="!props.topBuySignals?.length"
                                    class="py-2 text-center text-sm text-muted-foreground"
                                >
                                    {{ t('common.noData') }}
                                </p>
                            </CardContent>
                        </Card>
                    </Deferred>

                    <!-- Top Sell Signals -->
                    <Deferred data="topSellSignals">
                        <template #fallback>
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle
                                        class="flex items-center gap-2 text-base"
                                    >
                                        <TrendingDown
                                            class="size-4 text-foreground"
                                        />
                                        {{ t('sidebar.topSellSignals') }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-3">
                                    <div
                                        v-for="i in 5"
                                        :key="i"
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            class="h-4 w-16 animate-pulse rounded bg-muted"
                                        />
                                        <div
                                            class="h-5 w-20 animate-pulse rounded bg-muted"
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </template>

                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle
                                    class="flex items-center gap-2 text-base"
                                >
                                    <TrendingDown
                                        class="size-4 text-foreground"
                                    />
                                    {{ t('sidebar.topSellSignals') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <LocalizedLink
                                    v-for="item in props.topSellSignals"
                                    :key="item.id"
                                    :href="`/assets/${item.asset.symbol}`"
                                    class="-mx-2 flex items-center justify-between rounded-md px-2 py-1 transition-colors hover:bg-muted"
                                >
                                    <AssetDisplay
                                        :symbol="item.asset.symbol"
                                        :market-code="item.asset.market?.code"
                                        :show-name="false"
                                        :show-logo="false"
                                        size="sm"
                                    />
                                    <Badge
                                        :variant="
                                            getRecommendationVariant(
                                                item.recommendation,
                                            )
                                        "
                                        class="text-xs"
                                    >
                                        {{
                                            t(
                                                `recommendations.actions.${item.recommendation}`,
                                            )
                                        }}
                                    </Badge>
                                </LocalizedLink>
                                <p
                                    v-if="!props.topSellSignals?.length"
                                    class="py-2 text-center text-sm text-muted-foreground"
                                >
                                    {{ t('common.noData') }}
                                </p>
                            </CardContent>
                        </Card>
                    </Deferred>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
