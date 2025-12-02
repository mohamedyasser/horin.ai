<script setup lang="ts">
import { ref, computed } from 'vue';
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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {
    Search,
    SlidersHorizontal,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    TrendingUp,
    Target,
    Clock,
    Building2,
    Globe,
    ExternalLink,
    ArrowUpRight,
    ArrowDownRight,
    BarChart3,
} from 'lucide-vue-next';
import type { Market, Prediction, MarketCode, PredictionHorizon } from '@/types';

const { t, locale } = useI18n();

interface Props {
    market: Market;
    canLogin: boolean;
    canRegister: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Mock market data (will be replaced by props.market)
const market = computed(() => props.market || {
    id: 1,
    code: 'EGX' as MarketCode,
    name: 'EGX',
    full_name: 'Egyptian Exchange',
    country: 'Egypt',
    timezone: 'Africa/Cairo',
    trading_hours: { open: '10:00', close: '14:30' },
    status: 'closed' as const,
    prediction_count: 156,
    tv_link: 'https://www.tradingview.com/markets/stocks-egypt/',
    asset_count: 220,
});

// Mock predictions data
const mockPredictions: Prediction[] = [
    {
        id: 1,
        symbol: 'COMI',
        name: 'Commercial International Bank',
        market: 'EGX',
        sector: 'Banking',
        last_price: 72.5,
        predicted_price: 85.2,
        gain_percent: 17.5,
        horizon: '1M',
        confidence: 87,
        currency: 'EGP',
        created_at: '2024-01-15T10:00:00Z',
        updated_at: '2024-01-15T10:00:00Z',
    },
    {
        id: 2,
        symbol: 'HRHO',
        name: 'Hermes Holding',
        market: 'EGX',
        sector: 'Banking',
        last_price: 25.4,
        predicted_price: 29.8,
        gain_percent: 17.3,
        horizon: '1W',
        confidence: 82,
        currency: 'EGP',
        created_at: '2024-01-14T09:00:00Z',
        updated_at: '2024-01-14T09:00:00Z',
    },
    {
        id: 3,
        symbol: 'EAST',
        name: 'Eastern Company',
        market: 'EGX',
        sector: 'Consumer',
        last_price: 18.9,
        predicted_price: 21.5,
        gain_percent: 13.8,
        horizon: '1M',
        confidence: 79,
        currency: 'EGP',
        created_at: '2024-01-13T15:00:00Z',
        updated_at: '2024-01-13T15:00:00Z',
    },
    {
        id: 4,
        symbol: 'ORWE',
        name: 'Oriental Weavers',
        market: 'EGX',
        sector: 'Industrial',
        last_price: 12.3,
        predicted_price: 13.9,
        gain_percent: 13.0,
        horizon: '3M',
        confidence: 75,
        currency: 'EGP',
        created_at: '2024-01-12T12:00:00Z',
        updated_at: '2024-01-12T12:00:00Z',
    },
    {
        id: 5,
        symbol: 'TMGH',
        name: 'Talaat Moustafa Group',
        market: 'EGX',
        sector: 'Real Estate',
        last_price: 45.6,
        predicted_price: 51.2,
        gain_percent: 12.3,
        horizon: '1M',
        confidence: 84,
        currency: 'EGP',
        created_at: '2024-01-11T14:00:00Z',
        updated_at: '2024-01-11T14:00:00Z',
    },
    {
        id: 6,
        symbol: 'ETEL',
        name: 'Telecom Egypt',
        market: 'EGX',
        sector: 'Telecom',
        last_price: 32.1,
        predicted_price: 35.8,
        gain_percent: 11.5,
        horizon: '1W',
        confidence: 88,
        currency: 'EGP',
        created_at: '2024-01-10T11:00:00Z',
        updated_at: '2024-01-10T11:00:00Z',
    },
];

// Sectors list
const sectors = ['Banking', 'Energy', 'Telecom', 'Real Estate', 'Healthcare', 'Industrial', 'Materials', 'Consumer', 'Technology', 'Utilities'];
const horizons: PredictionHorizon[] = ['1D', '1W', '1M', '3M'];

// State
const searchQuery = ref('');
const selectedSector = ref<string | null>(null);
const selectedHorizon = ref<PredictionHorizon | null>(null);
const sortBy = ref<'gain' | 'confidence' | 'newest' | 'alphabetical'>('gain');
const filterOpen = ref(false);

// Computed
const filteredPredictions = computed(() => {
    let result = [...mockPredictions];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (p) =>
                p.symbol.toLowerCase().includes(query) ||
                p.name.toLowerCase().includes(query)
        );
    }

    if (selectedSector.value) {
        result = result.filter((p) => p.sector === selectedSector.value);
    }

    if (selectedHorizon.value) {
        result = result.filter((p) => p.horizon === selectedHorizon.value);
    }

    // Sort
    if (sortBy.value === 'gain') {
        result.sort((a, b) => b.gain_percent - a.gain_percent);
    } else if (sortBy.value === 'confidence') {
        result.sort((a, b) => b.confidence - a.confidence);
    } else if (sortBy.value === 'newest') {
        result.sort(
            (a, b) =>
                new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
        );
    } else {
        result.sort((a, b) => a.symbol.localeCompare(b.symbol));
    }

    return result;
});

const topGainers = computed(() =>
    [...mockPredictions].sort((a, b) => b.gain_percent - a.gain_percent).slice(0, 5)
);

const mostConfident = computed(() =>
    [...mockPredictions].sort((a, b) => b.confidence - a.confidence).slice(0, 5)
);

const recentPredictions = computed(() =>
    [...mockPredictions]
        .sort(
            (a, b) =>
                new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
        )
        .slice(0, 5)
);

const marketStats = computed(() => ({
    totalAssets: 220,
    assetsWithPredictions: mockPredictions.length,
    avgConfidence: Math.round(
        mockPredictions.reduce((sum, p) => sum + p.confidence, 0) / mockPredictions.length
    ),
    highestGainer: topGainers.value[0],
    lastUpdated: mockPredictions[0]?.updated_at,
}));

// Helpers
const formatPrice = (price: number, currency: string) => {
    return `${price.toFixed(2)} ${currency}`;
};

const formatGain = (gain: number) => {
    const sign = gain >= 0 ? '+' : '';
    return `${sign}${gain.toFixed(1)}%`;
};

const getConfidenceColor = (confidence: number) => {
    if (confidence >= 85) return 'text-green-600 dark:text-green-400';
    if (confidence >= 70) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
};

const getStatusColor = (status: string) => {
    return status === 'open'
        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
        : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400';
};

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getMarketName = (code: MarketCode) => {
    return t(`markets.names.${code}`);
};

const getSectorKey = (sector: string): string => {
    const sectorMap: Record<string, string> = {
        'Banking': 'banking',
        'Energy': 'energy',
        'Telecom': 'telecom',
        'Real Estate': 'realEstate',
        'Healthcare': 'healthcare',
        'Industrial': 'industrial',
        'Materials': 'materials',
        'Consumer': 'consumer',
        'Technology': 'technology',
        'Utilities': 'utilities',
    };
    return sectorMap[sector] || sector.toLowerCase();
};

const getSectorName = (sector: string) => {
    const key = getSectorKey(sector);
    return t(`marketDetail.sectors.${key}`);
};

const getCountryKey = (country: string): string => {
    const countryMap: Record<string, string> = {
        'Egypt': 'egypt',
        'Saudi Arabia': 'saudiArabia',
        'UAE': 'uae',
        'Kuwait': 'kuwait',
        'Qatar': 'qatar',
        'Bahrain': 'bahrain',
    };
    return countryMap[country] || country.toLowerCase();
};

const getCountryName = (country: string) => {
    const key = getCountryKey(country);
    return t(`markets.countries.${key}`);
};

const clearFilters = () => {
    selectedSector.value = null;
    selectedHorizon.value = null;
    filterOpen.value = false;
};
</script>

<template>
    <Head :title="t('marketDetail.title', { market: market.code })">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <!-- Market Header Section -->
            <section class="border-b border-border/40 bg-muted/30">
                <div class="mx-auto max-w-7xl px-4 py-8">
                    <!-- Back Link -->
                    <Link
                        href="/markets"
                        class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors mb-6"
                    >
                        <component :is="locale === 'ar' ? ChevronRight : ChevronLeft" class="size-4" />
                        {{ t('marketDetail.backToMarkets') }}
                    </Link>

                    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <!-- Market Info -->
                        <div class="flex items-start gap-4">
                            <div class="flex size-16 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                <Building2 class="size-8" />
                            </div>
                            <div>
                                <div class="flex items-center gap-3">
                                    <h1 class="text-2xl font-bold sm:text-3xl">
                                        {{ market.code }}
                                    </h1>
                                    <span
                                        :class="getStatusColor(market.status)"
                                        class="rounded-full px-3 py-1 text-sm font-medium"
                                    >
                                        {{ market.status === 'open' ? t('markets.open') : t('markets.closed') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-lg text-muted-foreground">
                                    {{ getMarketName(market.code) }}
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <Globe class="size-4" />
                                        {{ getCountryName(market.country) }}
                                    </span>
                                    <span>{{ market.timezone }}</span>
                                    <span>{{ market.trading_hours.open }} - {{ market.trading_hours.close }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats & Actions -->
                        <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                            <div class="flex items-center gap-6">
                                <div class="text-center">
                                    <p class="text-2xl font-bold">{{ market.prediction_count }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('markets.predictions') }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold">{{ marketStats.avgConfidence }}%</p>
                                    <p class="text-xs text-muted-foreground">{{ t('marketDetail.stats.avgConfidence') }}</p>
                                </div>
                            </div>
                            <Button v-if="(market as any).tv_link" as-child variant="outline" size="sm">
                                <a :href="(market as any).tv_link" target="_blank" rel="noopener noreferrer">
                                    {{ t('marketDetail.tvLink') }}
                                    <ExternalLink class="ms-1 size-4" />
                                </a>
                            </Button>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <div class="relative mt-8 max-w-xl">
                        <Search class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('marketDetail.searchPlaceholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                </div>
            </section>

            <!-- Filter Bar -->
            <section class="border-b border-border/40">
                <div class="mx-auto max-w-7xl px-4 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <!-- Quick Filters -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Sector Filter -->
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        {{ selectedSector ? getSectorName(selectedSector) : t('marketDetail.allSectors') }}
                                        <ChevronDown class="ms-1 size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuItem @click="selectedSector = null">
                                        {{ t('marketDetail.allSectors') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-for="sector in sectors"
                                        :key="sector"
                                        @click="selectedSector = sector"
                                    >
                                        {{ getSectorName(sector) }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- Horizon Filter -->
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        {{ selectedHorizon || t('marketDetail.allHorizons') }}
                                        <ChevronDown class="ms-1 size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuItem @click="selectedHorizon = null">
                                        {{ t('marketDetail.allHorizons') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-for="horizon in horizons"
                                        :key="horizon"
                                        @click="selectedHorizon = horizon"
                                    >
                                        {{ horizon }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- More Filters -->
                            <Dialog v-model:open="filterOpen">
                                <DialogTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        <SlidersHorizontal class="me-1 size-4" />
                                        {{ t('marketDetail.filters') }}
                                    </Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>{{ t('marketDetail.filterPredictions') }}</DialogTitle>
                                    </DialogHeader>
                                    <div class="grid gap-4 py-4">
                                        <div class="grid gap-2">
                                            <Label>{{ t('marketDetail.confidenceRange') }}</Label>
                                            <div class="flex gap-2">
                                                <Input type="number" placeholder="0" min="0" max="100" />
                                                <span class="flex items-center">-</span>
                                                <Input type="number" placeholder="100" min="0" max="100" />
                                            </div>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>{{ t('marketDetail.expectedGain') }}</Label>
                                            <div class="flex gap-2">
                                                <Input type="number" placeholder="0" />
                                                <span class="flex items-center">-</span>
                                                <Input type="number" placeholder="100" />
                                            </div>
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>{{ t('marketDetail.priceRange') }}</Label>
                                            <div class="flex gap-2">
                                                <Input type="number" placeholder="0" />
                                                <span class="flex items-center">-</span>
                                                <Input type="number" placeholder="1000" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <Button variant="outline" @click="clearFilters">
                                            {{ t('common.clear') }}
                                        </Button>
                                        <Button @click="filterOpen = false">{{ t('common.apply') }}</Button>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <!-- Sort Dropdown -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ t('marketDetail.sortBy') }}
                                    <ChevronDown class="ms-1 size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="sortBy = 'gain'">
                                    {{ t('marketDetail.highestGain') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="sortBy = 'confidence'">
                                    {{ t('marketDetail.highestConfidence') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="sortBy = 'newest'">
                                    {{ t('marketDetail.newest') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="sortBy = 'alphabetical'">
                                    {{ t('marketDetail.alphabetical') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </section>

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-8">
                <div class="grid gap-8 lg:grid-cols-4">
                    <!-- Predictions Table -->
                    <div class="lg:col-span-3">
                        <div class="rounded-lg border border-border">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-border bg-muted/50">
                                            <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.symbol') }}
                                            </th>
                                            <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.name') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.lastPrice') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.predictedPrice') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.gainPercent') }}
                                            </th>
                                            <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.horizon') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.confidence') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.updatedAt') }}
                                            </th>
                                            <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                {{ t('marketDetail.table.action') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="prediction in filteredPredictions"
                                            :key="prediction.id"
                                            class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer"
                                            @click="router.visit(`/assets/${prediction.id}`)"
                                        >
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium">{{ prediction.symbol }}</span>
                                                    <span class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                        {{ getSectorName(prediction.sector) }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-muted-foreground">
                                                {{ prediction.name }}
                                            </td>
                                            <td class="px-4 py-3 text-end text-sm">
                                                {{ formatPrice(prediction.last_price, prediction.currency) }}
                                            </td>
                                            <td class="px-4 py-3 text-end text-sm font-medium">
                                                {{ formatPrice(prediction.predicted_price, prediction.currency) }}
                                            </td>
                                            <td class="px-4 py-3 text-end">
                                                <span
                                                    class="inline-flex items-center gap-0.5 font-medium"
                                                    :class="prediction.gain_percent >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                                >
                                                    <ArrowUpRight v-if="prediction.gain_percent >= 0" class="size-4" />
                                                    <ArrowDownRight v-else class="size-4" />
                                                    {{ formatGain(prediction.gain_percent) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span class="rounded-full bg-muted px-2 py-1 text-xs font-medium">
                                                    {{ prediction.horizon }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-end">
                                                <span :class="getConfidenceColor(prediction.confidence)" class="font-medium">
                                                    {{ prediction.confidence }}%
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-end text-sm text-muted-foreground">
                                                {{ formatDate(prediction.updated_at) }}
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <Button
                                                    as-child
                                                    variant="ghost"
                                                    size="sm"
                                                    @click.stop
                                                >
                                                    <Link :href="`/assets/${prediction.id}`">
                                                        {{ t('marketDetail.viewDetails') }}
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="filteredPredictions.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <Search class="size-12 text-muted-foreground/50" />
                                <p class="mt-4 text-muted-foreground">
                                    {{ t('marketDetail.noResults') }}
                                </p>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4 flex items-center justify-center gap-2">
                            <Button variant="outline" size="sm" disabled>{{ t('common.previous') }}</Button>
                            <Button variant="outline" size="sm" class="bg-primary text-primary-foreground">1</Button>
                            <Button variant="outline" size="sm">2</Button>
                            <Button variant="outline" size="sm">3</Button>
                            <Button variant="outline" size="sm">{{ t('common.next') }}</Button>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Market Statistics -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <BarChart3 class="size-4 text-primary" />
                                    {{ t('marketDetail.stats.title') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">{{ t('marketDetail.stats.totalAssets') }}</span>
                                    <span class="font-medium">{{ marketStats.totalAssets }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">{{ t('marketDetail.stats.assetsWithPredictions') }}</span>
                                    <span class="font-medium">{{ marketStats.assetsWithPredictions }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">{{ t('marketDetail.stats.avgConfidence') }}</span>
                                    <span class="font-medium">{{ marketStats.avgConfidence }}%</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">{{ t('marketDetail.stats.highestGainer') }}</span>
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ marketStats.highestGainer?.symbol }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Top Gainers -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <TrendingUp class="size-4 text-green-500" />
                                    {{ t('marketDetail.topGainers') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('marketDetail.topGainersDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <Link
                                    v-for="prediction in topGainers"
                                    :key="prediction.id"
                                    :href="`/assets/${prediction.id}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.symbol }}</span>
                                        <span class="ms-1 text-xs text-muted-foreground">
                                            {{ getSectorName(prediction.sector) }}
                                        </span>
                                    </div>
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ formatGain(prediction.gain_percent) }}
                                    </span>
                                </Link>
                            </CardContent>
                        </Card>

                        <!-- Most Confident -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Target class="size-4 text-blue-500" />
                                    {{ t('marketDetail.mostConfident') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('marketDetail.mostConfidentDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <Link
                                    v-for="prediction in mostConfident"
                                    :key="prediction.id"
                                    :href="`/assets/${prediction.id}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.symbol }}</span>
                                        <span class="ms-1 text-xs text-muted-foreground">
                                            {{ getSectorName(prediction.sector) }}
                                        </span>
                                    </div>
                                    <span :class="getConfidenceColor(prediction.confidence)" class="font-medium">
                                        {{ prediction.confidence }}%
                                    </span>
                                </Link>
                            </CardContent>
                        </Card>

                        <!-- Recent Predictions -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Clock class="size-4 text-orange-500" />
                                    {{ t('marketDetail.recentPredictions') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('marketDetail.recentPredictionsDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <Link
                                    v-for="prediction in recentPredictions"
                                    :key="prediction.id"
                                    :href="`/assets/${prediction.id}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.symbol }}</span>
                                        <span class="ms-1 text-xs text-muted-foreground">
                                            {{ prediction.horizon }}
                                        </span>
                                    </div>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatDate(prediction.updated_at) }}
                                    </span>
                                </Link>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
    </GuestLayout>
</template>
