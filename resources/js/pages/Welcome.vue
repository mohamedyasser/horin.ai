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
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { login, register, dashboard } from '@/routes';
import {
    Search,
    SlidersHorizontal,
    ChevronDown,
    TrendingUp,
    Clock,
    Target,
    ArrowUpRight,
    ArrowDownRight,
} from 'lucide-vue-next';
import type { Prediction, MarketCode, PredictionFilters } from '@/types';

const { t } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
}

withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Markets data
const markets = [
    { code: 'EGX' as MarketCode, name: 'Egypt', country: 'Egypt' },
    { code: 'TASI' as MarketCode, name: 'Saudi', country: 'Saudi Arabia' },
    { code: 'ADX' as MarketCode, name: 'Abu Dhabi', country: 'UAE' },
    { code: 'DFM' as MarketCode, name: 'Dubai', country: 'UAE' },
    { code: 'KW' as MarketCode, name: 'Kuwait', country: 'Kuwait' },
    { code: 'QA' as MarketCode, name: 'Qatar', country: 'Qatar' },
    { code: 'BH' as MarketCode, name: 'Bahrain', country: 'Bahrain' },
];

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
        symbol: '2222',
        name: 'Saudi Aramco',
        market: 'TASI',
        sector: 'Energy',
        last_price: 28.9,
        predicted_price: 32.1,
        gain_percent: 11.1,
        horizon: '1W',
        confidence: 92,
        currency: 'SAR',
        created_at: '2024-01-15T09:00:00Z',
        updated_at: '2024-01-15T09:00:00Z',
    },
    {
        id: 3,
        symbol: 'ETISALAT',
        name: 'Emirates Telecom',
        market: 'ADX',
        sector: 'Telecom',
        last_price: 24.8,
        predicted_price: 27.5,
        gain_percent: 10.9,
        horizon: '1M',
        confidence: 85,
        currency: 'AED',
        created_at: '2024-01-14T15:00:00Z',
        updated_at: '2024-01-14T15:00:00Z',
    },
    {
        id: 4,
        symbol: 'EMAAR',
        name: 'Emaar Properties',
        market: 'DFM',
        sector: 'Real Estate',
        last_price: 8.2,
        predicted_price: 9.8,
        gain_percent: 19.5,
        horizon: '3M',
        confidence: 78,
        currency: 'AED',
        created_at: '2024-01-14T12:00:00Z',
        updated_at: '2024-01-14T12:00:00Z',
    },
    {
        id: 5,
        symbol: 'NBK',
        name: 'National Bank of Kuwait',
        market: 'KW',
        sector: 'Banking',
        last_price: 1.05,
        predicted_price: 1.18,
        gain_percent: 12.4,
        horizon: '1M',
        confidence: 89,
        currency: 'KWD',
        created_at: '2024-01-13T14:00:00Z',
        updated_at: '2024-01-13T14:00:00Z',
    },
    {
        id: 6,
        symbol: 'QNBK',
        name: 'Qatar National Bank',
        market: 'QA',
        sector: 'Banking',
        last_price: 14.2,
        predicted_price: 15.9,
        gain_percent: 12.0,
        horizon: '1W',
        confidence: 91,
        currency: 'QAR',
        created_at: '2024-01-13T11:00:00Z',
        updated_at: '2024-01-13T11:00:00Z',
    },
];

// State
const searchQuery = ref('');
const selectedMarket = ref<MarketCode | null>(null);
const sortBy = ref<'gain' | 'confidence' | 'newest'>('gain');
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

    if (selectedMarket.value) {
        result = result.filter((p) => p.market === selectedMarket.value);
    }

    // Sort
    if (sortBy.value === 'gain') {
        result.sort((a, b) => b.gain_percent - a.gain_percent);
    } else if (sortBy.value === 'confidence') {
        result.sort((a, b) => b.confidence - a.confidence);
    } else {
        result.sort(
            (a, b) =>
                new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime()
        );
    }

    return result;
});

const topMovers = computed(() =>
    [...mockPredictions].sort((a, b) => b.gain_percent - a.gain_percent).slice(0, 5)
);

const highestConfidence = computed(() =>
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

const selectMarket = (market: MarketCode | null) => {
    selectedMarket.value = market;
};
</script>

<template>
    <Head :title="t('home.title')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-background">
        <!-- Header -->
        <header class="border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4">
                <Link href="/" class="flex items-center gap-2">
                    <div class="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <AppLogoIcon class="size-5 fill-current" />
                    </div>
                    <span class="text-lg font-semibold">Horin</span>
                </Link>

                <nav class="flex items-center gap-2">
                    <LanguageSwitcher />
                    <Link
                        v-if="canLogin"
                        :href="login()"
                        class="text-sm font-medium text-muted-foreground hover:text-foreground transition-colors px-3 py-2"
                    >
                        {{ t('common.login') }}
                    </Link>
                    <Button v-if="canRegister" as-child>
                        <Link :href="register()">{{ t('common.getStarted') }}</Link>
                    </Button>
                </nav>
            </div>
        </header>

        <main>
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
                        <Search class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
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
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            :variant="selectedMarket === null ? 'default' : 'outline'"
                            size="sm"
                            @click="selectMarket(null)"
                        >
                            {{ t('home.allMarkets') }}
                        </Button>
                        <Button
                            v-for="market in markets"
                            :key="market.code"
                            :variant="selectedMarket === market.code ? 'default' : 'outline'"
                            size="sm"
                            @click="selectMarket(market.code)"
                        >
                            {{ market.code }}
                        </Button>
                    </div>
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

                                <!-- Filter Button -->
                                <Dialog v-model:open="filterOpen">
                                    <DialogTrigger as-child>
                                        <Button variant="outline" size="sm">
                                            <SlidersHorizontal class="me-1 size-4" />
                                            {{ t('home.filters') }}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>{{ t('home.filterPredictions') }}</DialogTitle>
                                        </DialogHeader>
                                        <div class="grid gap-4 py-4">
                                            <div class="grid gap-2">
                                                <Label>{{ t('home.sector') }}</Label>
                                                <Input :placeholder="t('home.sectorPlaceholder')" />
                                            </div>
                                            <div class="grid grid-cols-2 gap-4">
                                                <div class="grid gap-2">
                                                    <Label>{{ t('home.minPrice') }}</Label>
                                                    <Input type="number" placeholder="0" />
                                                </div>
                                                <div class="grid gap-2">
                                                    <Label>{{ t('home.maxPrice') }}</Label>
                                                    <Input type="number" placeholder="1000" />
                                                </div>
                                            </div>
                                            <div class="grid gap-2">
                                                <Label>{{ t('home.horizon') }}</Label>
                                                <div class="flex gap-2">
                                                    <Button variant="outline" size="sm">1D</Button>
                                                    <Button variant="outline" size="sm">1W</Button>
                                                    <Button variant="outline" size="sm">1M</Button>
                                                    <Button variant="outline" size="sm">3M</Button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <Button variant="outline" @click="filterOpen = false">
                                                {{ t('common.clear') }}
                                            </Button>
                                            <Button @click="filterOpen = false">{{ t('common.apply') }}</Button>
                                        </div>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>

                        <!-- Table -->
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
                                                {{ t('home.table.last') }}
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
                                            v-for="prediction in filteredPredictions"
                                            :key="prediction.id"
                                            class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors"
                                        >
                                            <td class="px-4 py-3">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium">{{ prediction.symbol }}</span>
                                                    <span class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                        {{ prediction.market }}
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
                                    {{ t('home.noResults') }}
                                </p>
                            </div>
                        </div>

                        <!-- Pagination placeholder -->
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
                        <!-- Top Movers -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <TrendingUp class="size-4 text-green-500" />
                                    {{ t('home.topMovers') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="prediction in topMovers"
                                    :key="prediction.id"
                                    class="flex items-center justify-between"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.symbol }}</span>
                                        <span class="ms-1 text-xs text-muted-foreground">{{ prediction.market }}</span>
                                    </div>
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ formatGain(prediction.gain_percent) }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Highest Confidence -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Target class="size-4 text-blue-500" />
                                    {{ t('home.highestConfidence') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div
                                    v-for="prediction in highestConfidence"
                                    :key="prediction.id"
                                    class="flex items-center justify-between"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.symbol }}</span>
                                        <span class="ms-1 text-xs text-muted-foreground">{{ prediction.market }}</span>
                                    </div>
                                    <span :class="getConfidenceColor(prediction.confidence)" class="font-medium">
                                        {{ prediction.confidence }}%
                                    </span>
                                </div>
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
                            <CardContent class="space-y-3">
                                <div
                                    v-for="prediction in recentPredictions"
                                    :key="prediction.id"
                                    class="flex items-center justify-between"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.symbol }}</span>
                                        <span class="ms-1 text-xs text-muted-foreground">{{ prediction.market }}</span>
                                    </div>
                                    <span class="text-xs text-muted-foreground">
                                        {{ prediction.horizon }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-border/40 bg-muted/30">
            <div class="mx-auto max-w-7xl px-4 py-6">
                <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                    <p class="text-sm text-muted-foreground">
                        &copy; {{ new Date().getFullYear() }} Horin. {{ t('common.allRightsReserved') }}
                    </p>
                    <nav class="flex items-center gap-4">
                        <Link href="/about" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('common.about') }}
                        </Link>
                        <a href="mailto:contact@horin.com" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                            {{ t('common.contact') }}
                        </a>
                    </nav>
                </div>
            </div>
        </footer>
    </div>
</template>
