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
import GuestLayout from '@/layouts/GuestLayout.vue';
import {
    Search,
    ChevronDown,
    TrendingUp,
    Target,
    ArrowUpRight,
    ArrowDownRight,
} from 'lucide-vue-next';
import type { PredictionListItem, PaginationMeta } from '@/types';

const { t, locale } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
    predictions?: {
        data: PredictionListItem[];
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
const predictions = computed(() => props.predictions?.data ?? []);
const predictionsMeta = computed(() => props.predictions?.meta);

// Filter predictions client-side for search
const filteredPredictions = computed(() => {
    let result = [...predictions.value];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (p) =>
                p.asset.symbol.toLowerCase().includes(query) ||
                p.asset.name.toLowerCase().includes(query)
        );
    }

    return result;
});

// Derived data for sidebar
const topGainers = computed(() =>
    [...predictions.value]
        .sort((a, b) => b.expectedGainPercent - a.expectedGainPercent)
        .slice(0, 5)
);

const mostConfident = computed(() =>
    [...predictions.value]
        .sort((a, b) => b.confidence - a.confidence)
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
</script>

<template>
    <Head :title="t('predictions.title')">
        <meta name="description" :content="t('meta.predictions')">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
            <!-- Hero Section -->
            <section class="border-b border-border/40 bg-muted/30">
                <div class="mx-auto max-w-7xl px-4 py-12 text-center">
                    <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        {{ t('predictions.title') }}
                    </h1>
                    <p class="mt-3 text-lg text-muted-foreground">
                        {{ t('predictions.subtitle') }}
                    </p>

                    <!-- Search Bar -->
                    <div class="relative mx-auto mt-8 max-w-xl">
                        <Search class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('predictions.searchPlaceholder')"
                            class="h-12 ps-10 text-base"
                        />
                    </div>
                </div>
            </section>

            <!-- Filter Bar -->
            <section class="border-b border-border/40">
                <div class="mx-auto max-w-7xl px-4 py-4">
                    <div class="flex flex-wrap items-center justify-end gap-4">
                        <!-- Sort Dropdown -->
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm">
                                    {{ t('predictions.sortBy') }}
                                    <ChevronDown class="ms-1 size-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem>
                                    {{ t('predictions.highestGain') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem>
                                    {{ t('predictions.highestConfidence') }}
                                </DropdownMenuItem>
                                <DropdownMenuItem>
                                    {{ t('predictions.newest') }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>
            </section>

            <!-- Main Content -->
            <div class="mx-auto max-w-7xl px-4 py-8">
                <div class="grid gap-8 lg:grid-cols-4">
                    <!-- Predictions Table -->
                    <div class="lg:col-span-3">
                        <Deferred data="predictions">
                            <template #fallback>
                                <!-- Loading skeleton -->
                                <div class="rounded-lg border border-border">
                                    <div class="animate-pulse space-y-4 p-4">
                                        <div v-for="i in 10" :key="i" class="flex items-center gap-4">
                                            <div class="h-10 w-20 rounded bg-muted" />
                                            <div class="h-4 flex-1 rounded bg-muted" />
                                            <div class="h-4 w-16 rounded bg-muted" />
                                            <div class="h-4 w-16 rounded bg-muted" />
                                            <div class="h-4 w-16 rounded bg-muted" />
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
                                                    {{ t('predictions.table.symbol') }}
                                                </th>
                                                <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.name') }}
                                                </th>
                                                <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.market') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.lastPrice') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.predictedPrice') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.gainPercent') }}
                                                </th>
                                                <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.horizon') }}
                                                </th>
                                                <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.confidence') }}
                                                </th>
                                                <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                                                    {{ t('predictions.table.action') }}
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
                                                        <span
                                                            v-if="prediction.asset.sector"
                                                            class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground"
                                                        >
                                                            {{ prediction.asset.sector.name }}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-muted-foreground">
                                                    {{ prediction.asset.name }}
                                                </td>
                                                <td class="px-4 py-3 text-center">
                                                    <span
                                                        v-if="prediction.asset.market"
                                                        class="rounded bg-muted px-2 py-0.5 text-xs font-medium"
                                                    >
                                                        {{ prediction.asset.market.code }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-end text-sm">
                                                    {{ prediction.asset.currentPrice?.toFixed(2) ?? '-' }}
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
                                                <td class="px-4 py-3 text-center">
                                                    <Button
                                                        as-child
                                                        variant="ghost"
                                                        size="sm"
                                                        @click.stop
                                                    >
                                                        <LocalizedLink :href="`/assets/${prediction.asset.symbol}`">
                                                            {{ t('predictions.viewDetails') }}
                                                        </LocalizedLink>
                                                    </Button>
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
                                        {{ t('predictions.noResults') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="predictionsMeta && predictionsMeta.lastPage > 1" class="mt-4 flex items-center justify-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="predictionsMeta.currentPage <= 1"
                                >
                                    {{ t('common.previous') }}
                                </Button>
                                <span class="text-sm text-muted-foreground">
                                    {{ predictionsMeta.currentPage }} / {{ predictionsMeta.lastPage }}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="predictionsMeta.currentPage >= predictionsMeta.lastPage"
                                >
                                    {{ t('common.next') }}
                                </Button>
                            </div>
                        </Deferred>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Top Gainers -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <TrendingUp class="size-4 text-green-500" />
                                    {{ t('predictions.topGainers') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('predictions.topGainersDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <LocalizedLink
                                    v-for="prediction in topGainers"
                                    :key="prediction.id"
                                    :href="`/assets/${prediction.asset.symbol}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.asset.symbol }}</span>
                                        <span v-if="prediction.asset.market" class="ms-1 text-xs text-muted-foreground">
                                            {{ prediction.asset.market.code }}
                                        </span>
                                    </div>
                                    <span class="font-medium text-green-600 dark:text-green-400">
                                        {{ formatGain(prediction.expectedGainPercent) }}
                                    </span>
                                </LocalizedLink>
                            </CardContent>
                        </Card>

                        <!-- Most Confident -->
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="flex items-center gap-2 text-base">
                                    <Target class="size-4 text-blue-500" />
                                    {{ t('predictions.mostConfident') }}
                                </CardTitle>
                                <p class="text-xs text-muted-foreground">
                                    {{ t('predictions.mostConfidentDesc') }}
                                </p>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <LocalizedLink
                                    v-for="prediction in mostConfident"
                                    :key="prediction.id"
                                    :href="`/assets/${prediction.asset.symbol}`"
                                    class="flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors"
                                >
                                    <div>
                                        <span class="font-medium">{{ prediction.asset.symbol }}</span>
                                        <span v-if="prediction.asset.market" class="ms-1 text-xs text-muted-foreground">
                                            {{ prediction.asset.market.code }}
                                        </span>
                                    </div>
                                    <span :class="getConfidenceColor(prediction.confidence)" class="font-medium">
                                        {{ prediction.confidence }}%
                                    </span>
                                </LocalizedLink>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
    </GuestLayout>
</template>
