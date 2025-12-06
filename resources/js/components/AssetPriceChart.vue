<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import {
    VisXYContainer,
    VisLine,
    VisArea,
    VisAxis,
    VisCrosshair,
    VisTooltip,
    VisAnnotations,
} from '@unovis/vue';
import { Annotation } from '@unovis/ts';
import { Button } from '@/components/ui/button';
import type { PriceHistoryPoint, PredictionChartPoint, ChartPeriod } from '@/types';

interface ChartDataPoint {
    timestamp: number;
    historicalPrice: number | null;
    predictedPrice: number | null;
    upperBound: number | null;
    lowerBound: number | null;
    confidence: number | null;
    isPrediction: boolean;
    horizonLabel?: string;
}

const { t, locale } = useI18n();

const props = defineProps<{
    priceHistory: PriceHistoryPoint[];
    predictionChartData: PredictionChartPoint[];
    chartPeriod: number;
    currency: string;
    assetSymbol: string;
}>();

const emit = defineEmits<{
    periodChange: [period: ChartPeriod];
}>();

const periods: ChartPeriod[] = [7, 30, 90, 180];
const selectedPeriod = ref<ChartPeriod>(props.chartPeriod as ChartPeriod);

// Watch for prop changes
watch(() => props.chartPeriod, (newPeriod) => {
    selectedPeriod.value = newPeriod as ChartPeriod;
});

const periodLabels: Record<ChartPeriod, string> = {
    7: '7D',
    30: '30D',
    90: '90D',
    180: '180D',
};

const handlePeriodChange = (period: ChartPeriod) => {
    if (period === selectedPeriod.value) return;
    selectedPeriod.value = period;
    emit('periodChange', period);

    // Navigate with new period
    router.reload({
        data: { period },
        only: ['priceHistory', 'predictionChartData', 'chartPeriod'],
        preserveState: true,
        preserveScroll: true,
    });
};

// Combine historical and prediction data for the chart
const chartData = computed<ChartDataPoint[]>(() => {
    const data: ChartDataPoint[] = [];

    // Add historical price points
    if (props.priceHistory?.length > 0) {
        props.priceHistory.forEach((point) => {
            data.push({
                timestamp: point.timestamp,
                historicalPrice: point.close,
                predictedPrice: null,
                upperBound: null,
                lowerBound: null,
                confidence: null,
                isPrediction: false,
            });
        });
    }

    // Add prediction chart points
    if (props.predictionChartData?.length > 0) {
        props.predictionChartData.forEach((point) => {
            // Check if this timestamp already exists (for the connection point)
            const existingIndex = data.findIndex(d => d.timestamp === point.timestamp);

            if (existingIndex >= 0 && !point.isPrediction) {
                // Update existing point with prediction start data
                data[existingIndex].predictedPrice = point.price;
            } else if (point.isPrediction) {
                // Add new prediction point
                data.push({
                    timestamp: point.timestamp,
                    historicalPrice: null,
                    predictedPrice: point.price,
                    upperBound: point.upperBound,
                    lowerBound: point.lowerBound,
                    confidence: point.confidence,
                    isPrediction: true,
                    horizonLabel: point.horizonLabel,
                });
            }
        });
    }

    // Sort by timestamp
    return data.sort((a, b) => a.timestamp - b.timestamp);
});

// Find the index where prediction starts (last historical point)
const predictionStartIndex = computed(() => {
    const lastHistoricalIndex = chartData.value.findLastIndex(d => d.historicalPrice !== null);
    return lastHistoricalIndex >= 0 ? lastHistoricalIndex : chartData.value.length - 1;
});

// Create connection data - connects last historical price to first prediction
const connectionData = computed<ChartDataPoint[]>(() => {
    const data = chartData.value;
    const startIdx = predictionStartIndex.value;

    if (startIdx < 0 || startIdx >= data.length - 1) return [];

    const lastHistorical = data[startIdx];
    const firstPrediction = data.find((d, i) => i > startIdx && d.predictedPrice !== null);

    if (!lastHistorical || !firstPrediction) return [];

    return [
        {
            ...lastHistorical,
            predictedPrice: lastHistorical.historicalPrice,
        },
        firstPrediction,
    ];
});

// X accessor
const x = (d: ChartDataPoint) => d.timestamp;

// Y accessors for lines (return number, not null - data is pre-filtered)
const yHistorical = (d: ChartDataPoint) => d.historicalPrice as number;
const yPredicted = (d: ChartDataPoint) => d.predictedPrice as number;
const yConnection = (d: ChartDataPoint) => d.predictedPrice as number;

// Y accessors for confidence band area
const yAreaMin = (d: ChartDataPoint) => d.lowerBound;
const yAreaMax = (d: ChartDataPoint) => d.upperBound;

// Confidence band data (only prediction points with bounds)
const confidenceBandData = computed(() => {
    return chartData.value.filter(d => d.isPrediction && d.upperBound !== null && d.lowerBound !== null);
});

// Historical data only (filtered for VisLine - no null values)
const historicalData = computed(() => {
    return chartData.value.filter(d => d.historicalPrice !== null);
});

// Prediction data only (filtered for VisLine - no null values)
const predictionData = computed(() => {
    return chartData.value.filter(d => d.predictedPrice !== null && d.isPrediction);
});

// Colors
const historicalColor = 'hsl(var(--primary))';
const predictedColor = 'hsl(var(--chart-2))';
const bandColor = 'hsl(var(--chart-2) / 0.2)';

// Format functions
const formatPrice = (value: number) => {
    return `${value.toFixed(2)} ${props.currency}`;
};

const formatDate = (timestamp: number) => {
    const date = new Date(timestamp);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatXAxis = (timestamp: number | Date) => {
    const date = timestamp instanceof Date ? timestamp : new Date(timestamp);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
    });
};

const formatYAxis = (value: number | Date) => {
    if (typeof value === 'number') {
        if (value >= 1000) {
            return `${(value / 1000).toFixed(1)}K`;
        }
        return value.toFixed(2);
    }
    return String(value);
};

// Tooltip template
const tooltipTemplate = (d: ChartDataPoint) => {
    const lines: string[] = [];
    lines.push(`<div class="font-medium text-sm">${formatDate(d.timestamp)}</div>`);

    if (d.historicalPrice !== null) {
        lines.push(`<div class="flex items-center gap-2 mt-1">
            <span class="w-2 h-2 rounded-full" style="background: ${historicalColor}"></span>
            <span class="text-muted-foreground">${t('assetDetail.chart.historical')}:</span>
            <span class="font-semibold">${formatPrice(d.historicalPrice)}</span>
        </div>`);
    }

    if (d.predictedPrice !== null && d.isPrediction) {
        lines.push(`<div class="flex items-center gap-2 mt-1">
            <span class="w-2 h-2 rounded-full" style="background: ${predictedColor}"></span>
            <span class="text-muted-foreground">${t('assetDetail.chart.predicted')}:</span>
            <span class="font-semibold">${formatPrice(d.predictedPrice)}</span>
        </div>`);

        if (d.confidence !== null) {
            lines.push(`<div class="text-xs text-muted-foreground mt-1">
                ${t('assetDetail.prediction.confidence')}: ${d.confidence.toFixed(0)}%
            </div>`);
        }

        if (d.horizonLabel) {
            lines.push(`<div class="text-xs text-muted-foreground">
                ${t('assetDetail.prediction.horizon')}: ${d.horizonLabel}
            </div>`);
        }
    }

    return `<div class="p-2 bg-background border border-border rounded-lg shadow-lg">${lines.join('')}</div>`;
};

// Annotation for the "Now" marker
const nowAnnotation = computed<Annotation[]>(() => {
    const data = chartData.value;
    const lastHistoricalPoint = data[predictionStartIndex.value];

    if (!lastHistoricalPoint) return [];

    return [{
        x: lastHistoricalPoint.timestamp,
        content: {
            text: locale.value === 'ar' ? 'الآن' : 'Now',
            color: 'hsl(var(--muted-foreground))',
            fontSize: 12,
        },
        verticalLineStyle: {
            stroke: 'hsl(var(--border))',
            strokeDasharray: '4 4',
        },
    }];
});

// Check if we have data
const hasData = computed(() => {
    return (props.priceHistory?.length > 0) || (props.predictionChartData?.length > 0);
});

const hasPredictions = computed(() => {
    return props.predictionChartData?.some(p => p.isPrediction) ?? false;
});
</script>

<template>
    <div class="space-y-4">
        <!-- Period selector -->
        <div class="flex justify-end">
            <div class="flex items-center gap-1 bg-muted/50 rounded-lg p-1">
                <Button
                    v-for="period in periods"
                    :key="period"
                    :variant="selectedPeriod === period ? 'secondary' : 'ghost'"
                    size="sm"
                    class="h-7 px-3 text-xs"
                    @click="handlePeriodChange(period)"
                >
                    {{ periodLabels[period] }}
                </Button>
            </div>
        </div>

        <!-- Chart -->
        <div v-if="hasData" class="h-64 sm:h-80">
            <VisXYContainer :data="chartData" :margin="{ top: 10, right: 10, bottom: 30, left: 50 }">
                <!-- X and Y Axes -->
                <VisAxis
                    type="x"
                    :tick-format="formatXAxis"
                    :grid-line="false"
                    :domain-line="false"
                    :tick-line="false"
                />
                <VisAxis
                    type="y"
                    :tick-format="formatYAxis"
                    :grid-line="true"
                    :domain-line="false"
                    :tick-line="false"
                />

                <!-- Confidence band area -->
                <VisArea
                    v-if="confidenceBandData.length > 0"
                    :data="confidenceBandData"
                    :x="x"
                    :y="[yAreaMin, yAreaMax]"
                    :color="bandColor"
                    :opacity="0.3"
                    :curve-type="'linear'"
                />

                <!-- Historical price line (solid) -->
                <VisLine
                    :data="historicalData"
                    :x="x"
                    :y="yHistorical"
                    :color="historicalColor"
                    :line-width="2"
                />

                <!-- Connection line (from last historical to first prediction) -->
                <VisLine
                    v-if="connectionData.length > 0"
                    :data="connectionData"
                    :x="x"
                    :y="yConnection"
                    :color="predictedColor"
                    :line-width="2"
                    :line-dash-array="[4, 4]"
                />

                <!-- Predicted price line (dashed) -->
                <VisLine
                    :data="predictionData"
                    :x="x"
                    :y="yPredicted"
                    :color="predictedColor"
                    :line-width="2"
                    :line-dash-array="[4, 4]"
                />

                <!-- Now marker annotation -->
                <VisAnnotations
                    v-if="nowAnnotation.length > 0"
                    :items="nowAnnotation"
                />

                <!-- Crosshair -->
                <VisCrosshair
                    :template="tooltipTemplate"
                    color="hsl(var(--border))"
                />

                <!-- Tooltip -->
                <VisTooltip />
            </VisXYContainer>
        </div>

        <!-- Empty state -->
        <div v-else class="flex items-center justify-center h-64 rounded-lg bg-muted/30 border border-dashed border-border">
            <p class="text-muted-foreground">{{ t('common.noData') }}</p>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap items-center justify-center gap-6 text-sm">
            <div class="flex items-center gap-2">
                <span class="w-4 h-0.5 rounded" :style="{ background: historicalColor }"></span>
                <span class="text-muted-foreground">{{ t('assetDetail.chart.historical') }}</span>
            </div>
            <div v-if="hasPredictions" class="flex items-center gap-2">
                <span class="w-4 h-0.5 rounded border-t-2 border-dashed" :style="{ borderColor: predictedColor }"></span>
                <span class="text-muted-foreground">{{ t('assetDetail.chart.predicted') }}</span>
            </div>
            <div v-if="hasPredictions" class="flex items-center gap-2">
                <span class="w-4 h-3 rounded opacity-30" :style="{ background: predictedColor }"></span>
                <span class="text-muted-foreground">{{ t('assetDetail.chart.confidenceBand') }}</span>
            </div>
        </div>
    </div>
</template>
