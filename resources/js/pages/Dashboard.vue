<script setup lang="ts">
import AssetDisplay from '@/components/AssetDisplay.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    Bell,
    Eye,
    TrendingUp,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();
const { formatGain } = usePredictionFormatters();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: t('dashboard.title'),
        href: dashboard().url,
    },
]);

interface Props {
    stats?: {
        portfolioValue: number;
        dailyPnl: number;
        dailyPnlPercent: number;
        activeAlerts: number;
        watchlistCount: number;
    };
    watchlist?: Array<{
        id: number;
        symbol: string;
        name: string;
        marketCode: string;
        currentPrice: number;
        priceChange: number;
        priceChangePercent: number;
    }>;
    recentAlerts?: Array<{
        id: number;
        type: string;
        assetSymbol: string;
        message: string;
        triggeredAt: string;
    }>;
    recentPredictions?: Array<{
        id: number;
        asset: { symbol: string; name: string; market?: { code: string } };
        predictedPrice: number;
        expectedGainPercent: number;
        confidence: number;
        horizonLabel: string;
    }>;
    recentRecommendations?: Array<{
        id: number;
        asset: { symbol: string; name: string; market?: { code: string } };
        action: string;
        score: number;
    }>;
}

const props = defineProps<Props>();

const stats = computed(
    () =>
        props.stats ?? {
            portfolioValue: 0,
            dailyPnl: 0,
            dailyPnlPercent: 0,
            activeAlerts: 0,
            watchlistCount: 0,
        },
);
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Welcome -->
            <div>
                <h1 class="text-lg font-semibold">
                    {{ t('dashboard.welcome') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        new Date().toLocaleDateString(
                            locale === 'ar' ? 'ar-EG' : 'en-US',
                            {
                                weekday: 'long',
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                            },
                        )
                    }}
                </p>
            </div>

            <!-- KPI Row -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ t('dashboard.portfolioValue') }}
                            </p>
                            <BarChart3 class="size-4 text-muted-foreground" />
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums">
                            {{ stats.portfolioValue.toLocaleString() }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ t('dashboard.dailyPnl') }}
                            </p>
                            <Activity class="size-4 text-muted-foreground" />
                        </div>
                        <p
                            class="mt-2 text-2xl font-bold tabular-nums"
                            :class="
                                stats.dailyPnl >= 0 ? 'text-gain' : 'text-loss'
                            "
                        >
                            <span dir="ltr">
                                {{ stats.dailyPnl >= 0 ? '+' : ''
                                }}{{ stats.dailyPnl.toLocaleString() }}
                            </span>
                        </p>
                        <p
                            class="mt-1 text-xs tabular-nums"
                            :class="
                                stats.dailyPnlPercent >= 0
                                    ? 'text-gain'
                                    : 'text-loss'
                            "
                        >
                            <span dir="ltr">
                                {{ stats.dailyPnlPercent >= 0 ? '+' : ''
                                }}{{ stats.dailyPnlPercent.toFixed(2) }}%
                            </span>
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ t('dashboard.activeAlerts') }}
                            </p>
                            <Bell class="size-4 text-muted-foreground" />
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums">
                            {{ stats.activeAlerts }}
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex items-center justify-between">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                {{ t('dashboard.watchlistCount') }}
                            </p>
                            <Eye class="size-4 text-muted-foreground" />
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums">
                            {{ stats.watchlistCount }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Two Column: Watchlist + Recent Alerts -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Watchlist -->
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">
                            {{ t('dashboard.watchlist') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="props.watchlist?.length"
                            class="overflow-x-auto"
                        >
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-border">
                                        <th
                                            class="pb-2 text-start text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{ t('home.table.symbol') }}
                                        </th>
                                        <th
                                            class="pb-2 text-end text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{ t('home.table.current') }}
                                        </th>
                                        <th
                                            class="pb-2 text-end text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{ t('home.table.gainPercent') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="item in props.watchlist"
                                        :key="item.id"
                                        class="border-b border-border/50 last:border-0"
                                    >
                                        <td class="py-2.5">
                                            <AssetDisplay
                                                :symbol="item.symbol"
                                                :market-code="item.marketCode"
                                                :show-name="false"
                                                :show-logo="false"
                                                size="sm"
                                            />
                                        </td>
                                        <td
                                            dir="ltr"
                                            class="py-2.5 text-end text-sm tabular-nums"
                                        >
                                            {{ item.currentPrice.toFixed(2) }}
                                        </td>
                                        <td class="py-2.5 text-end">
                                            <span
                                                dir="ltr"
                                                class="inline-flex items-center gap-0.5 text-sm font-medium tabular-nums"
                                                :class="
                                                    item.priceChangePercent >= 0
                                                        ? 'text-gain'
                                                        : 'text-loss'
                                                "
                                            >
                                                <ArrowUpRight
                                                    v-if="
                                                        item.priceChangePercent >=
                                                        0
                                                    "
                                                    class="size-3.5"
                                                />
                                                <ArrowDownRight
                                                    v-else
                                                    class="size-3.5"
                                                />
                                                {{
                                                    Math.abs(
                                                        item.priceChangePercent,
                                                    ).toFixed(2)
                                                }}%
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center py-8 text-center"
                        >
                            <Eye class="size-8 text-muted-foreground/50" />
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('dashboard.emptyWatchlist') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Recent Alerts -->
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">
                            {{ t('dashboard.recentAlerts') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="props.recentAlerts?.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="alert in props.recentAlerts"
                                :key="alert.id"
                                class="flex items-start gap-3 text-sm"
                            >
                                <Bell
                                    class="mt-0.5 size-3.5 shrink-0 text-muted-foreground"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">
                                        {{ alert.assetSymbol }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ alert.message }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center py-8 text-center"
                        >
                            <Bell class="size-8 text-muted-foreground/50" />
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('dashboard.noRecentAlerts') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Bottom: Recent Predictions + Recommendations -->
            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">
                            {{ t('dashboard.recentPredictions') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="props.recentPredictions?.length"
                            class="space-y-2"
                        >
                            <LocalizedLink
                                v-for="prediction in props.recentPredictions"
                                :key="prediction.id"
                                :href="`/assets/${prediction.asset.symbol}`"
                                class="-mx-2 flex items-center justify-between rounded-md px-2 py-1.5 transition-colors duration-150 hover:bg-muted/50"
                            >
                                <AssetDisplay
                                    :symbol="prediction.asset.symbol"
                                    :market-code="prediction.asset.market?.code"
                                    :show-name="false"
                                    :show-logo="false"
                                    size="sm"
                                />
                                <span
                                    dir="ltr"
                                    class="text-sm font-medium tabular-nums"
                                    :class="
                                        prediction.expectedGainPercent >= 0
                                            ? 'text-gain'
                                            : 'text-loss'
                                    "
                                >
                                    {{
                                        formatGain(
                                            prediction.expectedGainPercent,
                                        )
                                    }}
                                </span>
                            </LocalizedLink>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center py-8 text-center"
                        >
                            <TrendingUp
                                class="size-8 text-muted-foreground/50"
                            />
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('dashboard.noPredictions') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle class="text-base font-semibold">
                            {{ t('dashboard.recentRecommendations') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="props.recentRecommendations?.length"
                            class="space-y-2"
                        >
                            <LocalizedLink
                                v-for="rec in props.recentRecommendations"
                                :key="rec.id"
                                :href="`/assets/${rec.asset.symbol}`"
                                class="-mx-2 flex items-center justify-between rounded-md px-2 py-1.5 transition-colors duration-150 hover:bg-muted/50"
                            >
                                <AssetDisplay
                                    :symbol="rec.asset.symbol"
                                    :market-code="rec.asset.market?.code"
                                    :show-name="false"
                                    :show-logo="false"
                                    size="sm"
                                />
                                <Badge
                                    :variant="
                                        rec.action === 'buy' ||
                                        rec.action === 'strong_buy'
                                            ? 'gain'
                                            : rec.action === 'sell' ||
                                                rec.action === 'strong_sell'
                                              ? 'loss'
                                              : 'outline'
                                    "
                                >
                                    {{ rec.action }}
                                </Badge>
                            </LocalizedLink>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center py-8 text-center"
                        >
                            <Activity class="size-8 text-muted-foreground/50" />
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t('dashboard.noRecommendations') }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
