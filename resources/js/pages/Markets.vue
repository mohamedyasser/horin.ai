<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GuestLayout from '@/layouts/GuestLayout.vue';
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
import {
    Search,
    ChevronDown,
    TrendingUp,
    Clock,
    Flame,
    Building2,
    ArrowRight,
} from 'lucide-vue-next';
import { usePredictionFormatters } from '@/composables/usePredictionFormatters';
import type { MarketPreview } from '@/types';

const { t } = useI18n();
const { getStatusColor } = usePredictionFormatters();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
    markets: MarketPreview[];
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// State
const searchQuery = ref('');
const sortBy = ref<'alphabetical' | 'predictions'>('predictions');
const selectedCountry = ref<string | null>(null);

// Get unique countries from backend data
const countries = computed(() => {
    const uniqueCountries = [...new Set(props.markets.map((m) => m.country.name))];
    return uniqueCountries.sort();
});

// Computed
const filteredMarkets = computed(() => {
    let result = [...props.markets];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (m) =>
                m.code.toLowerCase().includes(query) ||
                m.name.toLowerCase().includes(query) ||
                m.country.name.toLowerCase().includes(query)
        );
    }

    if (selectedCountry.value) {
        result = result.filter((m) => m.country.name === selectedCountry.value);
    }

    // Sort
    if (sortBy.value === 'alphabetical') {
        result.sort((a, b) => a.name.localeCompare(b.name));
    } else {
        result.sort((a, b) => b.predictionCount - a.predictionCount);
    }

    return result;
});

const topMarkets = computed(() =>
    [...props.markets].sort((a, b) => b.predictionCount - a.predictionCount).slice(0, 3)
);

const trendingMarket = computed(() =>
    props.markets.length > 0
        ? [...props.markets].sort((a, b) => b.predictionCount - a.predictionCount)[0]
        : null
);

const recentlyUpdatedMarkets = computed(() =>
    props.markets.filter((m) => m.isOpen).slice(0, 3)
);
</script>

<template>
    <Head :title="t('markets.title')">
        <meta name="description" :content="t('meta.markets')">
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <!-- Hero Section -->
        <section class="border-b border-border/40 bg-muted/30">
            <div class="mx-auto max-w-7xl px-4 py-12 text-center">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                    {{ t('markets.title') }}
                </h1>
                <p class="mt-3 text-lg text-muted-foreground">
                    {{ t('markets.subtitle') }}
                </p>

                <!-- Search Bar -->
                <div class="relative mx-auto mt-8 max-w-xl">
                    <Search class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('markets.searchPlaceholder')"
                        class="h-12 ps-10 text-base"
                    />
                </div>
            </div>
        </section>

        <!-- Filter Bar -->
        <section class="border-b border-border/40">
            <div class="mx-auto max-w-7xl px-4 py-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <!-- Country Filter -->
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            :variant="selectedCountry === null ? 'default' : 'outline'"
                            size="sm"
                            @click="selectedCountry = null"
                        >
                            {{ t('markets.allCountries') }}
                        </Button>
                        <Button
                            v-for="country in countries"
                            :key="country"
                            :variant="selectedCountry === country ? 'default' : 'outline'"
                            size="sm"
                            @click="selectedCountry = country"
                        >
                            {{ country }}
                        </Button>
                    </div>

                    <!-- Sort Dropdown -->
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" size="sm">
                                {{ t('markets.sortBy') }}
                                <ChevronDown class="ms-1 size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @click="sortBy = 'predictions'">
                                {{ t('markets.sortPredictions') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="sortBy = 'alphabetical'">
                                {{ t('markets.sortAlphabetical') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </section>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="grid gap-8 lg:grid-cols-4">
                <!-- Markets Grid -->
                <div class="lg:col-span-3">
                    <!-- Markets Cards -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Card
                            v-for="market in filteredMarkets"
                            :key="market.id"
                            class="group transition-shadow hover:shadow-md"
                        >
                            <CardHeader class="pb-3">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <Building2 class="size-5" />
                                        </div>
                                        <div>
                                            <CardTitle class="text-lg">{{ market.code }}</CardTitle>
                                            <p class="text-sm text-muted-foreground">
                                                {{ market.name }}
                                            </p>
                                        </div>
                                    </div>
                                    <span
                                        :class="getStatusColor(market.isOpen)"
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{ market.isOpen ? t('markets.open') : t('markets.closed') }}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-muted-foreground">{{ t('markets.country') }}</p>
                                        <p class="font-medium">{{ market.country.name }}</p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground">{{ t('markets.assets') }}</p>
                                        <p class="font-medium">{{ market.assetCount }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between border-t border-border pt-4">
                                    <div>
                                        <p class="text-2xl font-bold">{{ market.predictionCount }}</p>
                                        <p class="text-xs text-muted-foreground">{{ t('markets.predictions') }}</p>
                                    </div>
                                    <Button as-child size="sm">
                                        <LocalizedLink :href="`/markets/${market.code}`">
                                            {{ t('markets.viewPredictions') }}
                                            <ArrowRight class="ms-1 size-4 rtl:rotate-180" />
                                        </LocalizedLink>
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="filteredMarkets.length === 0"
                        class="flex flex-col items-center justify-center py-12 text-center"
                    >
                        <Search class="size-12 text-muted-foreground/50" />
                        <p class="mt-4 text-muted-foreground">
                            {{ t('markets.noResults') }}
                        </p>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Top Markets -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <TrendingUp class="size-4 text-green-500" />
                                {{ t('markets.topMarkets') }}
                            </CardTitle>
                            <p class="text-xs text-muted-foreground">
                                {{ t('markets.topMarketsDesc') }}
                            </p>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="market in topMarkets"
                                :key="market.id"
                                class="flex items-center justify-between"
                            >
                                <div>
                                    <span class="font-medium">{{ market.code }}</span>
                                    <span class="ms-1 text-xs text-muted-foreground">
                                        {{ market.country.name }}
                                    </span>
                                </div>
                                <span class="text-sm font-medium text-green-600 dark:text-green-400">
                                    {{ market.predictionCount }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Trending Market -->
                    <Card v-if="trendingMarket">
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Flame class="size-4 text-orange-500" />
                                {{ t('markets.trendingMarket') }}
                            </CardTitle>
                            <p class="text-xs text-muted-foreground">
                                {{ t('markets.trendingMarketDesc') }}
                            </p>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="font-medium">{{ trendingMarket.code }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ trendingMarket.name }}
                                    </p>
                                </div>
                                <Button as-child variant="outline" size="sm">
                                    <LocalizedLink :href="`/markets/${trendingMarket.code}`">
                                        {{ t('markets.viewPredictions') }}
                                    </LocalizedLink>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Recently Updated -->
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="flex items-center gap-2 text-base">
                                <Clock class="size-4 text-blue-500" />
                                {{ t('markets.recentlyUpdated') }}
                            </CardTitle>
                            <p class="text-xs text-muted-foreground">
                                {{ t('markets.recentlyUpdatedDesc') }}
                            </p>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="market in recentlyUpdatedMarkets"
                                :key="market.id"
                                class="flex items-center justify-between"
                            >
                                <div>
                                    <span class="font-medium">{{ market.code }}</span>
                                    <span class="ms-1 text-xs text-muted-foreground">
                                        {{ market.country.name }}
                                    </span>
                                </div>
                                <span
                                    :class="getStatusColor(market.isOpen)"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ market.isOpen ? t('markets.open') : t('markets.closed') }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
