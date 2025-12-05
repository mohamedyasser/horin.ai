<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LocalizedLink from '@/components/LocalizedLink.vue';
import FilterButtonBar from '@/components/FilterButtonBar.vue';
import { SearchableSelect } from '@/components/ui/combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import GuestLayout from '@/layouts/GuestLayout.vue';
import {
    Search,
    ChevronDown,
    TrendingUp,
    Clock,
    Target,
    ArrowUpRight,
    ArrowDownRight,
    Loader2,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { useServerSearch } from '@/composables/useServerSearch';
import type {
    HomeStats,
    MarketPreview,
    SectorPreview,
    FeaturedPrediction,
    TopMover,
    RecentPrediction,
} from '@/types';

const { t, locale } = useI18n();

interface CountryPreview {
    id: string;
    name: string;
    code: string;
}

interface Props {
    canLogin: boolean;
    canRegister: boolean;
    stats: HomeStats;
    markets: MarketPreview[];
    sectors: SectorPreview[];
    countries: CountryPreview[];
    filters?: {
        search?: string | null;
        market?: string | null;
        sector?: string | null;
        country?: string | null;
    };
    featuredPredictions?: {
        data: FeaturedPrediction[];
    };
    topMovers?: TopMover[];
    recentPredictions?: RecentPrediction[];
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Server-side search
const { searchQuery, isSearching } = useServerSearch({
    initialValue: props.filters?.search,
    preserveParams: ['market', 'sector', 'country'],
    only: ['featuredPredictions', 'filters'],
});

// State
const filterOpen = ref(false);
const selectedMarket = ref<string | null>(props.filters?.market ?? null);
const selectedSector = ref<string | null>(props.filters?.sector ?? null);
const selectedCountry = ref<string | null>(props.filters?.country ?? null);
const sortBy = ref<'gain' | 'confidence' | 'newest'>('gain');

// Computed - options for searchable selects
const marketOptions = computed(() =>
    props.markets.map((m) => ({ value: m.code, label: `${m.name}` }))
);

const sectorOptions = computed(() =>
    props.sectors.map((s) => ({ value: s.id, label: s.name }))
);

const countryOptions = computed(() =>
    props.countries.map((c) => ({ value: c.id, label: c.name }))
);

// Count active filters
const activeFilterCount = computed(() => {
    let count = 0;
    if (selectedMarket.value) count++;
    if (selectedSector.value) count++;
    if (selectedCountry.value) count++;
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

    router.visit(window.location.pathname, {
        data,
        preserveState: true,
        preserveScroll: true,
        only: ['featuredPredictions', 'filters'],
    });

    filterOpen.value = false;
};

// Clear all filters
const clearFilters = () => {
    selectedMarket.value = null;
    selectedSector.value = null;
    selectedCountry.value = null;
    applyFilters();
};

// Quick market filter from button bar
const filterByMarket = (marketCode: string | null) => {
    selectedMarket.value = marketCode;
    applyFilters();
};

// Computed - use props data directly (already filtered by server)
const featuredPredictions = computed(() => props.featuredPredictions?.data ?? []);
const topMovers = computed(() => props.topMovers ?? []);
const recentPredictions = computed(() => props.recentPredictions ?? []);

// Sort predictions client-side (sorting doesn't require server round-trip)
const sortedPredictions = computed(() => {
    const result = [...featuredPredictions.value];

    if (sortBy.value === 'gain') {
        result.sort((a, b) => b.expectedGainPercent - a.expectedGainPercent);
    } else if (sortBy.value === 'confidence') {
        result.sort((a, b) => b.confidence - a.confidence);
    } else {
        result.sort((a, b) => {
            const dateA = a.timestamp ? new Date(a.timestamp).getTime() : 0;
            const dateB = b.timestamp ? new Date(b.timestamp).getTime() : 0;
            return dateB - dateA;
        });
    }

    return result;
});

// Helpers
const formatGain = (gain: number) => {
    const sign = gain >= 0 ? '+' : '';
    return `${sign}${gain.toFixed(1)}%`;
};

const getConfidenceColor = (confidence: number) => {
    if (confidence >= 85) return 'text-green-600 dark:text-green-400';
    if (confidence >= 70) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
};

</script>

<template>
    <Head :title="t('home.title')">
        <meta name="description" :content="t('meta.home')">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <!-- Hero Section -->
        <section class="border-b border-border/40 bg-muted/30">
            <div class="mx-auto max-w-7xl px-4 py-12 text-center">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                    {{ t('home.heroTitle') }}
                </h1>
                <p class="mt-3 text-lg text-muted-foreground">
                    {{ t('home.heroSubtitle') }}
                </p>

                <!-- Search Bar -->
                <div class="relative mx-auto mt-8 max-w-xl">
                    <Search v-if="!isSearching" class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                    <Loader2 v-else class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground animate-spin" />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('home.searchPlaceholder')"
                        class="h-12 ps-10 text-base"
                    />
                </div>
            </div>
        </section>

        <!-- Market Filter Bar -->
        <section class="border-b border-border/40">
            <div class="mx-auto max-w-7xl px-4 py-4">
                <FilterButtonBar
                    :model-value="selectedMarket"
                    :options="marketOptions"
                    :all-label-key="'home.allMarkets'"
                    @update:model-value="filterByMarket"
                />
            </div>
        </section>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="grid gap-8 lg:grid-cols-4">
                <!-- Predictions Table -->
                <div class="lg:col-span-3">
                    <!-- Controls -->
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-xl font-semibold">{{ t('home.predictions') }}</h2>
                        <div class="flex items-center gap-2">
                            <!-- Filter Button -->
                            <Dialog v-model:open="filterOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        <SlidersHorizontal class="me-1 size-4" />
                                        {{ t('home.filters') }}
                                        <span v-if="activeFilterCount > 0" class="ms-1 rounded-full bg-primary px-1.5 text-xs text-primary-foreground">
                                            {{ activeFilterCount }}
                                        </span>
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="sm:max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>{{ t('home.filterPredictions') }}</DialogTitle>
                                    </DialogHeader>
                                    <div class="grid gap-4 py-4">
                                        <!-- Market Select -->
                                        <div class="grid gap-2">
                                            <Label>{{ t('predictions.market') }}</Label>
                                            <SearchableSelect
                                                v-model="selectedMarket"
                                                :options="marketOptions"
                                                :placeholder="t('predictions.allMarkets')"
                                                :empty-text="t('home.noResults')"
                                            />
                                        </div>

                                        <!-- Sector Select -->
                                        <div class="grid gap-2">
                                            <Label>{{ t('predictions.sector') }}</Label>
                                            <SearchableSelect
                                                v-model="selectedSector"
                                                :options="sectorOptions"
                                                :placeholder="t('predictions.allSectors')"
                                                :empty-text="t('home.noResults')"
                                            />
                                        </div>

                                        <!-- Country Select -->
                                        <div class="grid gap-2">
                                            <Label>{{ t('markets.country') }}</Label>
                                            <SearchableSelect
                                                v-model="selectedCountry"
                                                :options="countryOptions"
                                                :placeholder="t('markets.allCountries')"
                                                :empty-text="t('home.noResults')"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" @click="clearFilters">
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
                                        {{ t('home.sortBy') }}
                                        <ChevronDown class="ms-1 size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem @click="sortBy = 'gain'">
                                        {{ t('home.highestGain') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="sortBy = 'confidence'">
                                        {{ t('home.confidence') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="sortBy = 'newest'">
                                        {{ t('home.newest') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    <!-- Predictions Table with Deferred Loading -->
                    <Deferred data="featuredPredictions">
                        <template #fallback>
                            <div class="rounded-lg border border-border">
                                <div class="space-y-4 p-4">
                                    <div v-for="i in 6" :key="i" class="animate-pulse">
                                        <div class="h-16 bg-muted rounded-lg"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="rounded-lg border border-border">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-border bg-muted/50">
                                            <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                {{ t('home.table.symbol') }}
                                            </th>
                                            <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                {{ t('home.table.name') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('home.table.current') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('home.table.predicted') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('home.table.gainPercent') }}
                                            </th>
                                            <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                {{ t('home.horizon') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('home.confidence') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="prediction in sortedPredictions"
                                            :key="prediction.id"
                                            class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer"
                                            @click="router.visit(`/${locale}/assets/${prediction.asset.symbol}`)"
                                        >
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium">{{ prediction.asset.symbol }}</span>
                                                    <span v-if="prediction.asset.market" class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                        {{ prediction.asset.market.code }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-muted-foreground">
                                                {{ prediction.asset.name }}
                                            </td>
                                            <td class="px-4 py-3 text-end text-sm">
                                                <template v-if="prediction.currentPrice">
                                                    {{ prediction.currentPrice.toFixed(2) }}
                                                </template>
                                                <span v-else class="text-muted-foreground">-</span>
                                            </td>
                                            <td class="px-4 py-3 text-end text-sm font-medium">
                                                {{ prediction.predictedPrice.toFixed(2) }}
                                            </td>
                                            <td class="px-4 py-3 text-end">
                                                <span
                                                    class="inline-flex items-center gap-0.5 font-medium"
                                                    :class="prediction.expectedGainPercent >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                                >
                                                    <ArrowUpRight v-if="prediction.expectedGainPercent >= 0" class="size-4" />
                                                    <ArrowDownRight v-else class="size-4" />
                                                    {{ formatGain(prediction.expectedGainPercent) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="rounded-full bg-muted px-2 py-1 text-xs font-medium">
                                                    {{ prediction.horizonLabel }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-end">
                                                <span :class="getConfidenceColor(prediction.confidence)" class="font-medium">
                                                    {{ prediction.confidence }}%
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="sortedPredictions.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <Search class="size-12 text-muted-foreground/50" />
                                <p class="mt-4 text-muted-foreground">
                                    {{ t('home.noResults') }}
                                </p>
                            </div>
                        </div>
                    </Deferred>

                    <!-- Results count -->
                    <div class="mt-4 text-center text-sm text-muted-foreground">
                        {{ t('home.showingPredictions', { count: sortedPredictions.length }) }}
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Top Movers -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <TrendingUp class="size-4 text-green-500" />
                                {{ t('home.topMovers') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred data="topMovers">
                                <template #fallback>
                                    <div class="space-y-3">
                                        <div v-for="i in 5" :key="i" class="animate-pulse">
                                            <div class="h-8 bg-muted rounded"></div>
                                        </div>
                                    </div>
                                </template>
                                <div class="space-y-3">
                                    <LocalizedLink
                                        v-for="mover in topMovers"
                                        :key="mover.id"
                                        :href="`/assets/${mover.symbol}`"
                                        class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                    >
                                        <div>
                                            <span class="font-medium">{{ mover.symbol }}</span>
                                            <span class="ms-1 text-xs text-muted-foreground">{{ mover.market.code }}</span>
                                        </div>
                                        <span class="font-medium text-green-600 dark:text-green-400">
                                            {{ formatGain(mover.priceChangePercent) }}
                                        </span>
                                    </LocalizedLink>
                                </div>
                            </Deferred>
                        </CardContent>
                    </Card>

                    <!-- Highest Confidence (derived from featuredPredictions) -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Target class="size-4 text-blue-500" />
                                {{ t('home.highestConfidence') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred data="featuredPredictions">
                                <template #fallback>
                                    <div class="space-y-3">
                                        <div v-for="i in 5" :key="i" class="animate-pulse">
                                            <div class="h-8 bg-muted rounded"></div>
                                        </div>
                                    </div>
                                </template>
                                <div class="space-y-3">
                                    <LocalizedLink
                                        v-for="prediction in [...featuredPredictions].sort((a, b) => b.confidence - a.confidence).slice(0, 5)"
                                        :key="prediction.id"
                                        :href="`/assets/${prediction.asset.symbol}`"
                                        class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                    >
                                        <div>
                                            <span class="font-medium">{{ prediction.asset.symbol }}</span>
                                            <span v-if="prediction.asset.market" class="ms-1 text-xs text-muted-foreground">{{ prediction.asset.market.code }}</span>
                                        </div>
                                        <span :class="getConfidenceColor(prediction.confidence)" class="font-medium">
                                            {{ prediction.confidence }}%
                                        </span>
                                    </LocalizedLink>
                                </div>
                            </Deferred>
                        </CardContent>
                    </Card>

                    <!-- Recent Predictions -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Clock class="size-4 text-orange-500" />
                                {{ t('home.recentUpdates') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred data="recentPredictions">
                                <template #fallback>
                                    <div class="space-y-3">
                                        <div v-for="i in 5" :key="i" class="animate-pulse">
                                            <div class="h-8 bg-muted rounded"></div>
                                        </div>
                                    </div>
                                </template>
                                <div class="space-y-3">
                                    <LocalizedLink
                                        v-for="prediction in recentPredictions"
                                        :key="prediction.id"
                                        :href="`/assets/${prediction.asset.symbol}`"
                                        class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                    >
                                        <div>
                                            <span class="font-medium">{{ prediction.asset.symbol }}</span>
                                            <span v-if="prediction.asset.market" class="ms-1 text-xs text-muted-foreground">{{ prediction.asset.market.code }}</span>
                                        </div>
                                        <span class="text-xs text-muted-foreground">
                                            {{ prediction.horizonLabel }}
                                        </span>
                                    </LocalizedLink>
                                </div>
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
