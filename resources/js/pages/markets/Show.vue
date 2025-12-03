<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LocalizedLink from '@/components/LocalizedLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {
    Search,
    ChevronLeft,
    ChevronRight,
    TrendingUp,
    Target,
    Building2,
    ExternalLink,
    BarChart3,
} from 'lucide-vue-next';
import type { MarketDetail, AssetListItem, PaginationMeta } from '@/types';

const { t, locale } = useI18n();

interface Props {
    market: MarketDetail;
    canLogin: boolean;
    canRegister: boolean;
    assets?: {
        data: AssetListItem[];
        meta: PaginationMeta;
    };
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// State
const searchQuery = ref('');

// Computed - use props data directly
const assets = computed(() => props.assets?.data ?? []);
const assetsMeta = computed(() => props.assets?.meta);

// Filter assets client-side for search
const filteredAssets = computed(() => {
    let result = [...assets.value];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (a) =>
                a.symbol.toLowerCase().includes(query) ||
                a.name.toLowerCase().includes(query)
        );
    }

    return result;
});

// Derived data from assets
const assetsWithPredictions = computed(() =>
    assets.value.filter((a) => a.latestPrediction)
);

const topGainers = computed(() =>
    [...assetsWithPredictions.value]
        .filter((a) => a.latestPrediction)
        .sort((a, b) => {
            const gainA = ((a.latestPrediction!.predictedPrice - (a.latestPrice?.last ?? 0)) / (a.latestPrice?.last ?? 1)) * 100;
            const gainB = ((b.latestPrediction!.predictedPrice - (b.latestPrice?.last ?? 0)) / (b.latestPrice?.last ?? 1)) * 100;
            return gainB - gainA;
        })
        .slice(0, 5)
);

const mostConfident = computed(() =>
    [...assetsWithPredictions.value]
        .filter((a) => a.latestPrediction)
        .sort((a, b) => b.latestPrediction!.confidence - a.latestPrediction!.confidence)
        .slice(0, 5)
);

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

const getStatusColor = (isOpen: boolean) => {
    return isOpen
        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
        : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400';
};

const calculateGainPercent = (asset: AssetListItem) => {
    if (!asset.latestPrediction || !asset.latestPrice?.last) return 0;
    return ((asset.latestPrediction.predictedPrice - asset.latestPrice.last) / asset.latestPrice.last) * 100;
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
                    <LocalizedLink
                        href="/markets"
                        class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors mb-6"
                    >
                        <component :is="locale === 'ar' ? ChevronRight : ChevronLeft" class="size-4" />
                        {{ t('marketDetail.backToMarkets') }}
                    </LocalizedLink>

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
                                        :class="getStatusColor(market.isOpen)"
                                        class="rounded-full px-3 py-1 text-sm font-medium"
                                    >
                                        {{ market.isOpen ? t('markets.open') : t('markets.closed') }}
                                    </span>
                                </div>
                                <p class="mt-1 text-lg text-muted-foreground">
                                    {{ market.name }}
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                                    <span v-if="market.country">{{ market.country.name }}</span>
                                    <span v-if="market.openAt && market.closeAt">{{ market.openAt }} - {{ market.closeAt }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Stats & Actions -->
                        <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center lg:flex-col lg:items-end">
                            <div class="flex items-center gap-6">
                                <div class="text-center">
                                    <p class="text-2xl font-bold">{{ market.assetCount }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('markets.assets') }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-2xl font-bold">{{ market.predictionCount }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('markets.predictions') }}</p>
                                </div>
                            </div>
                            <Button v-if="market.tvLink" as-child variant="outline" size="sm">
                                <a :href="market.tvLink" target="_blank" rel="noopener noreferrer">
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

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-8">
                <div class="grid gap-8 lg:grid-cols-4">
                    <!-- Assets Table -->
                    <div class="lg:col-span-3">
                        <Deferred data="assets">
                            <template #fallback>
                                <div class="rounded-lg border border-border">
                                    <div class="space-y-4 p-4">
                                        <div v-for="i in 10" :key="i" class="animate-pulse">
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
                                                <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                    {{ t('marketDetail.table.horizon') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('marketDetail.table.confidence') }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="asset in filteredAssets"
                                                :key="asset.id"
                                                class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer"
                                                @click="router.visit(`/${locale}/assets/${asset.symbol}`)"
                                            >
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center gap-2">
                                                        <span class="font-medium">{{ asset.symbol }}</span>
                                                        <span v-if="asset.sector" class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                                            {{ asset.sector.name }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                                    {{ asset.name }}
                                                </td>
                                                <td class="px-4 py-3 text-end text-sm">
                                                    <template v-if="asset.latestPrice">
                                                        {{ asset.latestPrice.last.toFixed(2) }}
                                                        <span
                                                            class="ms-1 text-xs"
                                                            :class="parseFloat(asset.latestPrice.pcp) >= 0 ? 'text-green-600' : 'text-red-600'"
                                                        >
                                                            {{ asset.latestPrice.pcp }}%
                                                        </span>
                                                    </template>
                                                    <span v-else class="text-muted-foreground">-</span>
                                                </td>
                                                <td class="px-4 py-3 text-end text-sm font-medium">
                                                    <template v-if="asset.latestPrediction">
                                                        {{ asset.latestPrediction.predictedPrice.toFixed(2) }}
                                                    </template>
                                                    <span v-else class="text-muted-foreground">-</span>
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span v-if="asset.latestPrediction" class="rounded-full bg-muted px-2 py-1 text-xs font-medium">
                                                        {{ asset.latestPrediction.horizonLabel }}
                                                    </span>
                                                    <span v-else class="text-muted-foreground">-</span>
                                                </td>
                                                <td class="px-4 py-3 text-end">
                                                    <template v-if="asset.latestPrediction">
                                                        <span :class="getConfidenceColor(asset.latestPrediction.confidence)" class="font-medium">
                                                            {{ asset.latestPrediction.confidence }}%
                                                        </span>
                                                    </template>
                                                    <span v-else class="text-muted-foreground">-</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Empty State -->
                                <div
                                    v-if="filteredAssets.length === 0"
                                    class="flex flex-col items-center justify-center py-12 text-center"
                                >
                                    <Search class="size-12 text-muted-foreground/50" />
                                    <p class="mt-4 text-muted-foreground">
                                        {{ t('marketDetail.noResults') }}
                                    </p>
                                </div>
                            </div>
                        </Deferred>

                        <!-- Pagination -->
                        <div v-if="assetsMeta && assetsMeta.lastPage > 1" class="mt-4 flex items-center justify-center gap-2">
                            <Button variant="outline" size="sm" :disabled="assetsMeta.currentPage === 1">
                                {{ t('common.previous') }}
                            </Button>
                            <span class="text-sm text-muted-foreground">
                                {{ assetsMeta.currentPage }} / {{ assetsMeta.lastPage }}
                            </span>
                            <Button variant="outline" size="sm" :disabled="assetsMeta.currentPage === assetsMeta.lastPage">
                                {{ t('common.next') }}
                            </Button>
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
                                    <span class="font-medium">{{ market.assetCount }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">{{ t('marketDetail.stats.assetsWithPredictions') }}</span>
                                    <span class="font-medium">{{ assetsWithPredictions.length }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-muted-foreground">{{ t('markets.predictions') }}</span>
                                    <span class="font-medium">{{ market.predictionCount }}</span>
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
                                <Deferred data="assets">
                                    <template #fallback>
                                        <div class="space-y-2">
                                            <div v-for="i in 5" :key="i" class="animate-pulse h-6 bg-muted rounded"></div>
                                        </div>
                                    </template>
                                    <LocalizedLink
                                        v-for="asset in topGainers"
                                        :key="asset.id"
                                        :href="`/assets/${asset.symbol}`"
                                        class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                    >
                                        <div>
                                            <span class="font-medium">{{ asset.symbol }}</span>
                                            <span v-if="asset.sector" class="ms-1 text-xs text-muted-foreground">
                                                {{ asset.sector.name }}
                                            </span>
                                        </div>
                                        <span class="font-medium text-green-600 dark:text-green-400">
                                            {{ formatGain(calculateGainPercent(asset)) }}
                                        </span>
                                    </LocalizedLink>
                                    <p v-if="topGainers.length === 0" class="text-sm text-muted-foreground text-center py-2">
                                        {{ t('common.noData') }}
                                    </p>
                                </Deferred>
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
                                <Deferred data="assets">
                                    <template #fallback>
                                        <div class="space-y-2">
                                            <div v-for="i in 5" :key="i" class="animate-pulse h-6 bg-muted rounded"></div>
                                        </div>
                                    </template>
                                    <LocalizedLink
                                        v-for="asset in mostConfident"
                                        :key="asset.id"
                                        :href="`/assets/${asset.symbol}`"
                                        class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                    >
                                        <div>
                                            <span class="font-medium">{{ asset.symbol }}</span>
                                            <span v-if="asset.sector" class="ms-1 text-xs text-muted-foreground">
                                                {{ asset.sector.name }}
                                            </span>
                                        </div>
                                        <span v-if="asset.latestPrediction" :class="getConfidenceColor(asset.latestPrediction.confidence)" class="font-medium">
                                            {{ asset.latestPrediction.confidence }}%
                                        </span>
                                    </LocalizedLink>
                                    <p v-if="mostConfident.length === 0" class="text-sm text-muted-foreground text-center py-2">
                                        {{ t('common.noData') }}
                                    </p>
                                </Deferred>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
    </GuestLayout>
</template>
