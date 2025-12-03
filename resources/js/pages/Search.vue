<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, router, Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import GuestLayout from '@/layouts/GuestLayout.vue';
import {
    Search,
    X,
    Clock,
    Loader2,
} from 'lucide-vue-next';
import type { SearchResult, PaginationMeta } from '@/types';

const { t } = useI18n();

interface Props {
    canLogin: boolean;
    canRegister: boolean;
    query?: string;
    results?: {
        data: SearchResult[];
        meta: PaginationMeta;
    };
    totalCount?: number;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
    query: '',
});

// State - initialize from props
const searchQuery = ref(props.query);
const isSearching = ref(false);

// Recent searches (stored in memory)
const recentSearches = ref<string[]>(['COMI', 'Aramco', 'Banking', 'EGX']);

// Computed - use backend results
const searchResults = computed(() => props.results?.data ?? []);
const resultsMeta = computed(() => props.results?.meta);
const hasSearched = computed(() => !!props.query);

// Highlight matching text
const highlightMatch = (text: string, query: string) => {
    if (!query) return text;
    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const regex = new RegExp(`(${escapedQuery})`, 'gi');
    return text.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-900 rounded px-0.5">$1</mark>');
};

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;
watch(searchQuery, (newVal) => {
    if (newVal) {
        isSearching.value = true;
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            router.visit(route('search', { q: newVal }), {
                preserveState: true,
                only: ['results', 'totalCount', 'query'],
                onFinish: () => {
                    isSearching.value = false;
                },
            });
        }, 300);
    } else {
        isSearching.value = false;
    }
});

// Handle search from recent
const searchFromRecent = (query: string) => {
    searchQuery.value = query;
};

// Clear recent searches
const clearRecentSearches = () => {
    recentSearches.value = [];
};

// Navigate to asset detail
const goToAsset = (id: string) => {
    router.visit(route('assets.show', { asset: id }));
};

// Format price change
const formatPriceChange = (pcp: string | undefined) => {
    if (!pcp) return '-';
    const value = parseFloat(pcp);
    const sign = value >= 0 ? '+' : '';
    return `${sign}${value.toFixed(2)}%`;
};

// Get change color
const getChangeColor = (pcp: string | undefined) => {
    if (!pcp) return 'text-muted-foreground';
    const value = parseFloat(pcp);
    if (value > 0) return 'text-green-600 dark:text-green-400';
    if (value < 0) return 'text-red-600 dark:text-red-400';
    return 'text-muted-foreground';
};
</script>

<template>
    <Head :title="t('search.title')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
            <!-- Search Section -->
            <section class="border-b border-border/40 bg-muted/30">
                <div class="mx-auto max-w-4xl px-4 py-12">
                    <div class="text-center mb-8">
                        <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                            {{ t('search.title') }}
                        </h1>
                        <p class="mt-3 text-lg text-muted-foreground">
                            {{ t('search.subtitle') }}
                        </p>
                    </div>

                    <!-- Search Bar -->
                    <div class="relative">
                        <Search class="absolute start-4 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('search.searchPlaceholder')"
                            class="h-14 ps-12 pe-12 text-lg rounded-xl shadow-sm"
                        />
                        <div v-if="isSearching" class="absolute end-4 top-1/2 -translate-y-1/2">
                            <Loader2 class="size-5 animate-spin text-muted-foreground" />
                        </div>
                        <button
                            v-else-if="searchQuery"
                            @click="searchQuery = ''"
                            class="absolute end-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                        >
                            <X class="size-5" />
                        </button>
                    </div>
                </div>
            </section>

            <!-- Results Section -->
            <div class="mx-auto max-w-7xl px-4 py-8">
                <Deferred data="results">
                    <template #fallback>
                        <!-- Loading skeleton -->
                        <div class="rounded-lg border border-border">
                            <div class="animate-pulse space-y-4 p-4">
                                <div v-for="i in 10" :key="i" class="flex items-center gap-4">
                                    <div class="h-4 w-20 rounded bg-muted" />
                                    <div class="h-4 flex-1 rounded bg-muted" />
                                    <div class="h-4 w-16 rounded bg-muted" />
                                    <div class="h-4 w-16 rounded bg-muted" />
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Results Count -->
                    <div v-if="hasSearched && searchResults.length > 0" class="mb-4 flex items-center justify-between">
                        <p class="text-sm text-muted-foreground">
                            {{ t('search.resultsCount', { count: props.totalCount ?? searchResults.length }) }}
                        </p>
                    </div>

                    <!-- Results Table -->
                    <div v-if="searchResults.length > 0" class="rounded-lg border border-border">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-border bg-muted/50">
                                        <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                            {{ t('search.table.symbol') }}
                                        </th>
                                        <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                            {{ t('search.table.name') }}
                                        </th>
                                        <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                            {{ t('search.table.market') }}
                                        </th>
                                        <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                                            {{ t('search.table.sector') }}
                                        </th>
                                        <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                            {{ t('search.table.lastPrice') }}
                                        </th>
                                        <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                                            {{ t('search.table.change') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="result in searchResults"
                                        :key="result.id"
                                        class="border-b border-border last:border-0 hover:bg-muted/30 transition-colors cursor-pointer"
                                        @click="goToAsset(result.id)"
                                    >
                                        <td class="px-4 py-3 font-medium">
                                            <span v-html="highlightMatch(result.symbol, searchQuery)" />
                                        </td>
                                        <td class="px-4 py-3 text-sm text-muted-foreground">
                                            <span v-html="highlightMatch(result.name, searchQuery)" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                v-if="result.market"
                                                class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs font-medium"
                                            >
                                                {{ result.market.code }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-muted-foreground">
                                            {{ result.sector?.name ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-end font-medium">
                                            {{ result.latestPrice?.last.toFixed(2) ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <span :class="getChangeColor(result.latestPrice?.pcp)" class="font-semibold">
                                                {{ formatPriceChange(result.latestPrice?.pcp) }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="resultsMeta && resultsMeta.lastPage > 1" class="mt-4 flex items-center justify-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="resultsMeta.currentPage <= 1"
                        >
                            {{ t('common.previous') }}
                        </Button>
                        <span class="text-sm text-muted-foreground">
                            {{ resultsMeta.currentPage }} / {{ resultsMeta.lastPage }}
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="resultsMeta.currentPage >= resultsMeta.lastPage"
                        >
                            {{ t('common.next') }}
                        </Button>
                    </div>

                    <!-- Empty State: No Results -->
                    <div
                        v-if="hasSearched && searchResults.length === 0"
                        class="flex flex-col items-center justify-center py-16 text-center"
                    >
                        <Search class="size-16 text-muted-foreground/30" />
                        <h3 class="mt-4 text-lg font-semibold">
                            {{ t('search.noResults') }}
                        </h3>
                        <p class="mt-2 text-muted-foreground">
                            {{ t('search.noResultsSuggestion') }}
                        </p>
                    </div>
                </Deferred>

                <!-- Empty State: Start Typing -->
                <div
                    v-if="!hasSearched"
                    class="py-12"
                >
                    <!-- Start typing message -->
                    <div class="flex flex-col items-center justify-center py-8 text-center">
                        <Search class="size-12 text-muted-foreground/30" />
                        <p class="mt-4 text-muted-foreground">
                            {{ t('search.startTyping') }}
                        </p>
                    </div>

                    <!-- Recent Searches -->
                    <div v-if="recentSearches.length > 0" class="mx-auto max-w-xl mt-8">
                        <Card>
                            <CardHeader class="pb-3">
                                <div class="flex items-center justify-between">
                                    <CardTitle class="flex items-center gap-2 text-base">
                                        <Clock class="size-4 text-muted-foreground" />
                                        {{ t('search.recentSearches') }}
                                    </CardTitle>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="clearRecentSearches"
                                    >
                                        {{ t('search.clearRecentSearches') }}
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-for="recent in recentSearches"
                                        :key="recent"
                                        variant="outline"
                                        size="sm"
                                        @click="searchFromRecent(recent)"
                                    >
                                        {{ recent }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
    </GuestLayout>
</template>
