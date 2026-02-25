<script setup lang="ts">
import AssetDisplay from '@/components/AssetDisplay.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useAssetStats } from '@/composables/useAssetStats';
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
import { useServerSearch } from '@/composables/useServerSearch';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { AssetListItem, MarketDetail, PaginationMeta } from '@/types';
import { Deferred, Head, router } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    ChevronLeft,
    ChevronRight,
    ExternalLink,
    Loader2,
    Search,
    Target,
    TrendingUp,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();
const { formatGain, getConfidenceColor, getStatusColor } =
    usePredictionFormatters();

interface Props {
    market: MarketDetail;
    canLogin: boolean;
    canRegister: boolean;
    filters?: {
        search?: string | null;
    };
    assets?: {
        data: AssetListItem[];
        meta: PaginationMeta;
    };
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Server-side search
const { searchQuery, isSearching } = useServerSearch({
    initialValue: props.filters?.search,
    preserveParams: ['page'],
});

// Computed - use props data directly (already filtered by server)
const assets = computed(() => props.assets?.data ?? []);
const assetsMeta = computed(() => props.assets?.meta);

// Derived data from assets
const {
    assetsWithPredictions,
    topGainers,
    mostConfident,
    calculateGainPercent,
} = useAssetStats(assets);
</script>

<template>
    <Head :title="t('marketDetail.title', { market: market.code })">
        <meta
            name="description"
            :content="
                t('meta.marketDetail', {
                    market: market.code,
                    marketName: market.name,
                })
            "
        />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <!-- Market Header Section -->
        <section class="pt-20 pb-8">
            <div class="mx-auto max-w-7xl px-6">
                <!-- Back Link -->
                <LocalizedLink
                    href="/markets"
                    class="mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <component
                        :is="locale === 'ar' ? ChevronRight : ChevronLeft"
                        class="size-4"
                    />
                    {{ t('marketDetail.backToMarkets') }}
                </LocalizedLink>

                <div
                    class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between"
                >
                    <!-- Market Info -->
                    <div class="flex items-start gap-4">
                        <div
                            class="flex size-16 items-center justify-center rounded-md bg-muted text-foreground"
                        >
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
                                    {{
                                        market.isOpen
                                            ? t('markets.open')
                                            : t('markets.closed')
                                    }}
                                </span>
                            </div>
                            <p class="mt-1 text-lg text-muted-foreground">
                                {{ market.name }}
                            </p>
                            <div
                                class="mt-3 flex flex-wrap items-center gap-4 text-sm text-muted-foreground"
                            >
                                <span v-if="market.country">{{
                                    market.country.name
                                }}</span>
                                <span v-if="market.openAt && market.closeAt"
                                    >{{ market.openAt }} -
                                    {{ market.closeAt }}</span
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats & Actions -->
                    <div
                        class="flex flex-col items-start gap-4 sm:flex-row sm:items-center lg:flex-col lg:items-end"
                    >
                        <div class="flex items-center gap-6">
                            <div class="text-center">
                                <p class="text-2xl font-bold tabular-nums">
                                    {{ market.assetCount }}
                                </p>
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ t('markets.assets') }}
                                </p>
                            </div>
                            <div class="text-center">
                                <p class="text-2xl font-bold tabular-nums">
                                    {{ market.predictionCount }}
                                </p>
                                <p
                                    class="text-xs tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ t('markets.predictions') }}
                                </p>
                            </div>
                        </div>
                        <Button
                            v-if="market.tvLink"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <a
                                :href="market.tvLink"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ t('marketDetail.tvLink') }}
                                <ExternalLink class="ms-1 size-4" />
                            </a>
                        </Button>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="relative mt-8 max-w-xl">
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
                        :placeholder="t('marketDetail.searchPlaceholder')"
                        class="h-12 ps-10 text-base"
                    />
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-6 py-8">
            <div class="grid gap-8 lg:grid-cols-4">
                <!-- Assets Table -->
                <div class="lg:col-span-3">
                    <Deferred data="assets">
                        <template #fallback>
                            <div class="rounded-lg border border-border">
                                <div class="space-y-4 p-4">
                                    <div
                                        v-for="i in 10"
                                        :key="i"
                                        class="animate-pulse"
                                    >
                                        <div
                                            class="h-16 rounded-lg bg-muted"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="rounded-lg border border-border">
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
                                                        'marketDetail.table.symbol',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-start text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t('marketDetail.table.name')
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-end text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        'marketDetail.table.lastPrice',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-end text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        'marketDetail.table.predictedPrice',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-center text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        'marketDetail.table.horizon',
                                                    )
                                                }}
                                            </th>
                                            <th
                                                class="px-4 py-3 text-end text-sm font-medium text-muted-foreground"
                                            >
                                                {{
                                                    t(
                                                        'marketDetail.table.confidence',
                                                    )
                                                }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="asset in assets"
                                            :key="asset.id"
                                            class="cursor-pointer border-b border-border transition-colors last:border-0 hover:bg-muted/30"
                                            @click="
                                                router.visit(
                                                    `/${locale}/assets/${asset.symbol}`,
                                                )
                                            "
                                        >
                                            <td class="px-4 py-3">
                                                <AssetDisplay
                                                    :symbol="asset.symbol"
                                                    :sector-name="
                                                        asset.sector?.name
                                                    "
                                                    :show-name="false"
                                                    :show-logo="false"
                                                    size="md"
                                                />
                                            </td>
                                            <td
                                                class="px-4 py-3 text-sm text-muted-foreground"
                                            >
                                                {{ asset.name }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-end text-sm tabular-nums"
                                            >
                                                <template
                                                    v-if="asset.latestPrice"
                                                >
                                                    {{
                                                        asset.latestPrice.last.toFixed(
                                                            2,
                                                        )
                                                    }}
                                                    <span
                                                        class="ms-1 text-xs"
                                                        :class="
                                                            parseFloat(
                                                                asset
                                                                    .latestPrice
                                                                    .pcp,
                                                            ) >= 0
                                                                ? 'text-gain'
                                                                : 'text-loss'
                                                        "
                                                    >
                                                        {{
                                                            asset.latestPrice
                                                                .pcp
                                                        }}%
                                                    </span>
                                                </template>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >-</span
                                                >
                                            </td>
                                            <td
                                                class="px-4 py-3 text-end text-sm font-medium tabular-nums"
                                            >
                                                <template
                                                    v-if="
                                                        asset.latestPrediction
                                                    "
                                                >
                                                    {{
                                                        asset.latestPrediction.predictedPrice.toFixed(
                                                            2,
                                                        )
                                                    }}
                                                </template>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >-</span
                                                >
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <span
                                                    v-if="
                                                        asset.latestPrediction
                                                    "
                                                    class="rounded-full bg-muted px-2 py-1 text-xs font-medium"
                                                >
                                                    {{
                                                        asset.latestPrediction
                                                            .horizonLabel
                                                    }}
                                                </span>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >-</span
                                                >
                                            </td>
                                            <td
                                                class="px-4 py-3 text-end tabular-nums"
                                            >
                                                <template
                                                    v-if="
                                                        asset.latestPrediction
                                                    "
                                                >
                                                    <span
                                                        :class="
                                                            getConfidenceColor(
                                                                asset
                                                                    .latestPrediction
                                                                    .confidence,
                                                            )
                                                        "
                                                        class="font-medium"
                                                    >
                                                        {{
                                                            asset
                                                                .latestPrediction
                                                                .confidence
                                                        }}%
                                                    </span>
                                                </template>
                                                <span
                                                    v-else
                                                    class="text-muted-foreground"
                                                    >-</span
                                                >
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="assets.length === 0"
                                class="flex flex-col items-center justify-center py-12 text-center"
                            >
                                <Search
                                    class="size-12 text-muted-foreground/50"
                                />
                                <p class="mt-4 text-muted-foreground">
                                    {{ t('marketDetail.noResults') }}
                                </p>
                            </div>
                        </div>
                    </Deferred>

                    <!-- Pagination -->
                    <div
                        v-if="assetsMeta && assetsMeta.lastPage > 1"
                        class="mt-4 flex items-center justify-center gap-2"
                    >
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="assetsMeta.currentPage === 1"
                            @click="
                                router.visit(
                                    `/${locale}/markets/${market.code}?page=${assetsMeta.currentPage - 1}`,
                                    { only: ['assets'] },
                                )
                            "
                        >
                            {{ t('common.previous') }}
                        </Button>
                        <span class="text-sm text-muted-foreground">
                            {{ assetsMeta.currentPage }} /
                            {{ assetsMeta.lastPage }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                assetsMeta.currentPage === assetsMeta.lastPage
                            "
                            @click="
                                router.visit(
                                    `/${locale}/markets/${market.code}?page=${assetsMeta.currentPage + 1}`,
                                    { only: ['assets'] },
                                )
                            "
                        >
                            {{ t('common.next') }}
                        </Button>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Market Statistics -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <BarChart3 class="size-4 text-foreground" />
                                {{ t('marketDetail.stats.title') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">{{
                                    t('marketDetail.stats.totalAssets')
                                }}</span>
                                <span class="font-medium tabular-nums">{{
                                    market.assetCount
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">{{
                                    t(
                                        'marketDetail.stats.assetsWithPredictions',
                                    )
                                }}</span>
                                <span class="font-medium tabular-nums">{{
                                    assetsWithPredictions.length
                                }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-muted-foreground">{{
                                    t('markets.predictions')
                                }}</span>
                                <span class="font-medium tabular-nums">{{
                                    market.predictionCount
                                }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Top Gainers -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <TrendingUp class="size-4 text-foreground" />
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
                                        <div
                                            v-for="i in 5"
                                            :key="i"
                                            class="h-6 animate-pulse rounded bg-muted"
                                        ></div>
                                    </div>
                                </template>
                                <LocalizedLink
                                    v-for="asset in topGainers"
                                    :key="asset.id"
                                    :href="`/assets/${asset.symbol}`"
                                    class="-mx-2 flex items-center justify-between rounded px-2 py-1 transition-colors hover:bg-muted/30"
                                >
                                    <AssetDisplay
                                        :symbol="asset.symbol"
                                        :sector-name="asset.sector?.name"
                                        :show-name="false"
                                        :show-logo="false"
                                        size="sm"
                                    />
                                    <span
                                        class="font-medium text-gain tabular-nums"
                                    >
                                        {{
                                            formatGain(
                                                calculateGainPercent(asset),
                                            )
                                        }}
                                    </span>
                                </LocalizedLink>
                                <p
                                    v-if="topGainers.length === 0"
                                    class="py-2 text-center text-sm text-muted-foreground"
                                >
                                    {{ t('common.noData') }}
                                </p>
                            </Deferred>
                        </CardContent>
                    </Card>

                    <!-- Most Confident -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle
                                class="flex items-center gap-2 text-base"
                            >
                                <Target class="size-4 text-foreground" />
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
                                        <div
                                            v-for="i in 5"
                                            :key="i"
                                            class="h-6 animate-pulse rounded bg-muted"
                                        ></div>
                                    </div>
                                </template>
                                <LocalizedLink
                                    v-for="asset in mostConfident"
                                    :key="asset.id"
                                    :href="`/assets/${asset.symbol}`"
                                    class="-mx-2 flex items-center justify-between rounded px-2 py-1 transition-colors hover:bg-muted/30"
                                >
                                    <AssetDisplay
                                        :symbol="asset.symbol"
                                        :sector-name="asset.sector?.name"
                                        :show-name="false"
                                        :show-logo="false"
                                        size="sm"
                                    />
                                    <span
                                        v-if="asset.latestPrediction"
                                        :class="
                                            getConfidenceColor(
                                                asset.latestPrediction
                                                    .confidence,
                                            )
                                        "
                                        class="font-medium tabular-nums"
                                    >
                                        {{ asset.latestPrediction.confidence }}%
                                    </span>
                                </LocalizedLink>
                                <p
                                    v-if="mostConfident.length === 0"
                                    class="py-2 text-center text-sm text-muted-foreground"
                                >
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
