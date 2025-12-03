<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LocalizedLink from '@/components/LocalizedLink.vue';
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
    TrendingUp,
    Clock,
    Target,
    ArrowUpRight,
    ArrowDownRight,
} from 'lucide-vue-next';
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
    canLogin: boolean;
    canRegister: boolean;
    stats: HomeStats;
    markets: MarketPreview[];
    sectors: SectorPreview[];
    featuredPredictions?: FeaturedPrediction[];
    topMovers?: TopMover[];
    recentPredictions?: RecentPrediction[];
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// State
const searchQuery = ref('');
const selectedMarket = ref<string | null>(null);
const sortBy = ref<'gain' | 'confidence' | 'newest'>('gain');
const filterOpen = ref(false);

// Computed - use props data directly
const markets = computed(() => props.markets);
const featuredPredictions = computed(() => props.featuredPredictions ?? []);
const topMovers = computed(() => props.topMovers ?? []);
const recentPredictions = computed(() => props.recentPredictions ?? []);

// Filter predictions client-side for search
const filteredPredictions = computed(() => {
    let result = [...featuredPredictions.value];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (p) =>
                p.asset.symbol.toLowerCase().includes(query) ||
                p.asset.name.toLowerCase().includes(query)
        );
    }

    if (selectedMarket.value) {
        result = result.filter((p) => p.asset.market?.code === selectedMarket.value);
    }

    // Sort
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

const selectMarket = (marketCode: string | null) => {
    selectedMarket.value = marketCode;
};
</script>

<template>
    <Head :title="t('home.title')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
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
                        :key="market.id"
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
                                v-if="filteredPredictions.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <Search class="size-12 text-muted-foreground/50" />
                                <p class="mt-4 text-muted-foreground">
                                    {{ t('home.noResults') }}
                                </p>
                            </div>
                        </div>
                    </Deferred>

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
