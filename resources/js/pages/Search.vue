<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
    X,
    Clock,
    Loader2,
} from 'lucide-vue-next';
import type { MarketCode } from '@/types';

const { t } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Search result type
interface SearchResult {
    id: number;
    symbol: string;
    name: string;
    market: MarketCode;
    sector: string;
    last_price: number;
    predicted_price: number;
    gain_percent: number;
    horizon: string;
    confidence: number;
}

// Mock search results data
const mockResults: SearchResult[] = [
    {
        id: 1,
        symbol: 'COMI',
        name: 'Commercial International Bank',
        market: 'EGX',
        sector: 'Banking',
        last_price: 72.5,
        predicted_price: 85.2,
        gain_percent: 17.52,
        horizon: '1M',
        confidence: 87,
    },
    {
        id: 2,
        symbol: 'EAST',
        name: 'Eastern Company',
        market: 'EGX',
        sector: 'Consumer',
        last_price: 28.4,
        predicted_price: 32.1,
        gain_percent: 13.03,
        horizon: '1M',
        confidence: 82,
    },
    {
        id: 3,
        symbol: '2222',
        name: 'Saudi Aramco',
        market: 'TASI',
        sector: 'Energy',
        last_price: 32.15,
        predicted_price: 35.8,
        gain_percent: 11.35,
        horizon: '1M',
        confidence: 91,
    },
    {
        id: 4,
        symbol: '1120',
        name: 'Al Rajhi Bank',
        market: 'TASI',
        sector: 'Banking',
        last_price: 78.2,
        predicted_price: 86.5,
        gain_percent: 10.61,
        horizon: '1M',
        confidence: 85,
    },
    {
        id: 5,
        symbol: 'ADNOCDIST',
        name: 'ADNOC Distribution',
        market: 'ADX',
        sector: 'Energy',
        last_price: 4.52,
        predicted_price: 5.15,
        gain_percent: 13.94,
        horizon: '1M',
        confidence: 79,
    },
    {
        id: 6,
        symbol: 'EMAAR',
        name: 'Emaar Properties',
        market: 'DFM',
        sector: 'Real Estate',
        last_price: 8.75,
        predicted_price: 10.2,
        gain_percent: 16.57,
        horizon: '1M',
        confidence: 84,
    },
    {
        id: 7,
        symbol: 'NBK',
        name: 'National Bank of Kuwait',
        market: 'KW',
        sector: 'Banking',
        last_price: 1.05,
        predicted_price: 1.18,
        gain_percent: 12.38,
        horizon: '1M',
        confidence: 88,
    },
    {
        id: 8,
        symbol: 'QNBK',
        name: 'QNB Group',
        market: 'QA',
        sector: 'Banking',
        last_price: 15.8,
        predicted_price: 17.5,
        gain_percent: 10.76,
        horizon: '1M',
        confidence: 86,
    },
];

// State
const searchQuery = ref('');
const selectedMarket = ref<string | null>(null);
const selectedSector = ref<string | null>(null);
const selectedHorizon = ref<string | null>(null);
const isSearching = ref(false);
const hasSearched = ref(false);

// Recent searches (stored in memory for demo)
const recentSearches = ref<string[]>(['COMI', 'Aramco', 'Banking', 'EGX']);

// Markets and sectors for filters
const markets = ['EGX', 'TASI', 'ADX', 'DFM', 'KW', 'QA', 'BH'];
const sectors = ['Banking', 'Energy', 'Consumer', 'Real Estate', 'Telecom', 'Healthcare', 'Industrial'];
const horizons = ['1D', '1W', '1M', '3M'];

// Computed: filtered results
const searchResults = computed(() => {
    if (!searchQuery.value && !selectedMarket.value && !selectedSector.value) {
        return [];
    }

    let results = [...mockResults];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        results = results.filter(
            (r) =>
                r.symbol.toLowerCase().includes(query) ||
                r.name.toLowerCase().includes(query)
        );
    }

    if (selectedMarket.value) {
        results = results.filter((r) => r.market === selectedMarket.value);
    }

    if (selectedSector.value) {
        results = results.filter((r) => r.sector === selectedSector.value);
    }

    if (selectedHorizon.value) {
        results = results.filter((r) => r.horizon === selectedHorizon.value);
    }

    return results;
});

// Highlight matching text
const highlightMatch = (text: string, query: string) => {
    if (!query) return text;
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-900 rounded px-0.5">$1</mark>');
};

// Watch search query for instant search simulation
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchQuery, (newVal) => {
    if (newVal) {
        isSearching.value = true;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            isSearching.value = false;
            hasSearched.value = true;
        }, 300);
    } else {
        hasSearched.value = false;
    }
});

// Handle search from recent
const searchFromRecent = (query: string) => {
    searchQuery.value = query;
};

// Clear recent searches
const clearRecentSearches = () => {
    recentSearches.value = [];
};

// Navigate to asset detail
const goToAsset = (id: number) => {
    router.visit(`/assets/${id}`);
};

// Get market name
const getMarketName = (code: MarketCode) => {
    return t(`markets.names.${code}`);
};

// Get confidence color
const getConfidenceColor = (confidence: number) => {
    if (confidence >= 85) return 'text-green-600 dark:text-green-400';
    if (confidence >= 70) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
};

// Get gain color
const getGainColor = (gain: number) => {
    if (gain > 0) return 'text-green-600 dark:text-green-400';
    if (gain < 0) return 'text-red-600 dark:text-red-400';
    return 'text-muted-foreground';
};
</script>

<template>
    <Head :title="t('search.title')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
            <!-- Search Section -->
            <section class="border-b border-border/40 bg-muted/30">
                <div class="mx-auto max-w-4xl px-4 py-12">
                    <div class="text-center mb-8">
                        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                            {{ t('search.title') }}
                        </h1>
                        <p class="mt-3 text-lg text-muted-foreground">
                            {{ t('search.subtitle') }}
                        </p>
                    </div>

                    <!-- Search Bar -->
                    <div class="relative">
                        <Search class="absolute start-4 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('search.searchPlaceholder')"
                            class="h-14 ps-12 pe-12 text-lg rounded-xl shadow-sm"
                        />
                        <div v-if="isSearching" class="absolute end-4 top-1/2 -translate-y-1/2">
                            <Loader2 class="size-5 animate-spin text-muted-foreground" />
                        </div>
                        <button
                            v-else-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute end-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            <X class="size-5" />
                        </button>
                    </div>

                    <!-- Optional Filters -->
                    <div class="flex flex-wrap items-center justify-center gap-3 mt-6">
                        <!-- Market Filter -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ selectedMarket || t('search.allMarkets') }}
                                    <ChevronDown class="ms-1 size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="center">
                                <DropdownMenuItem @click="selectedMarket = null">
                                    {{ t('search.allMarkets') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-for="market in markets"
                                    :key="market"
                                    @click="selectedMarket = market"
                                >
                                    {{ market }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Sector Filter -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ selectedSector || t('search.allSectors') }}
                                    <ChevronDown class="ms-1 size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="center">
                                <DropdownMenuItem @click="selectedSector = null">
                                    {{ t('search.allSectors') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-for="sector in sectors"
                                    :key="sector"
                                    @click="selectedSector = sector"
                                >
                                    {{ sector }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <!-- Horizon Filter -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ selectedHorizon || t('search.allHorizons') }}
                                    <ChevronDown class="ms-1 size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="center">
                                <DropdownMenuItem @click="selectedHorizon = null">
                                    {{ t('search.allHorizons') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    v-for="horizon in horizons"
                                    :key="horizon"
                                    @click="selectedHorizon = horizon"
                                >
                                    {{ t(`assetDetail.horizons.${horizon}`) }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </section>

            <!-- Results Section -->
            <div class="mx-auto max-w-7xl px-4 py-8">
                <!-- Results Count -->
                <div v-if="hasSearched && searchResults.length > 0" class="mb-4 flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        {{ t('search.resultsCount', { count: searchResults.length }) }}
                    </p>
                </div>

                <!-- Results Table -->
                <div v-if="searchResults.length > 0" class="rounded-lg border border-border">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-border bg-muted/50">
                                    <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                        {{ t('search.table.symbol') }}
                                    </th>
                                    <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                        {{ t('search.table.name') }}
                                    </th>
                                    <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                        {{ t('search.table.market') }}
                                    </th>
                                    <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                        {{ t('search.table.lastPrice') }}
                                    </th>
                                    <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                        {{ t('search.table.predictedPrice') }}
                                    </th>
                                    <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                        {{ t('search.table.gainPercent') }}
                                    </th>
                                    <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                        {{ t('search.table.horizon') }}
                                    </th>
                                    <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                        {{ t('search.table.confidence') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="result in searchResults"
                                    :key="result.id"
                                    class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer"
                                    @click="goToAsset(result.id)"
                                >
                                    <td class="px-4 py-3 font-medium">
                                        <span v-html="highlightMatch(result.symbol, searchQuery)" />
                                    </td>
                                    <td class="px-4 py-3 text-sm text-muted-foreground">
                                        <span v-html="highlightMatch(result.name, searchQuery)" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs font-medium">
                                            {{ result.market }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end font-medium">
                                        {{ result.last_price.toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-3 text-end font-medium">
                                        {{ result.predicted_price.toFixed(2) }}
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <span :class="getGainColor(result.gain_percent)" class="font-semibold">
                                            +{{ result.gain_percent.toFixed(2) }}%
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-xs font-medium text-primary">
                                            {{ t(`assetDetail.horizons.${result.horizon}`) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <span :class="getConfidenceColor(result.confidence)" class="font-semibold">
                                            {{ result.confidence }}%
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State: No Results -->
                <div
                    v-else-if="hasSearched && searchResults.length === 0"
                    class="flex flex-col items-center justify-center py-16 text-center"
                >
                    <Search class="size-16 text-muted-foreground/30" />
                    <h3 class="mt-4 text-lg font-semibold">
                        {{ t('search.noResults') }}
                    </h3>
                    <p class="mt-2 text-muted-foreground">
                        {{ t('search.noResultsSuggestion') }}
                    </p>
                </div>

                <!-- Empty State: Start Typing -->
                <div
                    v-else-if="!hasSearched"
                    class="py-12"
                >
                    <!-- Start typing message -->
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <Search class="size-12 text-muted-foreground/30" />
                        <p class="mt-4 text-muted-foreground">
                            {{ t('search.startTyping') }}
                        </p>
                    </div>

                    <!-- Recent Searches -->
                    <div v-if="recentSearches.length > 0" class="mx-auto max-w-xl mt-8">
                        <Card>
                            <CardHeader class="pb-3">
                                <div class="flex items-center justify-between">
                                    <CardTitle class="flex items-center gap-2 text-base">
                                        <Clock class="size-4 text-muted-foreground" />
                                        {{ t('search.recentSearches') }}
                                    </CardTitle>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="clearRecentSearches"
                                    >
                                        {{ t('search.clearRecentSearches') }}
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="recent in recentSearches"
                                        :key="recent"
                                        variant="outline"
                                        size="sm"
                                        @click="searchFromRecent(recent)"
                                    >
                                        {{ recent }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
    </GuestLayout>
</template>
