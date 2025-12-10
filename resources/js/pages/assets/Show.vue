<script setup lang="ts">
import { computed } from 'vue';
import { Head, Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import GuestLayout from '@/layouts/GuestLayout.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import {
    ChevronLeft,
    ChevronRight,
    TrendingUp,
    TrendingDown,
    Clock,
    Building2,
    BarChart3,
    Activity,
    ArrowUpRight,
    ArrowDownRight,
    Gauge,
    LineChart,
} from 'lucide-vue-next';
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
import AssetPriceChart from '@/components/AssetPriceChart.vue';
import RecommendationCard from '@/components/RecommendationCard.vue';
import type {
    AssetDetailData,
    AssetPriceData,
    AssetPredictionData,
    AssetIndicatorsData,
    PredictionHistoryItem,
    PriceHistoryPoint,
    PredictionChartPoint,
    IndicatorHistoryPoint,
    Recommendation,
    Signal,
    PatternDetection,
    Anomaly,
} from '@/types';

const { t, locale } = useI18n();
const { formatGain, getConfidenceColor } = usePredictionFormatters();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
    asset: AssetDetailData;
    price: AssetPriceData | null;
    chartPeriod?: number;
    predictions?: AssetPredictionData[];
    indicators?: AssetIndicatorsData | null;
    priceHistory?: PriceHistoryPoint[];
    indicatorHistory?: IndicatorHistoryPoint[];
    predictionChartData?: PredictionChartPoint[];
    predictionHistory?: PredictionHistoryItem[];
    recommendation?: Recommendation | null;
    activeSignals?: Signal[];
    detectedPatterns?: PatternDetection | null;
    anomalies?: Anomaly[];
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
    chartPeriod: 30,
});

// Computed - use props directly
const asset = computed(() => props.asset);
const price = computed(() => props.price);
const predictions = computed(() => props.predictions ?? []);
const indicators = computed(() => props.indicators);
const priceHistory = computed(() => props.priceHistory ?? []);
const indicatorHistory = computed(() => props.indicatorHistory ?? []);
const predictionChartData = computed(() => props.predictionChartData ?? []);
const predictionHistory = computed(() => props.predictionHistory ?? []);
const recommendation = computed(() => props.recommendation ?? null);
const activeSignals = computed(() => props.activeSignals ?? []);
const detectedPatterns = computed(() => props.detectedPatterns ?? null);
const anomalies = computed(() => props.anomalies ?? []);

const priceChangeIsPositive = computed(() => {
    if (!price.value?.changePercent) return true;
    return parseFloat(price.value.changePercent) >= 0;
});

// Local helpers (not in composable)
const formatPrice = (value: number | null | undefined) => {
    if (value == null) return '-';
    return `${value.toFixed(2)} ${asset.value.currency}`;
};

const formatVolume = (volume?: string) => {
    if (!volume) return '-';
    const num = parseFloat(volume);
    if (num >= 1000000) return `${(num / 1000000).toFixed(2)}M`;
    if (num >= 1000) return `${(num / 1000).toFixed(2)}K`;
    return volume;
};

const getConfidenceBgColor = (confidence: number) => {
    if (confidence >= 85) return 'bg-green-100 dark:bg-green-900/30';
    if (confidence >= 70) return 'bg-yellow-100 dark:bg-yellow-900/30';
    return 'bg-red-100 dark:bg-red-900/30';
};

const formatDate = (dateString: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatShortDate = (dateString: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
    });
};

const getRsiSignal = (rsi?: number | null) => {
    if (rsi === null || rsi === undefined) return null;
    if (rsi >= 70) return { key: 'overbought', color: 'text-red-600 dark:text-red-400' };
    if (rsi <= 30) return { key: 'oversold', color: 'text-green-600 dark:text-green-400' };
    return { key: 'neutral', color: 'text-yellow-600 dark:text-yellow-400' };
};

const getMacdSignal = (macdLine?: number | null) => {
    if (macdLine === null || macdLine === undefined) return null;
    if (macdLine > 0) return { key: 'bullish', color: 'text-green-600 dark:text-green-400' };
    if (macdLine < 0) return { key: 'bearish', color: 'text-red-600 dark:text-red-400' };
    return { key: 'neutral', color: 'text-yellow-600 dark:text-yellow-400' };
};
</script>

<template>
    <Head :title="t('assetDetail.title', { symbol: asset.symbol })">
        <meta name="description" :content="t('meta.assetDetail', { symbol: asset.symbol, name: asset.name })">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister" :show-nav="false">
        <!-- Asset Header Section -->
        <section class="border-b border-border/40 bg-muted/30">
            <div class="mx-auto max-w-7xl px-4 py-8">
                <!-- Back Links -->
                <div class="flex flex-wrap items-center gap-4 mb-6">
                    <LocalizedLink
                        v-if="asset.market"
                        :href="`/markets/${asset.market.code}`"
                        class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                    >
                        <component :is="locale === 'ar' ? ChevronRight : ChevronLeft" class="size-4" />
                        {{ t('assetDetail.backToMarket') }}
                    </LocalizedLink>
                    <span v-if="asset.market && asset.sector" class="text-muted-foreground">|</span>
                    <LocalizedLink
                        v-if="asset.sector"
                        :href="`/sectors/${asset.sector.id}`"
                        class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                    >
                        {{ t('assetDetail.backToSector') }}
                    </LocalizedLink>
                    <span class="text-muted-foreground">|</span>
                    <LocalizedLink
                        href="/predictions"
                        class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                    >
                        {{ t('assetDetail.backToAll') }}
                    </LocalizedLink>
                </div>

                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <!-- Asset Info -->
                    <div class="flex items-start gap-4">
                        <div class="flex size-16 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <Building2 class="size-8" />
                        </div>
                        <div>
                            <div class="flex items-center gap-3">
                                <h1 class="text-2xl font-bold sm:text-3xl">
                                    {{ asset.symbol }}
                                </h1>
                                <span class="rounded-full bg-muted px-3 py-1 text-sm font-medium text-muted-foreground">
                                {{ t(`assetDetail.assetType.${asset.type}`) }}
                            </span>
                            </div>
                            <p class="mt-1 text-lg text-muted-foreground">
                                {{ asset.name }}
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                            <span v-if="asset.market" class="flex items-center gap-1">
                                <BarChart3 class="size-4" />
                                {{ asset.market.code }} - {{ asset.market.name }}
                            </span>
                                <span v-if="asset.sector">{{ asset.sector.name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Current Price -->
                    <div v-if="price" class="flex flex-col items-start lg:items-end">
                        <div class="flex items-baseline gap-3">
                            <span class="text-3xl font-bold">{{ formatPrice(price.last) }}</span>
                            <span
                                class="flex items-center gap-1 text-lg font-medium"
                                :class="priceChangeIsPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                            >
                            <ArrowUpRight v-if="priceChangeIsPositive" class="size-5" />
                            <ArrowDownRight v-else class="size-5" />
                            {{ price.changePercent }}%
                        </span>
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                            {{ t('assetDetail.price.lastUpdated') }}: {{ formatDate(price.updatedAt) }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Recommendation Section -->
        <section class="border-b border-border/40 bg-background">
            <div class="mx-auto max-w-7xl px-4 py-6">
                <Deferred data="recommendation">
                    <template #fallback>
                        <div class="animate-pulse">
                            <div class="h-32 rounded-lg bg-muted" />
                        </div>
                    </template>
                    <RecommendationCard
                        :recommendation="recommendation"
                        :signals="activeSignals"
                        :patterns="detectedPatterns"
                        :anomalies="anomalies"
                    />
                </Deferred>
            </div>
        </section>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Left Column - Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Price Details Card -->
                    <Card v-if="price">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Activity class="size-5 text-primary" />
                                {{ t('assetDetail.price.title') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div>
                                    <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.current') }}</p>
                                    <p class="text-lg font-semibold">{{ formatPrice(price.last) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.lastClose') }}</p>
                                    <p class="text-lg font-semibold">{{ formatPrice(price.previousClose) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.high') }}</p>
                                    <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ formatPrice(price.high) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.low') }}</p>
                                    <p class="text-lg font-semibold text-red-600 dark:text-red-400">{{ formatPrice(price.low) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-border">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.volume') }}</p>
                                        <p class="text-lg font-semibold">{{ formatVolume(price.volume) }}</p>
                                    </div>
                                    <div class="text-end">
                                        <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.change') }}</p>
                                        <p
                                            class="text-lg font-semibold"
                                            :class="priceChangeIsPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                        >
                                            {{ price.changePercent }}%
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Predictions Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <TrendingUp class="size-5 text-green-500" />
                                {{ t('assetDetail.prediction.title') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred data="predictions">
                                <template #fallback>
                                    <div class="space-y-4">
                                        <div v-for="i in 4" :key="i" class="animate-pulse rounded-lg border border-border p-4">
                                            <div class="h-6 w-20 rounded bg-muted mb-3" />
                                            <div class="h-8 w-32 rounded bg-muted" />
                                        </div>
                                    </div>
                                </template>

                                <div v-if="predictions.length > 0" class="space-y-4">
                                    <div
                                        v-for="(prediction, index) in predictions"
                                        :key="index"
                                        class="rounded-lg border border-border p-4 hover:bg-muted/30 transition-colors"
                                    >
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
                                                    {{ prediction.horizonLabel }}
                                                </span>
                                                    <span
                                                        :class="[getConfidenceBgColor(prediction.confidence), getConfidenceColor(prediction.confidence)]"
                                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                                    >
                                                    {{ prediction.confidence }}% {{ t('assetDetail.prediction.confidence') }}
                                                </span>
                                                </div>
                                                <div class="mt-3 flex items-baseline gap-2">
                                                    <span class="text-2xl font-bold">{{ formatPrice(prediction.predictedPrice) }}</span>
                                                    <span
                                                        class="flex items-center gap-0.5 text-lg font-medium"
                                                        :class="prediction.expectedGainPercent >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                                    >
                                                    <ArrowUpRight v-if="prediction.expectedGainPercent >= 0" class="size-4" />
                                                    <ArrowDownRight v-else class="size-4" />
                                                    {{ formatGain(prediction.expectedGainPercent) }}
                                                </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                                    <TrendingDown class="size-12 text-muted-foreground/50" />
                                    <p class="mt-4 text-muted-foreground">
                                        {{ t('assetDetail.prediction.noPredictions') }}
                                    </p>
                                </div>
                            </Deferred>
                        </CardContent>
                    </Card>

                    <!-- Price & Prediction Chart -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <LineChart class="size-5 text-blue-500" />
                                {{ t('assetDetail.chart.title') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Deferred data="priceHistory">
                                <template #fallback>
                                    <div class="space-y-4">
                                        <!-- Period selector skeleton -->
                                        <div class="flex justify-between items-center">
                                            <div class="h-4 w-24 rounded bg-muted animate-pulse" />
                                            <div class="flex gap-1">
                                                <div v-for="i in 4" :key="i" class="h-7 w-10 rounded bg-muted animate-pulse" />
                                            </div>
                                        </div>
                                        <!-- Chart skeleton -->
                                        <div class="h-64 sm:h-80 rounded-lg bg-muted/30 animate-pulse" />
                                        <!-- Legend skeleton -->
                                        <div class="flex justify-center gap-6">
                                            <div class="h-4 w-20 rounded bg-muted animate-pulse" />
                                            <div class="h-4 w-20 rounded bg-muted animate-pulse" />
                                            <div class="h-4 w-24 rounded bg-muted animate-pulse" />
                                        </div>
                                    </div>
                                </template>

                                <AssetPriceChart
                                    :price-history="priceHistory"
                                    :prediction-chart-data="predictionChartData"
                                    :indicator-history="indicatorHistory"
                                    :chart-period="props.chartPeriod"
                                    :currency="asset.currency"
                                    :asset-symbol="asset.symbol"
                                />
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column - Sidebar -->
                <div class="space-y-6">
                    <!-- Technical Indicators -->
                    <Deferred data="indicators">
                        <template #fallback>
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="flex items-center gap-2 text-base">
                                        <Gauge class="size-4 text-purple-500" />
                                        {{ t('assetDetail.indicators.title') }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <div v-for="i in 5" :key="i" class="flex items-center justify-between animate-pulse">
                                        <div class="h-4 w-16 rounded bg-muted" />
                                        <div class="h-4 w-12 rounded bg-muted" />
                                    </div>
                                </CardContent>
                            </Card>
                        </template>

                        <Card v-if="indicators">
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Gauge class="size-4 text-purple-500" />
                                    {{ t('assetDetail.indicators.title') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- RSI -->
                                <div v-if="indicators.rsi !== null" class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium">{{ t('assetDetail.indicators.rsi') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.rsiDesc') }}</p>
                                    </div>
                                    <div class="text-end">
                                        <p class="font-bold">{{ indicators.rsi?.toFixed(1) }}</p>
                                        <p v-if="getRsiSignal(indicators.rsi)" :class="getRsiSignal(indicators.rsi)?.color" class="text-xs font-medium">
                                            {{ t(`assetDetail.indicators.${getRsiSignal(indicators.rsi)?.key}`) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- MACD -->
                                <div v-if="indicators.macd?.line !== null" class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium">{{ t('assetDetail.indicators.macd') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.macdDesc') }}</p>
                                    </div>
                                    <div class="text-end">
                                        <p class="font-bold">{{ indicators.macd?.line?.toFixed(2) }}</p>
                                        <p v-if="getMacdSignal(indicators.macd?.line)" :class="getMacdSignal(indicators.macd?.line)?.color" class="text-xs font-medium">
                                            {{ t(`assetDetail.indicators.${getMacdSignal(indicators.macd?.line)?.key}`) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- EMA -->
                                <div v-if="indicators.ema !== null" class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium">{{ t('assetDetail.indicators.ema') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.emaDesc') }}</p>
                                    </div>
                                    <p class="font-bold">{{ indicators.ema?.toFixed(2) }}</p>
                                </div>

                                <!-- SMA -->
                                <div v-if="indicators.sma !== null" class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium">{{ t('assetDetail.indicators.sma') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.smaDesc') }}</p>
                                    </div>
                                    <p class="font-bold">{{ indicators.sma?.toFixed(2) }}</p>
                                </div>

                                <!-- ATR -->
                                <div v-if="indicators.atr !== null" class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium">{{ t('assetDetail.indicators.atr') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.atrDesc') }}</p>
                                    </div>
                                    <p class="font-bold">{{ indicators.atr?.toFixed(2) }}</p>
                                </div>
                            </CardContent>
                        </Card>
                    </Deferred>

                    <!-- Prediction History -->
                    <Deferred data="predictionHistory">
                        <template #fallback>
                            <Card>
                                <CardHeader class="pb-3">
                                    <CardTitle class="flex items-center gap-2 text-base">
                                        <Clock class="size-4 text-orange-500" />
                                        {{ t('assetDetail.history.title') }}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div class="space-y-3">
                                        <div v-for="i in 5" :key="i" class="flex items-center justify-between py-2 animate-pulse">
                                            <div class="h-4 w-20 rounded bg-muted" />
                                            <div class="h-4 w-12 rounded bg-muted" />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </template>

                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Clock class="size-4 text-orange-500" />
                                    {{ t('assetDetail.history.title') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div v-if="predictionHistory.length > 0" class="space-y-3">
                                    <div
                                        v-for="(prediction, index) in predictionHistory"
                                        :key="index"
                                        class="flex items-center justify-between py-2 border-b border-border last:border-0"
                                    >
                                        <div>
                                            <p class="font-medium">{{ formatPrice(prediction.predictedPrice) }}</p>
                                            <p class="text-xs text-muted-foreground">{{ formatShortDate(prediction.timestamp) }}</p>
                                        </div>
                                        <div class="text-end">
                                            <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium">
                                                {{ prediction.horizonLabel }}
                                            </span>
                                            <p :class="getConfidenceColor(prediction.confidence)" class="text-sm font-medium mt-1">
                                                {{ prediction.confidence }}%
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                                    <Clock class="size-8 text-muted-foreground/50" />
                                    <p class="mt-2 text-sm text-muted-foreground">
                                        {{ t('assetDetail.history.noPredictions') }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </Deferred>

                </div>
            </div>
        </div>
    </GuestLayout>
</template>
