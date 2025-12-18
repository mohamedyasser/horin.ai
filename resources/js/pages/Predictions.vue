<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LocalizedLink from '@/components/LocalizedLink.vue';
import FilterButtonBar from '@/components/FilterButtonBar.vue';
import ClickableTableRow from '@/components/ClickableTableRow.vue';
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
    Target,
    ArrowUpRight,
    ArrowDownRight,
    Loader2,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { useServerSearch } from '@/composables/useServerSearch';
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
import { usePredictionStats } from '@/composables/usePredictionStats';
import type { PredictionListItem, PaginationMeta } from '@/types';

const { t, locale } = useI18n();
const { formatGain, getConfidenceColor } = usePredictionFormatters();

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
    };
    predictions?: {
        data: PredictionListItem[];
        meta: PaginationMeta;
    };
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
    preserveParams: ['market', 'sector', 'country'],
    only: ['predictions', 'filters'],
});

// State
const filterOpen = ref(false);
const selectedMarket = ref<string | null>(props.filters?.market ?? null);
const selectedSector = ref<string | null>(props.filters?.sector ?? null);
const selectedCountry = ref<string | null>(props.filters?.country ?? null);
const sortBy = ref<'gain' | 'confidence' | 'newest'>('confidence');

// Computed - options for searchable selects
const marketOptions = computed(() =>
    props.markets.map((m) => ({ value: m.code, label: m.name }))
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
        only: ['predictions', 'filters'],
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
const predictions = computed(() => props.predictions?.data ?? []);
const predictionsMeta = computed(() => props.predictions?.meta);

// Sort predictions client-side
const sortedPredictions = computed(() => {
    const result = [...predictions.value];

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

// Derived data for sidebar
const { topGainers, mostConfident } = usePredictionStats(predictions);

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
        only: ['predictions', 'filters'],
    });
};
</script>

<template>
    <Head :title="t('predictions.title')">
        <meta name="description" :content="t('meta.predictions')">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
            <!-- Hero Section -->
            <section class="border-b border-border/40 bg-muted/30">
                <div class="mx-auto max-w-7xl px-4 py-12 text-center">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ t('predictions.title') }}
                    </h1>
                    <p class="mt-3 text-lg text-muted-foreground">
                        {{ t('predictions.subtitle') }}
                    </p>

                    <!-- Search Bar -->
                    <div class="relative mx-auto mt-8 max-w-xl">
                        <Search v-if="!isSearching" class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                        <Loader2 v-else class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground animate-spin" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('predictions.searchPlaceholder')"
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
                            <h2 class="text-xl font-semibold">{{ t('predictions.allPredictions') }}</h2>
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
                                            {{ t('predictions.sortBy') }}
                                            <ChevronDown class="ms-1 size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="sortBy = 'gain'">
                                            {{ t('predictions.highestGain') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="sortBy = 'confidence'">
                                            {{ t('predictions.highestConfidence') }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="sortBy = 'newest'">
                                            {{ t('predictions.newest') }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </div>

                        <Deferred data="predictions">
                            <template #fallback>
                                <!-- Loading skeleton -->
                                <div class="rounded-lg border border-border">
                                    <div class="animate-pulse space-y-4 p-4">
                                        <div v-for="i in 10" :key="i" class="flex items-center gap-4">
                                            <div class="h-10 w-20 rounded bg-muted" />
                                            <div class="h-4 flex-1 rounded bg-muted" />
                                            <div class="h-4 w-16 rounded bg-muted" />
                                            <div class="h-4 w-16 rounded bg-muted" />
                                            <div class="h-4 w-16 rounded bg-muted" />
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
                                                    {{ t('predictions.table.symbol') }}
                                                </th>
                                                <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.name') }}
                                                </th>
                                                <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.market') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.lastPrice') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.predictedPrice') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.gainPercent') }}
                                                </th>
                                                <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.horizon') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.confidence') }}
                                                </th>
                                                <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.action') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <ClickableTableRow
                                                v-for="prediction in sortedPredictions"
                                                :key="prediction.id"
                                                :aria-label="`View details for ${prediction.asset.symbol} - ${prediction.asset.name}`"
                                                @click="router.visit(`/${locale}/assets/${prediction.asset.symbol}`)"
                                            >
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium">{{ prediction.asset.symbol }}</span>
                                                        <span
                                                            v-if="prediction.asset.sector"
                                                            class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground"
                                                        >
                                                            {{ prediction.asset.sector.name }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                                    {{ prediction.asset.name }}
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span
                                                        v-if="prediction.asset.market"
                                                        class="rounded bg-muted px-2 py-0.5 text-xs font-medium"
                                                    >
                                                        {{ prediction.asset.market.code }}
                                                    </span>
                                                </td>
                                                <td dir="ltr" class="px-4 py-3 text-end text-sm">
                                                    {{ prediction.asset.currentPrice?.toFixed(2) ?? '-' }}
                                                </td>
                                                <td dir="ltr" class="px-4 py-3 text-end text-sm font-medium">
                                                    {{ prediction.predictedPrice.toFixed(2) }}
                                                </td>
                                                <td class="px-4 py-3 text-end">
                                                    <span
                                                        dir="ltr"
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
                                                <td dir="ltr" class="px-4 py-3 text-end">
                                                    <span :class="getConfidenceColor(prediction.confidence)" class="font-medium">
                                                        {{ prediction.confidence }}%
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <Button
                                                        as-child
                                                        variant="ghost"
                                                        size="sm"
                                                        @click.stop
                                                    >
                                                        <LocalizedLink :href="`/assets/${prediction.asset.symbol}`">
                                                            {{ t('predictions.viewDetails') }}
                                                        </LocalizedLink>
                                                    </Button>
                                                </td>
                                            </ClickableTableRow>
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
                                        {{ t('predictions.noResults') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="predictionsMeta && predictionsMeta.lastPage > 1" class="mt-4 flex items-center justify-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="predictionsMeta.currentPage <= 1"
                                    @click="goToPage(predictionsMeta.currentPage - 1)"
                                >
                                    {{ t('common.previous') }}
                                </Button>
                                <span dir="ltr" class="text-sm text-muted-foreground">
                                    {{ predictionsMeta.currentPage }} / {{ predictionsMeta.lastPage }}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="predictionsMeta.currentPage >= predictionsMeta.lastPage"
                                    @click="goToPage(predictionsMeta.currentPage + 1)"
                                >
                                    {{ t('common.next') }}
                                </Button>
                            </div>
                        </Deferred>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Top Gainers -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <TrendingUp class="size-4 text-green-500" />
                                    {{ t('predictions.topGainers') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('predictions.topGainersDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <LocalizedLink
                                    v-for="prediction in topGainers"
                                    :key="prediction.id"
                                    :href="`/assets/${prediction.asset.symbol}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.asset.symbol }}</span>
                                        <span v-if="prediction.asset.market" class="ms-1 text-xs text-muted-foreground">
                                            {{ prediction.asset.market.code }}
                                        </span>
                                    </div>
                                    <span dir="ltr" class="font-medium text-green-600 dark:text-green-400">
                                        {{ formatGain(prediction.expectedGainPercent) }}
                                    </span>
                                </LocalizedLink>
                            </CardContent>
                        </Card>

                        <!-- Most Confident -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Target class="size-4 text-blue-500" />
                                    {{ t('predictions.mostConfident') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('predictions.mostConfidentDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <LocalizedLink
                                    v-for="prediction in mostConfident"
                                    :key="prediction.id"
                                    :href="`/assets/${prediction.asset.symbol}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.asset.symbol }}</span>
                                        <span v-if="prediction.asset.market" class="ms-1 text-xs text-muted-foreground">
                                            {{ prediction.asset.market.code }}
                                        </span>
                                    </div>
                                    <span dir="ltr" :class="getConfidenceColor(prediction.confidence)" class="font-medium">
                                        {{ prediction.confidence }}%
                                    </span>
                                </LocalizedLink>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
    </GuestLayout>
</template>
