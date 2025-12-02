<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GuestLayout from '@/layouts/GuestLayout.vue';
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
    Globe,
    ArrowRight,
} from 'lucide-vue-next';
import type { Market, MarketCode } from '@/types';

const { t } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

// Mock markets data
const mockMarkets: Market[] = [
    {
        id: 1,
        code: 'EGX',
        name: 'EGX',
        full_name: 'Egyptian Exchange',
        country: 'Egypt',
        timezone: 'Africa/Cairo',
        trading_hours: { open: '10:00', close: '14:30' },
        status: 'closed',
        prediction_count: 156,
    },
    {
        id: 2,
        code: 'TASI',
        name: 'TASI',
        full_name: 'Tadawul',
        country: 'Saudi Arabia',
        timezone: 'Asia/Riyadh',
        trading_hours: { open: '10:00', close: '15:00' },
        status: 'open',
        prediction_count: 243,
    },
    {
        id: 3,
        code: 'ADX',
        name: 'ADX',
        full_name: 'Abu Dhabi Securities Exchange',
        country: 'UAE',
        timezone: 'Asia/Dubai',
        trading_hours: { open: '10:00', close: '14:00' },
        status: 'open',
        prediction_count: 89,
    },
    {
        id: 4,
        code: 'DFM',
        name: 'DFM',
        full_name: 'Dubai Financial Market',
        country: 'UAE',
        timezone: 'Asia/Dubai',
        trading_hours: { open: '10:00', close: '14:00' },
        status: 'open',
        prediction_count: 112,
    },
    {
        id: 5,
        code: 'KW',
        name: 'KW',
        full_name: 'Boursa Kuwait',
        country: 'Kuwait',
        timezone: 'Asia/Kuwait',
        trading_hours: { open: '09:00', close: '12:40' },
        status: 'closed',
        prediction_count: 67,
    },
    {
        id: 6,
        code: 'QA',
        name: 'QA',
        full_name: 'Qatar Stock Exchange',
        country: 'Qatar',
        timezone: 'Asia/Qatar',
        trading_hours: { open: '09:30', close: '13:15' },
        status: 'closed',
        prediction_count: 54,
    },
    {
        id: 7,
        code: 'BH',
        name: 'BH',
        full_name: 'Bahrain Bourse',
        country: 'Bahrain',
        timezone: 'Asia/Bahrain',
        trading_hours: { open: '09:30', close: '13:00' },
        status: 'closed',
        prediction_count: 32,
    },
];

// State
const searchQuery = ref('');
const sortBy = ref<'alphabetical' | 'predictions'>('predictions');
const selectedCountry = ref<string | null>(null);

// Get unique countries
const countries = computed(() => {
    const uniqueCountries = [...new Set(mockMarkets.map((m) => m.country))];
    return uniqueCountries.sort();
});

// Computed
const filteredMarkets = computed(() => {
    let result = [...mockMarkets];

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (m) =>
                m.code.toLowerCase().includes(query) ||
                m.full_name.toLowerCase().includes(query) ||
                m.country.toLowerCase().includes(query)
        );
    }

    if (selectedCountry.value) {
        result = result.filter((m) => m.country === selectedCountry.value);
    }

    // Sort
    if (sortBy.value === 'alphabetical') {
        result.sort((a, b) => a.full_name.localeCompare(b.full_name));
    } else {
        result.sort((a, b) => b.prediction_count - a.prediction_count);
    }

    return result;
});

const topMarkets = computed(() =>
    [...mockMarkets].sort((a, b) => b.prediction_count - a.prediction_count).slice(0, 3)
);

const trendingMarket = computed(() =>
    mockMarkets.find((m) => m.code === 'TASI')
);

const recentlyUpdatedMarkets = computed(() =>
    mockMarkets.filter((m) => m.status === 'open').slice(0, 3)
);

// Helpers
const getStatusColor = (status: string) => {
    return status === 'open'
        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
        : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400';
};

const getMarketName = (code: MarketCode) => {
    return t(`markets.names.${code}`);
};

const getCountryKey = (country: string): string => {
    const countryMap: Record<string, string> = {
        'Egypt': 'egypt',
        'Saudi Arabia': 'saudiArabia',
        'UAE': 'uae',
        'Kuwait': 'kuwait',
        'Qatar': 'qatar',
        'Bahrain': 'bahrain',
    };
    return countryMap[country] || country.toLowerCase();
};

const getCountryName = (country: string) => {
    const key = getCountryKey(country);
    return t(`markets.countries.${key}`);
};
</script>

<template>
    <Head :title="t('markets.title')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
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
                            {{ getCountryName(country) }}
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
                                                {{ getMarketName(market.code) }}
                                            </p>
                                        </div>
                                    </div>
                                    <span
                                        :class="getStatusColor(market.status)"
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    >
                                        {{ market.status === 'open' ? t('markets.open') : t('markets.closed') }}
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <p class="text-muted-foreground">{{ t('markets.countries.' + getCountryKey(market.country)) }}</p>
                                        <p class="font-medium flex items-center gap-1">
                                            <Globe class="size-3.5" />
                                            {{ market.timezone.split('/')[1] }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-muted-foreground">{{ t('markets.tradingHours') }}</p>
                                        <p class="font-medium">
                                            {{ market.trading_hours.open }} - {{ market.trading_hours.close }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between border-t border-border pt-4">
                                    <div>
                                        <p class="text-2xl font-bold">{{ market.prediction_count }}</p>
                                        <p class="text-xs text-muted-foreground">{{ t('markets.predictions') }}</p>
                                    </div>
                                    <Button as-child size="sm">
                                        <Link :href="`/markets/${market.id}`">
                                            {{ t('markets.viewPredictions') }}
                                            <ArrowRight class="ms-1 size-4" />
                                        </Link>
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
                                        {{ getCountryName(market.country) }}
                                    </span>
                                </div>
                                <span class="text-sm font-medium text-green-600 dark:text-green-400">
                                    {{ market.prediction_count }}
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
                                        {{ getMarketName(trendingMarket.code) }}
                                    </p>
                                </div>
                                <Button as-child variant="outline" size="sm">
                                    <Link :href="`/markets/${trendingMarket.id}`">
                                        {{ t('markets.viewPredictions') }}
                                    </Link>
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
                                        {{ getCountryName(market.country) }}
                                    </span>
                                </div>
                                <span
                                    :class="getStatusColor(market.status)"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                >
                                    {{ market.status === 'open' ? t('markets.open') : t('markets.closed') }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </GuestLayout>
</template>
