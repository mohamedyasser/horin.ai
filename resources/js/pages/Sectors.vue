<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
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
    TrendingUp,
    Target,
    Clock,
    Layers,
    Building2,
    ArrowRight,
} from 'lucide-vue-next';
import type { Sector, SectorCode, MarketCode } from '@/types';

const { t, locale } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Mock sectors data
const mockSectors: Sector[] = [
    {
        id: 1,
        code: 'banking',
        name: 'Banking',
        description: 'Commercial and investment banks',
        asset_count: 45,
        prediction_count: 38,
        markets: [
            { market: 'EGX', count: 12 },
            { market: 'TASI', count: 15 },
            { market: 'ADX', count: 8 },
            { market: 'DFM', count: 10 },
        ],
        avg_gain_percent: 12.5,
        updated_at: '2024-01-15T10:00:00Z',
    },
    {
        id: 2,
        code: 'energy',
        name: 'Energy',
        description: 'Oil, gas, and renewable energy',
        asset_count: 32,
        prediction_count: 28,
        markets: [
            { market: 'TASI', count: 18 },
            { market: 'ADX', count: 8 },
            { market: 'QA', count: 6 },
        ],
        avg_gain_percent: 15.2,
        updated_at: '2024-01-15T09:30:00Z',
    },
    {
        id: 3,
        code: 'telecom',
        name: 'Telecom',
        description: 'Telecommunications and internet services',
        asset_count: 18,
        prediction_count: 15,
        markets: [
            { market: 'EGX', count: 4 },
            { market: 'TASI', count: 5 },
            { market: 'KW', count: 3 },
            { market: 'QA', count: 3 },
        ],
        avg_gain_percent: 8.7,
        updated_at: '2024-01-14T14:00:00Z',
    },
    {
        id: 4,
        code: 'realEstate',
        name: 'Real Estate',
        description: 'Property development and management',
        asset_count: 52,
        prediction_count: 42,
        markets: [
            { market: 'EGX', count: 15 },
            { market: 'DFM', count: 20 },
            { market: 'ADX', count: 10 },
            { market: 'TASI', count: 7 },
        ],
        avg_gain_percent: 11.3,
        updated_at: '2024-01-15T08:00:00Z',
    },
    {
        id: 5,
        code: 'healthcare',
        name: 'Healthcare',
        description: 'Hospitals, pharmaceuticals, and medical devices',
        asset_count: 22,
        prediction_count: 18,
        markets: [
            { market: 'EGX', count: 6 },
            { market: 'TASI', count: 10 },
            { market: 'ADX', count: 4 },
            { market: 'KW', count: 2 },
        ],
        avg_gain_percent: 9.8,
        updated_at: '2024-01-13T16:00:00Z',
    },
    {
        id: 6,
        code: 'industrial',
        name: 'Industrial',
        description: 'Manufacturing and industrial goods',
        asset_count: 38,
        prediction_count: 30,
        markets: [
            { market: 'EGX', count: 12 },
            { market: 'TASI', count: 14 },
            { market: 'KW', count: 6 },
            { market: 'BH', count: 6 },
        ],
        avg_gain_percent: 10.5,
        updated_at: '2024-01-14T11:00:00Z',
    },
    {
        id: 7,
        code: 'materials',
        name: 'Materials',
        description: 'Raw materials and basic resources',
        asset_count: 28,
        prediction_count: 22,
        markets: [
            { market: 'EGX', count: 8 },
            { market: 'TASI', count: 12 },
            { market: 'QA', count: 8 },
        ],
        avg_gain_percent: 13.1,
        updated_at: '2024-01-15T07:30:00Z',
    },
    {
        id: 8,
        code: 'consumer',
        name: 'Consumer',
        description: 'Consumer goods and retail',
        asset_count: 35,
        prediction_count: 28,
        markets: [
            { market: 'EGX', count: 10 },
            { market: 'TASI', count: 12 },
            { market: 'DFM', count: 8 },
            { market: 'KW', count: 5 },
        ],
        avg_gain_percent: 7.9,
        updated_at: '2024-01-14T15:00:00Z',
    },
    {
        id: 9,
        code: 'technology',
        name: 'Technology',
        description: 'Software, hardware, and IT services',
        asset_count: 15,
        prediction_count: 12,
        markets: [
            { market: 'TASI', count: 8 },
            { market: 'ADX', count: 4 },
            { market: 'DFM', count: 3 },
        ],
        avg_gain_percent: 18.4,
        updated_at: '2024-01-15T10:30:00Z',
    },
    {
        id: 10,
        code: 'utilities',
        name: 'Utilities',
        description: 'Electricity, water, and gas utilities',
        asset_count: 20,
        prediction_count: 16,
        markets: [
            { market: 'EGX', count: 5 },
            { market: 'TASI', count: 8 },
            { market: 'ADX', count: 4 },
            { market: 'QA', count: 3 },
        ],
        avg_gain_percent: 6.2,
        updated_at: '2024-01-13T12:00:00Z',
    },
];

const marketCodes: MarketCode[] = ['EGX', 'TASI', 'ADX', 'DFM', 'KW', 'QA', 'BH'];
const countries = ['Egypt', 'Saudi Arabia', 'UAE', 'Kuwait', 'Qatar', 'Bahrain'];

// State
const searchQuery = ref('');
const sortBy = ref<'alphabetical' | 'predictions'>('predictions');
const selectedMarket = ref<MarketCode | null>(null);
const selectedCountry = ref<string | null>(null);

// Computed
const filteredSectors = computed(() => {
    let result = [...mockSectors];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (s) =>
                t(`sectors.names.${s.code}`).toLowerCase().includes(query) ||
                t(`sectors.descriptions.${s.code}`).toLowerCase().includes(query)
        );
    }

    if (selectedMarket.value) {
        result = result.filter((s) =>
            s.markets.some((m) => m.market === selectedMarket.value)
        );
    }

    // Sort
    if (sortBy.value === 'predictions') {
        result.sort((a, b) => b.prediction_count - a.prediction_count);
    } else {
        result.sort((a, b) =>
            t(`sectors.names.${a.code}`).localeCompare(t(`sectors.names.${b.code}`))
        );
    }

    return result;
});

const topSectors = computed(() =>
    [...mockSectors].sort((a, b) => b.prediction_count - a.prediction_count).slice(0, 5)
);

const trendingSector = computed(() =>
    [...mockSectors].sort((a, b) => (b.avg_gain_percent || 0) - (a.avg_gain_percent || 0))[0]
);

const recentlyUpdated = computed(() =>
    [...mockSectors]
        .sort(
            (a, b) =>
                new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
        )
        .slice(0, 5)
);

// Helpers
const getSectorName = (code: SectorCode) => {
    return t(`sectors.names.${code}`);
};

const getSectorDescription = (code: SectorCode) => {
    return t(`sectors.descriptions.${code}`);
};

const getMarketName = (code: MarketCode) => {
    return t(`markets.names.${code}`);
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

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatGain = (gain: number) => {
    const sign = gain >= 0 ? '+' : '';
    return `${sign}${gain.toFixed(1)}%`;
};

const getSectorIcon = (code: SectorCode) => {
    // Return a consistent icon for visual variety
    const icons: Record<string, typeof Building2> = {
        banking: Building2,
        energy: TrendingUp,
        telecom: Layers,
        realEstate: Building2,
        healthcare: Target,
        industrial: Layers,
        materials: Layers,
        consumer: Layers,
        technology: Layers,
        utilities: Layers,
    };
    return icons[code] || Layers;
};
</script>

<template>
    <Head :title="t('sectors.title')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
            <!-- Hero Section -->
            <section class="border-b border-border/40 bg-muted/30">
                <div class="mx-auto max-w-7xl px-4 py-12 text-center">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ t('sectors.title') }}
                    </h1>
                    <p class="mt-3 text-lg text-muted-foreground">
                        {{ t('sectors.subtitle') }}
                    </p>

                    <!-- Search Bar -->
                    <div class="relative mx-auto mt-8 max-w-xl">
                        <Search class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('sectors.searchPlaceholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                </div>
            </section>

            <!-- Filter Bar -->
            <section class="border-b border-border/40">
                <div class="mx-auto max-w-7xl px-4 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <!-- Filters -->
                        <div class="flex flex-wrap items-center gap-2">
                            <!-- Market Filter -->
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        {{ selectedMarket ? getMarketName(selectedMarket) : t('sectors.allMarkets') }}
                                        <ChevronDown class="ms-1 size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuItem @click="selectedMarket = null">
                                        {{ t('sectors.allMarkets') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-for="market in marketCodes"
                                        :key="market"
                                        @click="selectedMarket = market"
                                    >
                                        {{ getMarketName(market) }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- Country Filter -->
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="outline" size="sm">
                                        {{ selectedCountry ? getCountryName(selectedCountry) : t('sectors.allCountries') }}
                                        <ChevronDown class="ms-1 size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuItem @click="selectedCountry = null">
                                        {{ t('sectors.allCountries') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-for="country in countries"
                                        :key="country"
                                        @click="selectedCountry = country"
                                    >
                                        {{ getCountryName(country) }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <!-- Sort Dropdown -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ t('sectors.sortBy') }}
                                    <ChevronDown class="ms-1 size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem @click="sortBy = 'predictions'">
                                    {{ t('sectors.sortPredictions') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="sortBy = 'alphabetical'">
                                    {{ t('sectors.sortAlphabetical') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </section>

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-8">
                <div class="grid gap-8 lg:grid-cols-4">
                    <!-- Sectors Grid -->
                    <div class="lg:col-span-3">
                        <!-- Table View -->
                        <div class="rounded-lg border border-border">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-border bg-muted/50">
                                            <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.sector') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.assets') }}
                                            </th>
                                            <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.predictions') }}
                                            </th>
                                            <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.markets') }}
                                            </th>
                                            <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                {{ t('sectors.table.action') }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="sector in filteredSectors"
                                            :key="sector.id"
                                            class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors"
                                        >
                                            <td class="px-4 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                        <Layers class="size-5" />
                                                    </div>
                                                    <div>
                                                        <p class="font-medium">{{ getSectorName(sector.code) }}</p>
                                                        <p class="text-sm text-muted-foreground">
                                                            {{ getSectorDescription(sector.code) }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-end">
                                                <span class="font-medium">{{ sector.asset_count }}</span>
                                                <span class="ms-1 text-sm text-muted-foreground">{{ t('sectors.assets') }}</span>
                                            </td>
                                            <td class="px-4 py-4 text-end">
                                                <span class="font-medium text-primary">{{ sector.prediction_count }}</span>
                                                <span class="ms-1 text-sm text-muted-foreground">{{ t('sectors.predictions') }}</span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="flex flex-wrap gap-1">
                                                    <span
                                                        v-for="m in sector.markets.slice(0, 4)"
                                                        :key="m.market"
                                                        class="rounded bg-muted px-2 py-0.5 text-xs font-medium"
                                                    >
                                                        {{ m.market }}: {{ m.count }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 text-center">
                                                <Button as-child variant="ghost" size="sm">
                                                    <Link :href="`/sectors/${sector.id}`">
                                                        {{ t('sectors.viewPredictions') }}
                                                        <ArrowRight class="ms-1 size-4" />
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="filteredSectors.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <Search class="size-12 text-muted-foreground/50" />
                                <p class="mt-4 text-muted-foreground">
                                    {{ t('sectors.noResults') }}
                                </p>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4 flex items-center justify-center gap-2">
                            <Button variant="outline" size="sm" disabled>{{ t('common.previous') }}</Button>
                            <Button variant="outline" size="sm" class="bg-primary text-primary-foreground">1</Button>
                            <Button variant="outline" size="sm">{{ t('common.next') }}</Button>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Top Sectors -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <TrendingUp class="size-4 text-green-500" />
                                    {{ t('sectors.topSectors') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('sectors.topSectorsDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <Link
                                    v-for="sector in topSectors"
                                    :key="sector.id"
                                    :href="`/sectors/${sector.id}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <span class="font-medium">{{ getSectorName(sector.code) }}</span>
                                    <span class="text-sm text-muted-foreground">
                                        {{ sector.prediction_count }} {{ t('sectors.predictions') }}
                                    </span>
                                </Link>
                            </CardContent>
                        </Card>

                        <!-- Trending Sector -->
                        <Card v-if="trendingSector">
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Target class="size-4 text-blue-500" />
                                    {{ t('sectors.trendingSector') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('sectors.trendingSectorDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent>
                                <Link
                                    :href="`/sectors/${trendingSector.id}`"
                                    class="block hover:bg-muted/30 -mx-2 px-2 py-2 rounded transition-colors"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex size-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                                <Layers class="size-5" />
                                            </div>
                                            <div>
                                                <p class="font-medium">{{ getSectorName(trendingSector.code) }}</p>
                                                <p class="text-sm text-muted-foreground">
                                                    {{ trendingSector.prediction_count }} {{ t('sectors.predictions') }}
                                                </p>
                                            </div>
                                        </div>
                                        <span class="font-medium text-green-600 dark:text-green-400">
                                            {{ formatGain(trendingSector.avg_gain_percent || 0) }}
                                        </span>
                                    </div>
                                </Link>
                            </CardContent>
                        </Card>

                        <!-- Recently Updated -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Clock class="size-4 text-orange-500" />
                                    {{ t('sectors.recentlyUpdated') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('sectors.recentlyUpdatedDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <Link
                                    v-for="sector in recentlyUpdated"
                                    :key="sector.id"
                                    :href="`/sectors/${sector.id}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <span class="font-medium">{{ getSectorName(sector.code) }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ formatDate(sector.updated_at) }}
                                    </span>
                                </Link>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
    </GuestLayout>
</template>
