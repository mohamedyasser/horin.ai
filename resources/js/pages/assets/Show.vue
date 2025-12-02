<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {
    ChevronLeft,
    ChevronRight,
    TrendingUp,
    TrendingDown,
    Target,
    Clock,
    Building2,
    ExternalLink,
    BarChart3,
    Activity,
    ArrowUpRight,
    ArrowDownRight,
    Gauge,
    LineChart,
} from 'lucide-vue-next';
import type { AssetDetail, AssetPrediction, MarketCode, PredictionHorizon } from '@/types';

const { t, locale } = useI18n();

interface Props {
    asset: AssetDetail;
    canLogin: boolean;
    canRegister: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Mock asset data (will be replaced by props.asset)
const asset = computed<AssetDetail>(() => props.asset || {
    id: 1,
    symbol: 'COMI',
    name: 'Commercial International Bank',
    market: 'EGX' as MarketCode,
    sector: 'Banking',
    country: 'Egypt',
    asset_type: 'stock' as const,
    currency: 'EGP',
    last_updated: '2024-01-15T10:00:00Z',
    price: {
        last: 72.5,
        last_close: 71.2,
        change_percent: 1.83,
        high: 73.1,
        low: 71.0,
        volume: 1250000,
        updated_at: '2024-01-15T14:30:00Z',
    },
    predictions: [
        {
            id: 1,
            predicted_price: 75.8,
            horizon: '1D' as PredictionHorizon,
            confidence: 82,
            expected_gain_percent: 4.55,
            upper_bound: 77.2,
            lower_bound: 74.4,
            timestamp: '2024-01-15T10:00:00Z',
        },
        {
            id: 2,
            predicted_price: 79.5,
            horizon: '1W' as PredictionHorizon,
            confidence: 78,
            expected_gain_percent: 9.66,
            upper_bound: 82.1,
            lower_bound: 76.9,
            timestamp: '2024-01-15T10:00:00Z',
        },
        {
            id: 3,
            predicted_price: 85.2,
            horizon: '1M' as PredictionHorizon,
            confidence: 87,
            expected_gain_percent: 17.52,
            upper_bound: 89.5,
            lower_bound: 80.9,
            timestamp: '2024-01-15T10:00:00Z',
        },
        {
            id: 4,
            predicted_price: 92.0,
            horizon: '3M' as PredictionHorizon,
            confidence: 72,
            expected_gain_percent: 26.90,
            upper_bound: 98.5,
            lower_bound: 85.5,
            timestamp: '2024-01-15T10:00:00Z',
        },
    ],
    indicators: {
        rsi: 58.5,
        macd: 1.25,
        ema: 71.8,
        sma: 70.5,
        atr: 1.85,
    },
    prediction_history: [
        {
            id: 101,
            predicted_price: 84.5,
            horizon: '1M' as PredictionHorizon,
            confidence: 85,
            expected_gain_percent: 16.5,
            timestamp: '2024-01-14T10:00:00Z',
        },
        {
            id: 102,
            predicted_price: 83.2,
            horizon: '1M' as PredictionHorizon,
            confidence: 84,
            expected_gain_percent: 15.2,
            timestamp: '2024-01-13T10:00:00Z',
        },
        {
            id: 103,
            predicted_price: 82.8,
            horizon: '1M' as PredictionHorizon,
            confidence: 86,
            expected_gain_percent: 14.8,
            timestamp: '2024-01-12T10:00:00Z',
        },
        {
            id: 104,
            predicted_price: 81.5,
            horizon: '1W' as PredictionHorizon,
            confidence: 80,
            expected_gain_percent: 12.4,
            timestamp: '2024-01-11T10:00:00Z',
        },
        {
            id: 105,
            predicted_price: 80.2,
            horizon: '1W' as PredictionHorizon,
            confidence: 79,
            expected_gain_percent: 10.6,
            timestamp: '2024-01-10T10:00:00Z',
        },
    ],
});

// Computed
const primaryPrediction = computed(() => {
    // Return the 1M prediction as primary, or the first available
    return asset.value.predictions.find(p => p.horizon === '1M') || asset.value.predictions[0];
});

const priceChangeIsPositive = computed(() => asset.value.price.change_percent >= 0);

// Helpers
const formatPrice = (price: number) => {
    return `${price.toFixed(2)} ${asset.value.currency}`;
};

const formatGain = (gain: number) => {
    const sign = gain >= 0 ? '+' : '';
    return `${sign}${gain.toFixed(2)}%`;
};

const formatVolume = (volume?: number) => {
    if (!volume) return '-';
    if (volume >= 1000000) return `${(volume / 1000000).toFixed(2)}M`;
    if (volume >= 1000) return `${(volume / 1000).toFixed(2)}K`;
    return volume.toString();
};

const getConfidenceColor = (confidence: number) => {
    if (confidence >= 85) return 'text-green-600 dark:text-green-400';
    if (confidence >= 70) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
};

const getConfidenceBgColor = (confidence: number) => {
    if (confidence >= 85) return 'bg-green-100 dark:bg-green-900/30';
    if (confidence >= 70) return 'bg-yellow-100 dark:bg-yellow-900/30';
    return 'bg-red-100 dark:bg-red-900/30';
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

const formatShortDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
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

const getRsiSignal = (rsi?: number) => {
    if (!rsi) return null;
    if (rsi >= 70) return { key: 'overbought', color: 'text-red-600 dark:text-red-400' };
    if (rsi <= 30) return { key: 'oversold', color: 'text-green-600 dark:text-green-400' };
    return { key: 'neutral', color: 'text-yellow-600 dark:text-yellow-400' };
};

const getMacdSignal = (macd?: number) => {
    if (!macd) return null;
    if (macd > 0) return { key: 'bullish', color: 'text-green-600 dark:text-green-400' };
    if (macd < 0) return { key: 'bearish', color: 'text-red-600 dark:text-red-400' };
    return { key: 'neutral', color: 'text-yellow-600 dark:text-yellow-400' };
};
</script>

<template>
    <Head :title="t('assetDetail.title', { symbol: asset.symbol })">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister" :show-nav="false">
        <!-- Asset Header Section -->
        <section class="border-b border-border/40 bg-muted/30">
        <div class="mx-auto max-w-7xl px-4 py-8">
            <!-- Back Links -->
            <div class="flex flex-wrap items-center gap-4 mb-6">
                <Link
                    :href="`/markets/${asset.market === 'EGX' ? 1 : 2}`"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    <component :is="locale === 'ar' ? ChevronRight : ChevronLeft" class="size-4" />
                    {{ t('assetDetail.backToMarket') }}
                </Link>
                <span class="text-muted-foreground">|</span>
                <Link
                    :href="`/sectors/${getSectorKey(asset.sector)}`"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    {{ t('assetDetail.backToSector') }}
                </Link>
                <span class="text-muted-foreground">|</span>
                <Link
                    href="/predictions"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                    {{ t('assetDetail.backToAll') }}
                </Link>
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
                                {{ t(`assetDetail.assetType.${asset.asset_type}`) }}
                            </span>
                        </div>
                        <p class="mt-1 text-lg text-muted-foreground">
                            {{ asset.name }}
                        </p>
                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                            <span class="flex items-center gap-1">
                                <BarChart3 class="size-4" />
                                {{ asset.market }} - {{ getMarketName(asset.market) }}
                            </span>
                            <span>{{ getSectorName(asset.sector) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Current Price -->
                <div class="flex flex-col items-start lg:items-end">
                    <div class="flex items-baseline gap-3">
                        <span class="text-3xl font-bold">{{ formatPrice(asset.price.last) }}</span>
                        <span
                            class="flex items-center gap-1 text-lg font-medium"
                            :class="priceChangeIsPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                        >
                            <ArrowUpRight v-if="priceChangeIsPositive" class="size-5" />
                            <ArrowDownRight v-else class="size-5" />
                            {{ formatGain(asset.price.change_percent) }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">
                        {{ t('assetDetail.price.lastUpdated') }}: {{ formatDate(asset.price.updated_at) }}
                    </p>
                </div>
            </div>
        </div>
        </section>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-4 py-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Left Column - Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Price Details Card -->
                <Card>
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
                                <p class="text-lg font-semibold">{{ formatPrice(asset.price.last) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.lastClose') }}</p>
                                <p class="text-lg font-semibold">{{ formatPrice(asset.price.last_close) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.high') }}</p>
                                <p class="text-lg font-semibold text-green-600 dark:text-green-400">{{ formatPrice(asset.price.high) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.low') }}</p>
                                <p class="text-lg font-semibold text-red-600 dark:text-red-400">{{ formatPrice(asset.price.low) }}</p>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-border">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.volume') }}</p>
                                    <p class="text-lg font-semibold">{{ formatVolume(asset.price.volume) }}</p>
                                </div>
                                <div class="text-end">
                                    <p class="text-sm text-muted-foreground">{{ t('assetDetail.price.change') }}</p>
                                    <p
                                        class="text-lg font-semibold"
                                        :class="priceChangeIsPositive ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                    >
                                        {{ formatGain(asset.price.change_percent) }}
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
                            <div v-if="asset.predictions.length > 0" class="space-y-4">
                                <div
                                    v-for="prediction in asset.predictions"
                                    :key="prediction.id"
                                    class="rounded-lg border border-border p-4 hover:bg-muted/30 transition-colors"
                                >
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
                                                    {{ t(`assetDetail.horizons.${prediction.horizon}`) }}
                                                </span>
                                                <span
                                                    :class="[getConfidenceBgColor(prediction.confidence), getConfidenceColor(prediction.confidence)]"
                                                    class="rounded-full px-2.5 py-1 text-xs font-medium"
                                                >
                                                    {{ prediction.confidence }}% {{ t('assetDetail.prediction.confidence') }}
                                                </span>
                                            </div>
                                            <div class="mt-3 flex items-baseline gap-2">
                                                <span class="text-2xl font-bold">{{ formatPrice(prediction.predicted_price) }}</span>
                                                <span
                                                    class="flex items-center gap-0.5 text-lg font-medium"
                                                    :class="prediction.expected_gain_percent >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                                                >
                                                    <ArrowUpRight v-if="prediction.expected_gain_percent >= 0" class="size-4" />
                                                    <ArrowDownRight v-else class="size-4" />
                                                    {{ formatGain(prediction.expected_gain_percent) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="prediction.upper_bound && prediction.lower_bound" class="mt-3 pt-3 border-t border-border">
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p class="text-muted-foreground">{{ t('assetDetail.prediction.upperBound') }}</p>
                                                <p class="font-medium text-green-600 dark:text-green-400">{{ formatPrice(prediction.upper_bound) }}</p>
                                            </div>
                                            <div>
                                                <p class="text-muted-foreground">{{ t('assetDetail.prediction.lowerBound') }}</p>
                                                <p class="font-medium text-red-600 dark:text-red-400">{{ formatPrice(prediction.lower_bound) }}</p>
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
                        </CardContent>
                    </Card>

                    <!-- Chart Placeholder -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <LineChart class="size-5 text-blue-500" />
                                {{ t('assetDetail.chart.title') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-center h-64 rounded-lg bg-muted/30 border border-dashed border-border">
                                <div class="text-center text-muted-foreground">
                                    <LineChart class="size-12 mx-auto mb-2 opacity-50" />
                                    <p>{{ t('assetDetail.chart.historical') }} + {{ t('assetDetail.chart.predicted') }}</p>
                                    <p class="text-sm">{{ t('assetDetail.chart.confidenceBand') }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right Column - Sidebar -->
                <div class="space-y-6">
                    <!-- Technical Indicators -->
                    <Card v-if="asset.indicators">
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Gauge class="size-4 text-purple-500" />
                                {{ t('assetDetail.indicators.title') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- RSI -->
                            <div v-if="asset.indicators.rsi" class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ t('assetDetail.indicators.rsi') }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.rsiDesc') }}</p>
                                </div>
                                <div class="text-end">
                                    <p class="font-bold">{{ asset.indicators.rsi.toFixed(1) }}</p>
                                    <p v-if="getRsiSignal(asset.indicators.rsi)" :class="getRsiSignal(asset.indicators.rsi)?.color" class="text-xs font-medium">
                                        {{ t(`assetDetail.indicators.${getRsiSignal(asset.indicators.rsi)?.key}`) }}
                                    </p>
                                </div>
                            </div>

                            <!-- MACD -->
                            <div v-if="asset.indicators.macd !== undefined" class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ t('assetDetail.indicators.macd') }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.macdDesc') }}</p>
                                </div>
                                <div class="text-end">
                                    <p class="font-bold">{{ asset.indicators.macd.toFixed(2) }}</p>
                                    <p v-if="getMacdSignal(asset.indicators.macd)" :class="getMacdSignal(asset.indicators.macd)?.color" class="text-xs font-medium">
                                        {{ t(`assetDetail.indicators.${getMacdSignal(asset.indicators.macd)?.key}`) }}
                                    </p>
                                </div>
                            </div>

                            <!-- EMA -->
                            <div v-if="asset.indicators.ema" class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ t('assetDetail.indicators.ema') }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.emaDesc') }}</p>
                                </div>
                                <p class="font-bold">{{ asset.indicators.ema.toFixed(2) }}</p>
                            </div>

                            <!-- SMA -->
                            <div v-if="asset.indicators.sma" class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ t('assetDetail.indicators.sma') }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.smaDesc') }}</p>
                                </div>
                                <p class="font-bold">{{ asset.indicators.sma.toFixed(2) }}</p>
                            </div>

                            <!-- ATR -->
                            <div v-if="asset.indicators.atr" class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ t('assetDetail.indicators.atr') }}</p>
                                    <p class="text-xs text-muted-foreground">{{ t('assetDetail.indicators.atrDesc') }}</p>
                                </div>
                                <p class="font-bold">{{ asset.indicators.atr.toFixed(2) }}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Prediction History -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Clock class="size-4 text-orange-500" />
                                {{ t('assetDetail.history.title') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="asset.prediction_history && asset.prediction_history.length > 0" class="space-y-3">
                                <div
                                    v-for="prediction in asset.prediction_history"
                                    :key="prediction.id"
                                    class="flex items-center justify-between py-2 border-b border-border last:border-0"
                                >
                                    <div>
                                        <p class="font-medium">{{ formatPrice(prediction.predicted_price) }}</p>
                                        <p class="text-xs text-muted-foreground">{{ formatShortDate(prediction.timestamp) }}</p>
                                    </div>
                                    <div class="text-end">
                                        <span class="rounded bg-muted px-2 py-0.5 text-xs font-medium">
                                            {{ prediction.horizon }}
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

                    <!-- Actions -->
                    <Card>
                        <CardContent class="pt-6 space-y-3">
                            <Button as-child variant="outline" class="w-full">
                                <a href="https://www.tradingview.com" target="_blank" rel="noopener noreferrer">
                                    {{ t('assetDetail.actions.viewOnTradingView') }}
                                    <ExternalLink class="ms-2 size-4" />
                                </a>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
