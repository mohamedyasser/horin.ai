<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import type { BacktestResult } from '@/types/alerts';
import {
    Activity,
    Calendar,
    FlaskConical,
    Target,
    TrendingDown,
    TrendingUp,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface Props {
    result: BacktestResult;
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    compact: false,
});

const formatReturn = (value: number | null | undefined): string => {
    if (value === null || value === undefined) return '-';
    const percent = value * 100;
    const sign = percent >= 0 ? '+' : '';
    return `${sign}${percent.toFixed(1)}%`;
};

const formatDate = (dateStr: string): string => {
    return new Date(dateStr).toLocaleDateString(locale.value, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const isPositive = (value: number | null | undefined): boolean => {
    return value !== null && value !== undefined && value > 0;
};

const isNegative = (value: number | null | undefined): boolean => {
    return value !== null && value !== undefined && value < 0;
};

const winRateColor = computed(() => {
    const rate = props.result.win_rate;
    if (!rate) return 'text-muted-foreground';
    if (rate >= 0.6) return 'text-gain';
    if (rate >= 0.4) return 'text-foreground';
    return 'text-loss';
});

const winRateLabel = computed(() => {
    const rate = props.result.win_rate;
    if (!rate) return t('alerts.backtest.no_data');
    if (rate >= 0.6) return t('alerts.backtest.rating.good');
    if (rate >= 0.4) return t('alerts.backtest.rating.moderate');
    return t('alerts.backtest.rating.poor');
});
</script>

<template>
    <!-- Compact View -->
    <div v-if="compact" class="flex items-center gap-4 rounded-lg border p-3">
        <div
            class="flex size-10 items-center justify-center rounded-full bg-muted"
        >
            <FlaskConical class="size-5 text-foreground" />
        </div>
        <div class="flex-1">
            <div class="flex items-center gap-2">
                <span class="font-medium"
                    >{{ result.trigger_count }}
                    {{ t('alerts.backtest.triggers') }}</span
                >
                <Badge
                    v-if="result.win_rate"
                    :variant="result.win_rate >= 0.5 ? 'default' : 'secondary'"
                >
                    {{ (result.win_rate * 100).toFixed(0) }}%
                    {{ t('alerts.backtest.win_rate') }}
                </Badge>
            </div>
            <p class="text-sm text-muted-foreground">
                {{
                    t('alerts.backtest.lookback', {
                        days: result.lookback_days,
                    })
                }}
            </p>
        </div>
        <div class="text-end">
            <p
                class="font-medium"
                :class="{
                    'text-gain': isPositive(result.avg_return_1d),
                    'text-loss': isNegative(result.avg_return_1d),
                }"
            >
                {{ formatReturn(result.avg_return_1d) }}
            </p>
            <p class="text-xs text-muted-foreground">
                {{ t('alerts.backtest.avg_1d') }}
            </p>
        </div>
    </div>

    <!-- Full View -->
    <Card v-else>
        <CardHeader>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <FlaskConical class="size-5 text-foreground" />
                    <CardTitle>{{ t('alerts.backtest.title') }}</CardTitle>
                </div>
                <Badge variant="outline">
                    {{ formatDate(result.completed_at) }}
                </Badge>
            </div>
            <CardDescription>
                {{
                    t('alerts.backtest.lookback', {
                        days: result.lookback_days,
                    })
                }}
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Key Metrics -->
            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-md bg-muted/50 p-4 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <Target class="size-4 text-muted-foreground" />
                    </div>
                    <p class="mt-2 text-2xl font-bold tabular-nums">
                        {{ result.trigger_count }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('alerts.backtest.triggers') }}
                    </p>
                </div>

                <div class="rounded-md bg-muted/50 p-4 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <component
                            :is="
                                isPositive(result.avg_return_1d)
                                    ? TrendingUp
                                    : TrendingDown
                            "
                            class="size-4"
                            :class="{
                                'text-gain': isPositive(result.avg_return_1d),
                                'text-loss': isNegative(result.avg_return_1d),
                            }"
                        />
                    </div>
                    <p
                        class="mt-2 text-2xl font-bold tabular-nums"
                        :class="{
                            'text-gain': isPositive(result.avg_return_1d),
                            'text-loss': isNegative(result.avg_return_1d),
                        }"
                    >
                        {{ formatReturn(result.avg_return_1d) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('alerts.backtest.avg_1d') }}
                    </p>
                </div>

                <div class="rounded-md bg-muted/50 p-4 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <Calendar class="size-4 text-muted-foreground" />
                    </div>
                    <p
                        class="mt-2 text-2xl font-bold tabular-nums"
                        :class="{
                            'text-gain': isPositive(result.avg_return_1w),
                            'text-loss': isNegative(result.avg_return_1w),
                        }"
                    >
                        {{ formatReturn(result.avg_return_1w) }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('alerts.backtest.avg_1w') }}
                    </p>
                </div>

                <div class="rounded-md bg-muted/50 p-4 text-center">
                    <div class="flex items-center justify-center gap-1">
                        <Activity class="size-4 text-muted-foreground" />
                    </div>
                    <p
                        class="mt-2 text-2xl font-bold tabular-nums"
                        :class="winRateColor"
                    >
                        {{
                            result.win_rate
                                ? `${(result.win_rate * 100).toFixed(0)}%`
                                : '-'
                        }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('alerts.backtest.win_rate') }}
                    </p>
                </div>
            </div>

            <!-- Win Rate Progress -->
            <div v-if="result.win_rate" class="space-y-2">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">{{
                        t('alerts.backtest.performance')
                    }}</span>
                    <span :class="winRateColor">{{ winRateLabel }}</span>
                </div>
                <Progress :model-value="result.win_rate * 100" />
            </div>

            <!-- Recent Triggers -->
            <div
                v-if="result.triggers && result.triggers.length > 0"
                class="space-y-3"
            >
                <h4 class="text-sm font-medium">
                    {{ t('alerts.backtest.recent_triggers') }}
                </h4>
                <div class="max-h-48 space-y-2 overflow-y-auto">
                    <div
                        v-for="(trigger, index) in result.triggers.slice(0, 5)"
                        :key="index"
                        class="flex items-center justify-between rounded-lg border p-2 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-muted-foreground">{{
                                formatDate(trigger.date)
                            }}</span>
                            <span
                                >@
                                {{
                                    trigger.trigger_price.toLocaleString(locale)
                                }}</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                :class="{
                                    'text-gain': trigger.performance['1d'] > 0,
                                    'text-loss': trigger.performance['1d'] < 0,
                                }"
                            >
                                {{ formatReturn(trigger.performance['1d']) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- No Triggers Message -->
            <div v-else class="py-8 text-center text-muted-foreground">
                <FlaskConical class="mx-auto size-8 opacity-50" />
                <p class="mt-2">{{ t('alerts.backtest.no_triggers') }}</p>
            </div>
        </CardContent>
    </Card>
</template>
