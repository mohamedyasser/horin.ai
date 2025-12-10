<script setup lang="ts">
import { computed, ref, watch, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import { Loader2, TrendingUp, TrendingDown, ChevronDown, LineChart as LineChartIcon, CandlestickChart as CandleIcon, AreaChart } from 'lucide-vue-next';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { CandlestickChart, LineChart, BarChart } from 'echarts/charts';
import {
    GridComponent,
    TooltipComponent,
    DataZoomComponent,
    MarkLineComponent,
    MarkAreaComponent,
    LegendComponent,
} from 'echarts/components';
import VChart from 'vue-echarts';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { PriceHistoryPoint, PredictionChartPoint, IndicatorHistoryPoint, ChartPeriod } from '@/types';

use([
    CanvasRenderer,
    CandlestickChart,
    LineChart,
    BarChart,
    GridComponent,
    TooltipComponent,
    DataZoomComponent,
    MarkLineComponent,
    MarkAreaComponent,
    LegendComponent,
]);

const { t, locale } = useI18n();

const props = defineProps<{
    priceHistory: PriceHistoryPoint[];
    predictionChartData: PredictionChartPoint[];
    indicatorHistory?: IndicatorHistoryPoint[];
    chartPeriod: number;
    currency: string;
    assetSymbol: string;
}>();

const emit = defineEmits<{
    periodChange: [period: ChartPeriod];
}>();

// Chart type selector - Line chart is default (simpler for normal users)
type ChartType = 'line' | 'candlestick' | 'area';
const chartType = ref<ChartType>('line');

// Period selector - Extended periods like professional charts
const periods: ChartPeriod[] = [1, 7, 30, 90, 180];
const selectedPeriod = ref<ChartPeriod>(props.chartPeriod as ChartPeriod);
const isLoading = ref(false);

// Indicator toggles - all off by default for clean view
const showEMA = ref(false);
const showSMA = ref(false);
const showRSI = ref(false);
const showMACD = ref(false);

watch(() => props.chartPeriod, (newPeriod) => {
    selectedPeriod.value = newPeriod as ChartPeriod;
    isLoading.value = false;
});

const periodLabels: Record<ChartPeriod, string> = {
    1: '1D',
    7: '7D',
    30: '30D',
    90: '90D',
    180: '180D',
};

const removeFinishListener: (() => void) | null = null;
onUnmounted(() => {
    if (removeFinishListener) {
        removeFinishListener();
    }
});

const handlePeriodChange = (period: ChartPeriod) => {
    if (period === selectedPeriod.value || isLoading.value) return;
    selectedPeriod.value = period;
    isLoading.value = true;
    emit('periodChange', period);

    router.reload({
        data: { period },
        only: ['priceHistory', 'predictionChartData', 'indicatorHistory', 'chartPeriod'],
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            isLoading.value = false;
        },
    });
};

// Format date for X axis
const formatDate = (timestamp: number) => {
    const date = new Date(timestamp);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
    });
};

// Build timestamps array
const rawTimestamps = computed(() => {
    const timestamps: number[] = [];
    props.priceHistory?.forEach(p => timestamps.push(p.timestamp));
    props.predictionChartData?.filter(p => p.isPrediction).forEach(p => timestamps.push(p.timestamp));
    return [...new Set(timestamps)].sort((a, b) => a - b);
});

const chartCategories = computed(() => rawTimestamps.value.map(formatDate));

// Create timestamp index map for data alignment
const timestampIndexMap = computed(() => new Map(rawTimestamps.value.map((ts, i) => [ts, i])));

// Candlestick data: [open, close, low, high] (ECharts OCLH order)
const candlestickData = computed(() => {
    return props.priceHistory?.map(p => [p.open, p.close, p.low, p.high]) ?? [];
});

// Volume data
const volumeData = computed(() => {
    return props.priceHistory?.map(p => ({
        value: p.volume,
        itemStyle: {
            color: p.close >= p.open ? 'rgba(34, 197, 94, 0.5)' : 'rgba(239, 68, 68, 0.5)',
        },
    })) ?? [];
});

// Prediction line data
const predictionLineData = computed(() => {
    if (!props.predictionChartData?.length) return [];

    const data: (number | null)[] = new Array(rawTimestamps.value.length).fill(null);

    // Connect from last historical price
    const lastHistorical = props.priceHistory?.[props.priceHistory.length - 1];
    if (lastHistorical) {
        const idx = timestampIndexMap.value.get(lastHistorical.timestamp);
        if (idx !== undefined) data[idx] = lastHistorical.close;
    }

    // Add prediction points
    props.predictionChartData.filter(p => p.isPrediction).forEach(p => {
        const idx = timestampIndexMap.value.get(p.timestamp);
        if (idx !== undefined) data[idx] = p.price;
    });

    return data;
});

// Confidence band
const upperBandData = computed(() => {
    if (!props.predictionChartData?.length) return [];
    const data: (number | null)[] = new Array(rawTimestamps.value.length).fill(null);
    props.predictionChartData.filter(p => p.isPrediction && p.upperBound).forEach(p => {
        const idx = timestampIndexMap.value.get(p.timestamp);
        if (idx !== undefined) data[idx] = p.upperBound;
    });
    return data;
});

const lowerBandData = computed(() => {
    if (!props.predictionChartData?.length) return [];
    const data: (number | null)[] = new Array(rawTimestamps.value.length).fill(null);
    props.predictionChartData.filter(p => p.isPrediction && p.lowerBound).forEach(p => {
        const idx = timestampIndexMap.value.get(p.timestamp);
        if (idx !== undefined) data[idx] = p.lowerBound;
    });
    return data;
});

// EMA line data
const emaData = computed(() => {
    if (!props.indicatorHistory?.length) return [];
    const data: (number | null)[] = new Array(rawTimestamps.value.length).fill(null);
    props.indicatorHistory.forEach(ind => {
        const idx = timestampIndexMap.value.get(ind.timestamp);
        if (idx !== undefined && ind.ema !== null) data[idx] = ind.ema;
    });
    return data;
});

// SMA line data
const smaData = computed(() => {
    if (!props.indicatorHistory?.length) return [];
    const data: (number | null)[] = new Array(rawTimestamps.value.length).fill(null);
    props.indicatorHistory.forEach(ind => {
        const idx = timestampIndexMap.value.get(ind.timestamp);
        if (idx !== undefined && ind.sma !== null) data[idx] = ind.sma;
    });
    return data;
});

// RSI data
const rsiData = computed(() => {
    if (!props.indicatorHistory?.length) return [];
    const data: (number | null)[] = new Array(rawTimestamps.value.length).fill(null);
    props.indicatorHistory.forEach(ind => {
        const idx = timestampIndexMap.value.get(ind.timestamp);
        if (idx !== undefined && ind.rsi !== null) data[idx] = ind.rsi;
    });
    return data;
});

// MACD data
const macdLineData = computed(() => {
    if (!props.indicatorHistory?.length) return [];
    const data: (number | null)[] = new Array(rawTimestamps.value.length).fill(null);
    props.indicatorHistory.forEach(ind => {
        const idx = timestampIndexMap.value.get(ind.timestamp);
        if (idx !== undefined && ind.macd_line !== null) data[idx] = ind.macd_line;
    });
    return data;
});

const macdSignalData = computed(() => {
    if (!props.indicatorHistory?.length) return [];
    const data: (number | null)[] = new Array(rawTimestamps.value.length).fill(null);
    props.indicatorHistory.forEach(ind => {
        const idx = timestampIndexMap.value.get(ind.timestamp);
        if (idx !== undefined && ind.macd_signal !== null) data[idx] = ind.macd_signal;
    });
    return data;
});

const macdHistogramData = computed(() => {
    if (!props.indicatorHistory?.length) return [];
    return props.indicatorHistory.map(ind => ({
        value: ind.macd_histogram ?? 0,
        itemStyle: {
            color: (ind.macd_histogram ?? 0) >= 0 ? 'rgba(34, 197, 94, 0.7)' : 'rgba(239, 68, 68, 0.7)',
        },
    }));
});

const hasPredictions = computed(() => props.predictionChartData?.some(p => p.isPrediction) ?? false);
const hasData = computed(() => (props.priceHistory?.length ?? 0) > 0);
const hasIndicators = computed(() => (props.indicatorHistory?.length ?? 0) > 0);
const nowMarkerIndex = computed(() => (props.priceHistory?.length ?? 1) - 1);

// OHLC stats for the latest candle (displayed in header like professional charts)
const latestOHLC = computed(() => {
    if (!props.priceHistory?.length) return null;
    const latest = props.priceHistory[props.priceHistory.length - 1];
    const previous = props.priceHistory.length > 1 ? props.priceHistory[props.priceHistory.length - 2] : null;
    const change = previous ? latest.close - previous.close : 0;
    const changePercent = previous ? (change / previous.close) * 100 : 0;
    return {
        open: latest.open,
        high: latest.high,
        low: latest.low,
        close: latest.close,
        volume: latest.volume,
        change,
        changePercent,
        isPositive: change >= 0,
    };
});

// Line chart data - simple close prices
const lineData = computed(() => {
    return props.priceHistory?.map(p => p.close) ?? [];
});

// Area chart data - same as line but styled differently
const areaData = computed(() => lineData.value);

// Format price for tooltip
const formatPrice = (value: number) => {
    return new Intl.NumberFormat(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
};

// Dynamic grid layout based on visible indicators
const gridLayout = computed(() => {
    const grids: { left: string; right: string; top: string; height: string }[] = [];
    const xAxisConfigs: { type: string; data: string[]; gridIndex: number; show: boolean }[] = [];
    const yAxisConfigs: { scale: boolean; gridIndex: number; splitLine: object; axisLabel: object; min?: number; max?: number }[] = [];

    let currentTop = 8; // Start at 8%
    const rightMargin = '4%';
    const leftMargin = '12%';

    // Main price grid - always shown (55% if no indicators, smaller if indicators shown)
    const mainHeight = (showRSI.value || showMACD.value) ? 40 : 55;
    grids.push({ left: leftMargin, right: rightMargin, top: `${currentTop}%`, height: `${mainHeight}%` });
    xAxisConfigs.push({ type: 'category', data: chartCategories.value, gridIndex: 0, show: false });
    yAxisConfigs.push({
        scale: true,
        gridIndex: 0,
        splitLine: { lineStyle: { color: 'hsl(var(--border))', type: 'dashed' } },
        axisLabel: { fontSize: 10, color: 'hsl(var(--muted-foreground))', formatter: (v: number) => formatPrice(v) },
    });
    currentTop += mainHeight + 2;

    // Volume grid - always shown (smaller)
    const volumeHeight = (showRSI.value || showMACD.value) ? 10 : 15;
    grids.push({ left: leftMargin, right: rightMargin, top: `${currentTop}%`, height: `${volumeHeight}%` });
    xAxisConfigs.push({ type: 'category', data: chartCategories.value, gridIndex: 1, show: !(showRSI.value || showMACD.value) });
    yAxisConfigs.push({ scale: true, gridIndex: 1, splitLine: { show: false }, axisLabel: { show: false } });
    currentTop += volumeHeight + 2;

    // RSI grid - optional
    if (showRSI.value) {
        grids.push({ left: leftMargin, right: rightMargin, top: `${currentTop}%`, height: '12%' });
        xAxisConfigs.push({ type: 'category', data: chartCategories.value, gridIndex: grids.length - 1, show: !showMACD.value });
        yAxisConfigs.push({
            scale: false,
            gridIndex: grids.length - 1,
            min: 0,
            max: 100,
            splitLine: { lineStyle: { color: 'hsl(var(--border))', type: 'dashed' } },
            axisLabel: { fontSize: 9, color: 'hsl(var(--muted-foreground))' },
        });
        currentTop += 14;
    }

    // MACD grid - optional
    if (showMACD.value) {
        grids.push({ left: leftMargin, right: rightMargin, top: `${currentTop}%`, height: '12%' });
        xAxisConfigs.push({ type: 'category', data: chartCategories.value, gridIndex: grids.length - 1, show: true });
        yAxisConfigs.push({
            scale: true,
            gridIndex: grids.length - 1,
            splitLine: { lineStyle: { color: 'hsl(var(--border))', type: 'dashed' } },
            axisLabel: { fontSize: 9, color: 'hsl(var(--muted-foreground))' },
        });
    }

    return { grids, xAxisConfigs, yAxisConfigs };
});

// ECharts option
const chartOption = computed(() => {
    const { grids, xAxisConfigs, yAxisConfigs } = gridLayout.value;

    const series: Record<string, unknown>[] = [];

    // Main price series - changes based on chart type
    const priceMarkLine = hasPredictions.value ? {
        silent: true,
        symbol: 'none',
        data: [{
            xAxis: nowMarkerIndex.value,
            label: {
                formatter: locale.value === 'ar' ? 'الآن' : 'Now',
                position: 'insideEndTop',
                fontSize: 11,
                color: 'hsl(var(--muted-foreground))',
            },
        }],
        lineStyle: { type: 'dashed', color: 'hsl(var(--border))', width: 1 },
    } : undefined;

    if (chartType.value === 'candlestick') {
        // Candlestick chart
        series.push({
            name: t('assetDetail.chart.price'),
            type: 'candlestick',
            data: candlestickData.value,
            xAxisIndex: 0,
            yAxisIndex: 0,
            itemStyle: {
                color: '#22c55e',
                color0: '#ef4444',
                borderColor: '#16a34a',
                borderColor0: '#dc2626',
            },
            markLine: priceMarkLine,
        });
    } else if (chartType.value === 'area') {
        // Area chart
        series.push({
            name: t('assetDetail.chart.price'),
            type: 'line',
            data: areaData.value,
            xAxisIndex: 0,
            yAxisIndex: 0,
            lineStyle: { width: 2, color: '#3b82f6' },
            itemStyle: { color: '#3b82f6' },
            symbol: 'none',
            areaStyle: {
                color: {
                    type: 'linear',
                    x: 0, y: 0, x2: 0, y2: 1,
                    colorStops: [
                        { offset: 0, color: 'rgba(59, 130, 246, 0.3)' },
                        { offset: 1, color: 'rgba(59, 130, 246, 0.02)' },
                    ],
                },
            },
            markLine: priceMarkLine,
        });
    } else {
        // Line chart (default)
        series.push({
            name: t('assetDetail.chart.price'),
            type: 'line',
            data: lineData.value,
            xAxisIndex: 0,
            yAxisIndex: 0,
            lineStyle: { width: 2, color: '#3b82f6' },
            itemStyle: { color: '#3b82f6' },
            symbol: 'none',
            markLine: priceMarkLine,
        });
    }

    // Volume bars
    series.push({
        name: t('assetDetail.chart.volume'),
        type: 'bar',
        data: volumeData.value,
        xAxisIndex: 1,
        yAxisIndex: 1,
        barWidth: '60%',
    });

    // Prediction line + confidence band
    if (hasPredictions.value) {
        series.push(
            {
                name: t('assetDetail.chart.predicted'),
                type: 'line',
                data: predictionLineData.value,
                xAxisIndex: 0,
                yAxisIndex: 0,
                lineStyle: { width: 2, type: 'dashed', color: '#f97316' },
                itemStyle: { color: '#f97316' },
                symbol: 'circle',
                symbolSize: 8,
                connectNulls: true,
            },
            {
                name: t('assetDetail.chart.confidenceBand'),
                type: 'line',
                data: upperBandData.value,
                xAxisIndex: 0,
                yAxisIndex: 0,
                lineStyle: { width: 0 },
                itemStyle: { color: 'transparent' },
                symbol: 'none',
                areaStyle: { color: 'rgba(249, 115, 22, 0.15)' },
                stack: 'confidence',
                connectNulls: true,
            },
            {
                name: 'Lower',
                type: 'line',
                data: lowerBandData.value,
                xAxisIndex: 0,
                yAxisIndex: 0,
                lineStyle: { width: 0 },
                itemStyle: { color: 'transparent' },
                symbol: 'none',
                stack: 'confidence',
                connectNulls: true,
            },
        );
    }

    // EMA overlay
    if (showEMA.value && hasIndicators.value) {
        series.push({
            name: 'EMA',
            type: 'line',
            data: emaData.value,
            xAxisIndex: 0,
            yAxisIndex: 0,
            lineStyle: { width: 1.5, color: '#3b82f6' },
            itemStyle: { color: '#3b82f6' },
            symbol: 'none',
            connectNulls: true,
        });
    }

    // SMA overlay
    if (showSMA.value && hasIndicators.value) {
        series.push({
            name: 'SMA',
            type: 'line',
            data: smaData.value,
            xAxisIndex: 0,
            yAxisIndex: 0,
            lineStyle: { width: 1.5, color: '#8b5cf6' },
            itemStyle: { color: '#8b5cf6' },
            symbol: 'none',
            connectNulls: true,
        });
    }

    // RSI pane
    if (showRSI.value && hasIndicators.value) {
        const rsiGridIndex = 2;
        series.push({
            name: 'RSI',
            type: 'line',
            data: rsiData.value,
            xAxisIndex: rsiGridIndex,
            yAxisIndex: rsiGridIndex,
            lineStyle: { width: 1.5, color: '#eab308' },
            itemStyle: { color: '#eab308' },
            symbol: 'none',
            connectNulls: true,
            markLine: {
                silent: true,
                symbol: 'none',
                data: [
                    { yAxis: 70, lineStyle: { color: '#ef4444', type: 'dashed', width: 1 } },
                    { yAxis: 30, lineStyle: { color: '#22c55e', type: 'dashed', width: 1 } },
                ],
                label: { show: false },
            },
        });
    }

    // MACD pane
    if (showMACD.value && hasIndicators.value) {
        const macdGridIndex = showRSI.value ? 3 : 2;
        series.push(
            {
                name: 'MACD',
                type: 'line',
                data: macdLineData.value,
                xAxisIndex: macdGridIndex,
                yAxisIndex: macdGridIndex,
                lineStyle: { width: 1.5, color: '#3b82f6' },
                itemStyle: { color: '#3b82f6' },
                symbol: 'none',
                connectNulls: true,
            },
            {
                name: 'Signal',
                type: 'line',
                data: macdSignalData.value,
                xAxisIndex: macdGridIndex,
                yAxisIndex: macdGridIndex,
                lineStyle: { width: 1.5, color: '#f97316' },
                itemStyle: { color: '#f97316' },
                symbol: 'none',
                connectNulls: true,
            },
            {
                name: 'Histogram',
                type: 'bar',
                data: macdHistogramData.value,
                xAxisIndex: macdGridIndex,
                yAxisIndex: macdGridIndex,
                barWidth: '60%',
            },
        );
    }

    // Build x-axis array from configs
    const xAxisArray = xAxisConfigs.map((cfg, i) => ({
        type: cfg.type,
        data: cfg.data,
        gridIndex: cfg.gridIndex,
        axisLine: { show: false },
        axisTick: { show: false },
        axisLabel: cfg.show ? {
            fontSize: 10,
            color: 'hsl(var(--muted-foreground))',
            interval: 'auto',
        } : { show: false },
    }));

    // Build dataZoom x-axis indices
    const xAxisIndices = xAxisArray.map((_, i) => i);

    return {
        animation: true,
        animationDuration: 300,
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'cross',
                crossStyle: { color: 'hsl(var(--muted-foreground))' },
            },
            backgroundColor: 'hsl(var(--background))',
            borderColor: 'hsl(var(--border))',
            textStyle: { color: 'hsl(var(--foreground))', fontSize: 12 },
            formatter: (params: { seriesName: string; seriesType: string; value: number | number[] | { value: number }; color: string; axisValue: string }[]) => {
                if (!params?.length) return '';

                const date = params[0].axisValue;
                let html = `<div class="font-medium mb-1">${date}</div>`;

                params.forEach(p => {
                    if (p.value === null || p.value === undefined) return;
                    if (p.seriesName === 'Lower' || p.seriesName === t('assetDetail.chart.confidenceBand') || p.seriesName === 'Histogram') return;

                    if (p.seriesType === 'candlestick' && Array.isArray(p.value)) {
                        html += `<div class="flex justify-between gap-4">
                            <span style="color:${p.color}">${p.seriesName}</span>
                            <span>O: ${formatPrice(p.value[0])} C: ${formatPrice(p.value[1])}</span>
                        </div>
                        <div class="flex justify-between gap-4 text-xs opacity-70">
                            <span></span>
                            <span>H: ${formatPrice(p.value[3])} L: ${formatPrice(p.value[2])}</span>
                        </div>`;
                    } else if (p.seriesType === 'bar' && p.seriesName === t('assetDetail.chart.volume')) {
                        const vol = typeof p.value === 'object' && 'value' in p.value ? p.value.value : p.value;
                        html += `<div class="flex justify-between gap-4">
                            <span style="color:${p.color}">${p.seriesName}</span>
                            <span>${new Intl.NumberFormat().format(vol as number)}</span>
                        </div>`;
                    } else if (typeof p.value === 'number') {
                        const unit = ['EMA', 'SMA', t('assetDetail.chart.predicted')].includes(p.seriesName) ? ` ${props.currency}` : '';
                        html += `<div class="flex justify-between gap-4">
                            <span style="color:${p.color}">${p.seriesName}</span>
                            <span>${p.seriesName === 'RSI' ? p.value.toFixed(1) : formatPrice(p.value)}${unit}</span>
                        </div>`;
                    }
                });

                return html;
            },
        },
        grid: grids,
        xAxis: xAxisArray,
        yAxis: yAxisConfigs,
        dataZoom: [
            { type: 'inside', xAxisIndex: xAxisIndices, start: 50, end: 100 },
            {
                type: 'slider',
                xAxisIndex: xAxisIndices,
                bottom: '2%',
                height: 20,
                borderColor: 'hsl(var(--border))',
                fillerColor: 'hsla(var(--primary), 0.2)',
                handleStyle: { color: 'hsl(var(--primary))' },
            },
        ],
        series,
    };
});

// Dynamic chart height based on visible indicators
const chartHeight = computed(() => {
    let height = 320; // Base height
    if (showRSI.value) height += 80;
    if (showMACD.value) height += 80;
    return `${height}px`;
});
</script>

<template>
    <div class="space-y-3">
        <!-- OHLC Header Stats - Like professional charts -->
        <div v-if="latestOHLC" class="flex flex-wrap items-center gap-x-6 gap-y-2 px-1">
            <!-- Price change indicator -->
            <div class="flex items-center gap-2">
                <span class="text-2xl font-semibold">{{ formatPrice(latestOHLC.close) }}</span>
                <span class="text-sm text-muted-foreground">{{ currency }}</span>
                <span
                    :class="[
                        'flex items-center gap-1 text-sm font-medium px-2 py-0.5 rounded',
                        latestOHLC.isPositive ? 'text-green-600 bg-green-500/10' : 'text-red-600 bg-red-500/10'
                    ]"
                >
                    <TrendingUp v-if="latestOHLC.isPositive" class="size-3.5" />
                    <TrendingDown v-else class="size-3.5" />
                    {{ latestOHLC.isPositive ? '+' : '' }}{{ formatPrice(latestOHLC.change) }} ({{ latestOHLC.changePercent.toFixed(2) }}%)
                </span>
            </div>
            <!-- OHLC values -->
            <div class="flex items-center gap-4 text-sm text-muted-foreground">
                <div class="flex items-center gap-1.5">
                    <span class="font-medium text-foreground/70">O</span>
                    <span>{{ formatPrice(latestOHLC.open) }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-medium text-green-600">H</span>
                    <span>{{ formatPrice(latestOHLC.high) }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-medium text-red-600">L</span>
                    <span>{{ formatPrice(latestOHLC.low) }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="font-medium text-foreground/70">C</span>
                    <span>{{ formatPrice(latestOHLC.close) }}</span>
                </div>
            </div>
        </div>

        <!-- Controls row: Chart type + Indicators + Period selector -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
            <div class="flex items-center gap-2">
                <!-- Chart Type Selector -->
                <div class="flex items-center gap-1 bg-muted/50 rounded-lg p-1">
                    <Button
                        :variant="chartType === 'line' ? 'secondary' : 'ghost'"
                        size="sm"
                        class="h-7 w-7 p-0"
                        :title="t('assetDetail.chart.lineChart')"
                        @click="chartType = 'line'"
                    >
                        <LineChartIcon class="size-4" />
                    </Button>
                    <Button
                        :variant="chartType === 'area' ? 'secondary' : 'ghost'"
                        size="sm"
                        class="h-7 w-7 p-0"
                        :title="t('assetDetail.chart.areaChart')"
                        @click="chartType = 'area'"
                    >
                        <AreaChart class="size-4" />
                    </Button>
                    <Button
                        :variant="chartType === 'candlestick' ? 'secondary' : 'ghost'"
                        size="sm"
                        class="h-7 w-7 p-0"
                        :title="t('assetDetail.chart.candlestickChart')"
                        @click="chartType = 'candlestick'"
                    >
                        <CandleIcon class="size-4" />
                    </Button>
                </div>

                <!-- Indicator toggles (dropdown for cleaner UI) -->
                <DropdownMenu v-if="hasIndicators">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm" class="h-7 px-3 text-xs gap-1">
                            {{ t('assetDetail.chart.indicators') }}
                            <ChevronDown class="size-3" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        <DropdownMenuItem @click="showEMA = !showEMA" class="gap-2">
                            <div :class="['w-2 h-2 rounded-full', showEMA ? 'bg-blue-500' : 'bg-muted']" />
                            EMA (12)
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="showSMA = !showSMA" class="gap-2">
                            <div :class="['w-2 h-2 rounded-full', showSMA ? 'bg-purple-500' : 'bg-muted']" />
                            SMA (20)
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="showRSI = !showRSI" class="gap-2">
                            <div :class="['w-2 h-2 rounded-full', showRSI ? 'bg-yellow-500' : 'bg-muted']" />
                            RSI (14)
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="showMACD = !showMACD" class="gap-2">
                            <div :class="['w-2 h-2 rounded-full', showMACD ? 'bg-blue-500' : 'bg-muted']" />
                            MACD
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- Period selector -->
            <div class="flex items-center gap-1 bg-muted/50 rounded-lg p-1">
                <Button
                    v-for="period in periods"
                    :key="period"
                    :variant="selectedPeriod === period ? 'secondary' : 'ghost'"
                    size="sm"
                    class="h-7 px-3 text-xs"
                    :disabled="isLoading"
                    @click="handlePeriodChange(period)"
                >
                    {{ periodLabels[period] }}
                </Button>
            </div>
        </div>

        <!-- Chart -->
        <div v-if="hasData" class="relative" :style="{ height: chartHeight }">
            <!-- Loading overlay -->
            <div
                v-if="isLoading"
                class="absolute inset-0 z-10 flex items-center justify-center bg-background/80 backdrop-blur-sm rounded-lg"
            >
                <div class="flex flex-col items-center gap-2">
                    <Loader2 class="size-8 animate-spin text-primary" />
                    <span class="text-sm text-muted-foreground">{{ t('common.loading') }}</span>
                </div>
            </div>
            <VChart :option="chartOption" autoresize class="w-full h-full" />
        </div>

        <!-- Empty state -->
        <div v-else class="flex items-center justify-center h-64 rounded-lg bg-muted/30 border border-dashed border-border">
            <p class="text-muted-foreground">{{ t('common.noData') }}</p>
        </div>

        <!-- Legend - Compact and contextual -->
        <div class="flex flex-wrap items-center justify-center gap-4 text-xs text-muted-foreground">
            <!-- Price legend varies by chart type -->
            <div v-if="chartType === 'candlestick'" class="flex items-center gap-1.5">
                <div class="flex items-center gap-0.5">
                    <span class="w-1.5 h-3 bg-green-500 rounded-sm"></span>
                    <span class="w-1.5 h-3 bg-red-500 rounded-sm"></span>
                </div>
                <span>{{ t('assetDetail.chart.price') }}</span>
            </div>
            <div v-else class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 bg-blue-500 rounded"></span>
                <span>{{ t('assetDetail.chart.price') }}</span>
            </div>
            <div v-if="hasPredictions" class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 rounded border-t border-dashed border-orange-500"></span>
                <span>{{ t('assetDetail.chart.predicted') }}</span>
            </div>
            <div v-if="showEMA" class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 bg-blue-500 rounded"></span>
                <span>EMA</span>
            </div>
            <div v-if="showSMA" class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 bg-purple-500 rounded"></span>
                <span>SMA</span>
            </div>
            <div v-if="showRSI" class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 bg-yellow-500 rounded"></span>
                <span>RSI</span>
            </div>
            <div v-if="showMACD" class="flex items-center gap-1.5">
                <span class="w-3 h-0.5 bg-blue-500 rounded"></span>
                <span>MACD</span>
            </div>
        </div>
    </div>
</template>
