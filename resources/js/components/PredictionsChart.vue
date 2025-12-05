<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { VisXYContainer, VisLine, VisAxis, VisArea, VisScatter } from '@unovis/vue';
import { CurveType } from '@unovis/ts';
import { ChartCrosshair, ChartLegend } from '@/components/ui/chart';

interface Prediction {
    horizon: string;
    horizonLabel: string;
    predictedPrice: number;
    confidence: number;
    expectedGainPercent: number;
    timestamp?: string | null;
    targetTimestamp?: string | null;
}

interface Props {
    predictions: Prediction[];
    currentPrice?: number | null;
    currency?: string;
}

const props = withDefaults(defineProps<Props>(), {
    currency: 'EGP',
});

const { t } = useI18n();

// Map horizon labels to numeric order for x-axis
const horizonOrder: Record<string, number> = {
    '15m': 0,
    '30m': 1,
    '1h': 2,
    '4h': 3,
    '1d': 4,
    '1w': 5,
    '1M': 6,
};

// Sort predictions by horizon
const sortedPredictions = computed(() => {
    return [...props.predictions].sort((a, b) => {
        return (horizonOrder[a.horizon] ?? 99) - (horizonOrder[b.horizon] ?? 99);
    });
});

// Transform predictions data for the chart
const chartData = computed(() => {
    const data: Array<{
        label: string;
        price: number;
        current?: number;
    }> = [];

    // Add current price point if available
    if (props.currentPrice) {
        data.push({
            label: t('assetDetail.price.current'),
            price: props.currentPrice,
            current: props.currentPrice,
        });
    }

    // Add prediction points
    sortedPredictions.value.forEach((prediction) => {
        data.push({
            label: prediction.horizonLabel,
            price: prediction.predictedPrice,
            current: props.currentPrice ?? undefined,
        });
    });

    return data;
});

// Chart configuration
const categories = computed(() => {
    const cats = ['price'];
    if (props.currentPrice) cats.push('current');
    return cats;
});

// Colors for the chart
const colors = computed(() => {
    const lastPrediction = sortedPredictions.value[sortedPredictions.value.length - 1];
    const isPositive = lastPrediction ? lastPrediction.expectedGainPercent >= 0 : true;
    return [
        isPositive ? 'hsl(142, 76%, 36%)' : 'hsl(0, 84%, 60%)', // Price line
        'hsl(var(--muted-foreground))', // Current price reference
    ];
});

// Legend items
const legendItems = computed(() => [
    { name: 'price', color: colors.value[0] },
]);

// X accessor
const x = (_d: (typeof chartData.value)[0], i: number) => i;

// Y accessor for price
const yPrice = (d: (typeof chartData.value)[0]) => d.price;

// Y accessor for current price reference line
const yCurrent = (d: (typeof chartData.value)[0]) => d.current;

// X-axis tick format
const xFormatter = (_tick: number | Date, i: number) => {
    return chartData.value[i]?.label ?? '';
};

// Y-axis tick format
const yFormatter = (tick: number | Date) => `${Number(tick).toFixed(0)}`;

// Min/max for Y axis
const yDomain = computed(() => {
    const prices = chartData.value.map((d) => d.price);
    if (props.currentPrice) prices.push(props.currentPrice);
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    const padding = (max - min) * 0.15 || max * 0.1;
    return [min - padding, max + padding];
});

// Point color function
const pointColor = (d: (typeof chartData.value)[0]) => {
    if (!props.currentPrice) return colors.value[0];
    return d.price >= props.currentPrice
        ? 'hsl(142, 76%, 36%)' // green
        : 'hsl(0, 84%, 60%)'; // red
};

// Format price helper
const formatPrice = (value: number) => {
    return `${value.toFixed(2)} ${props.currency}`;
};
</script>

<template>
    <div class="w-full">
        <!-- Line Chart -->
        <div v-if="chartData.length > 0" class="h-[250px]">
            <VisXYContainer :data="chartData" :yDomain="yDomain" :margin="{ top: 10, right: 10, bottom: 30, left: 50 }">
                <!-- Current price reference line (dashed) -->
                <VisLine
                    v-if="currentPrice"
                    :x="x"
                    :y="yCurrent"
                    color="hsl(var(--muted-foreground))"
                    :lineWidth="1"
                    :lineDashArray="[5, 5]"
                />

                <!-- Area under the prediction line -->
                <VisArea
                    :x="x"
                    :y="yPrice"
                    :opacity="0.15"
                    :color="colors[0]"
                    :curveType="CurveType.MonotoneX"
                />

                <!-- Main prediction line -->
                <VisLine
                    :x="x"
                    :y="yPrice"
                    :color="colors[0]"
                    :curveType="CurveType.MonotoneX"
                    :lineWidth="2.5"
                />

                <!-- Data points -->
                <VisScatter
                    :x="x"
                    :y="yPrice"
                    :color="pointColor"
                    :size="10"
                    :strokeWidth="2"
                />

                <!-- X Axis -->
                <VisAxis
                    type="x"
                    :tickFormat="xFormatter"
                    :gridLine="false"
                    :tickLine="false"
                    :domainLine="true"
                    :numTicks="chartData.length"
                />

                <!-- Y Axis -->
                <VisAxis
                    type="y"
                    :tickFormat="yFormatter"
                    :gridLine="true"
                    :tickLine="false"
                    :domainLine="false"
                />

                <!-- Crosshair tooltip -->
                <ChartCrosshair
                    :colors="colors"
                    index="label"
                    :items="legendItems"
                />
            </VisXYContainer>
        </div>

        <!-- Legend -->
        <div class="flex items-center justify-center gap-6 mt-4 pt-3 border-t border-border text-xs text-muted-foreground">
            <div class="flex items-center gap-1.5">
                <div class="w-4 h-0.5 bg-muted-foreground" style="border-top: 1px dashed currentColor;" />
                <span>{{ t('assetDetail.price.current') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="size-2.5 rounded-full bg-green-600" />
                <span>{{ t('assetDetail.indicators.bullish') }}</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="size-2.5 rounded-full bg-red-500" />
                <span>{{ t('assetDetail.indicators.bearish') }}</span>
            </div>
        </div>

        <!-- Predictions Summary Table -->
        <div class="mt-5 space-y-1">
            <div
                v-for="prediction in sortedPredictions"
                :key="prediction.horizon"
                class="flex items-center justify-between py-2.5 px-3 rounded-lg hover:bg-muted/50 transition-colors border border-transparent hover:border-border"
            >
                <div class="flex items-center gap-3">
                    <span class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                        {{ prediction.horizonLabel }}
                    </span>
                    <span
                        class="text-xs font-medium px-2 py-0.5 rounded-full"
                        :class="[
                            prediction.confidence >= 85 ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' :
                            prediction.confidence >= 70 ? 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300' :
                            'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300'
                        ]"
                    >
                        {{ prediction.confidence }}% {{ t('assetDetail.prediction.confidence') }}
                    </span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="font-bold text-base">{{ formatPrice(prediction.predictedPrice) }}</span>
                    <span
                        class="text-sm font-semibold min-w-[60px] text-end"
                        :class="prediction.expectedGainPercent >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'"
                    >
                        {{ prediction.expectedGainPercent >= 0 ? '+' : '' }}{{ prediction.expectedGainPercent.toFixed(1) }}%
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
