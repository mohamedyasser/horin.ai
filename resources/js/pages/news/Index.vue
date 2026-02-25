<script setup lang="ts">
import FilterButtonBar from '@/components/FilterButtonBar.vue';
import NewsCard from '@/components/news/NewsCard.vue';
import NewsFeatured from '@/components/news/NewsFeatured.vue';
import NewsListItem from '@/components/news/NewsListItem.vue';
import { Button } from '@/components/ui/button';
import { SearchableSelect } from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { useServerSearch } from '@/composables/useServerSearch';
import GuestLayout from '@/layouts/GuestLayout.vue';
import type { PaginationMeta } from '@/types';
import type {
    AssetNew,
    AssetNewListItem,
    NewsFilterOptions,
    NewsFilters,
} from '@/types/news';
import { Deferred, Head, router } from '@inertiajs/vue3';
import {
    LayoutGrid,
    List,
    Loader2,
    Newspaper,
    Search,
    SlidersHorizontal,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    canLogin: boolean;
    canRegister: boolean;
    featured: AssetNew | null;
    news: {
        data: AssetNewListItem[];
        meta: PaginationMeta;
    };
    filters: NewsFilters;
    filterOptions?: NewsFilterOptions;
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

const { t, locale } = useI18n();

// View mode (persisted in localStorage)
const viewMode = ref<'grid' | 'list'>(
    (typeof window !== 'undefined' &&
        (localStorage.getItem('newsViewMode') as 'grid' | 'list')) ||
        'grid',
);

watch(viewMode, (newMode) => {
    if (typeof window !== 'undefined') {
        localStorage.setItem('newsViewMode', newMode);
    }
});

// Server-side search
const { searchQuery, isSearching } = useServerSearch({
    initialValue: props.filters?.search,
    preserveParams: [
        'sentiment',
        'category',
        'action',
        'market_id',
        'sector_id',
        'country_id',
    ],
    only: ['news', 'featured', 'filters'],
});

// Filter state
const filterOpen = ref(false);
const selectedSentiment = ref<string | null>(props.filters?.sentiment ?? null);
const selectedCategory = ref<string | null>(props.filters?.category ?? null);
const selectedAction = ref<string | null>(props.filters?.action ?? null);
const selectedMarket = ref<string | null>(props.filters?.market_id ?? null);
const selectedSector = ref<string | null>(props.filters?.sector_id ?? null);

// Filter options
const sentimentOptions = computed(() => [
    { value: 'positive', label: t('news.sentiment.positive') },
    { value: 'negative', label: t('news.sentiment.negative') },
    { value: 'neutral', label: t('news.sentiment.neutral') },
]);

const actionOptions = computed(() => [
    { value: 'buy', label: t('news.action.buy') },
    { value: 'sell', label: t('news.action.sell') },
    { value: 'watch', label: t('news.action.watch') },
]);

const marketOptions = computed(() =>
    (props.filterOptions?.markets ?? []).map((m) => ({
        value: m.id,
        label: `${m.code} - ${m.name}`,
    })),
);

const sectorOptions = computed(() =>
    (props.filterOptions?.sectors ?? []).map((s) => ({
        value: s.id,
        label: s.name,
    })),
);

const categoryOptions = computed(() =>
    (props.filterOptions?.categories ?? []).map((c) => ({
        value: c,
        label: c,
    })),
);

// Count active filters
const activeFilterCount = computed(() => {
    let count = 0;
    if (selectedSentiment.value) count++;
    if (selectedCategory.value) count++;
    if (selectedAction.value) count++;
    if (selectedMarket.value) count++;
    if (selectedSector.value) count++;
    return count;
});

// Apply filters
const applyFilters = () => {
    const data: Record<string, string | undefined> = {};

    if (searchQuery.value) data.search = searchQuery.value;
    if (selectedSentiment.value) data.sentiment = selectedSentiment.value;
    if (selectedCategory.value) data.category = selectedCategory.value;
    if (selectedAction.value) data.action = selectedAction.value;
    if (selectedMarket.value) data.market_id = selectedMarket.value;
    if (selectedSector.value) data.sector_id = selectedSector.value;

    router.visit(window.location.pathname, {
        data,
        preserveState: true,
        preserveScroll: true,
        only: ['news', 'featured', 'filters'],
    });

    filterOpen.value = false;
};

// Clear all filters
const clearFilters = () => {
    selectedSentiment.value = null;
    selectedCategory.value = null;
    selectedAction.value = null;
    selectedMarket.value = null;
    selectedSector.value = null;
    searchQuery.value = '';
    applyFilters();
};

// Quick sentiment filter
const filterBySentiment = (sentiment: string | null) => {
    selectedSentiment.value = sentiment;
    applyFilters();
};

// Computed data
const newsList = computed(() => props.news?.data ?? []);
const newsMeta = computed(() => props.news?.meta);

// Pagination
const goToPage = (page: number) => {
    const currentParams = new URLSearchParams(window.location.search);
    const data: Record<string, string> = {};

    currentParams.forEach((value, key) => {
        data[key] = value;
    });

    data.page = String(page);

    router.visit(window.location.pathname, {
        data,
        preserveState: true,
        preserveScroll: true,
        only: ['news', 'filters'],
    });
};
</script>

<template>
    <Head :title="t('news.title')">
        <meta name="description" :content="t('news.metaDescription')" />
    </Head>

    <GuestLayout :can-login="props.canLogin" :can-register="props.canRegister">
        <!-- Hero Section -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-7xl px-6 pt-20 pb-12 text-center">
                <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">
                    {{ t('news.title') }}
                </h1>
                <p class="mt-3 text-lg text-muted-foreground">
                    {{ t('news.subtitle') }}
                </p>

                <!-- Search Bar -->
                <div class="relative mx-auto mt-8 max-w-xl">
                    <Search
                        v-if="!isSearching"
                        class="absolute start-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Loader2
                        v-else
                        class="absolute start-3 top-1/2 size-5 -translate-y-1/2 animate-spin text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('news.searchPlaceholder')"
                        class="h-12 ps-10 text-base"
                    />
                </div>
            </div>
        </section>

        <!-- Sentiment Filter Bar -->
        <section class="border-b border-border">
            <div class="mx-auto max-w-7xl px-6 py-4">
                <FilterButtonBar
                    :model-value="selectedSentiment"
                    :options="sentimentOptions"
                    :all-label-key="'news.allSentiments'"
                    @update:model-value="filterBySentiment"
                />
            </div>
        </section>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-6 py-8">
            <!-- Featured News -->
            <section v-if="featured" class="mb-8">
                <NewsFeatured :news="featured" />
            </section>

            <!-- Controls -->
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-semibold">
                    {{ t('news.latestNews') }}
                </h2>
                <div class="flex items-center gap-2">
                    <!-- Filter Button -->
                    <Dialog v-model:open="filterOpen">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm">
                                <SlidersHorizontal class="me-1 size-4" />
                                {{ t('home.filters') }}
                                <span
                                    v-if="activeFilterCount > 0"
                                    class="ms-1 rounded-full bg-primary px-1.5 text-xs text-primary-foreground"
                                >
                                    {{ activeFilterCount }}
                                </span>
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>{{
                                    t('news.filterNews')
                                }}</DialogTitle>
                            </DialogHeader>
                            <Deferred data="filterOptions">
                                <template #fallback>
                                    <div class="space-y-4 py-4">
                                        <Skeleton class="h-10 w-full" />
                                        <Skeleton class="h-10 w-full" />
                                        <Skeleton class="h-10 w-full" />
                                        <Skeleton class="h-10 w-full" />
                                    </div>
                                </template>

                                <div class="grid gap-4 py-4">
                                    <!-- Sentiment Select -->
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('news.sentiment.label')
                                        }}</Label>
                                        <SearchableSelect
                                            v-model="selectedSentiment"
                                            :options="sentimentOptions"
                                            :placeholder="
                                                t('news.allSentiments')
                                            "
                                            :empty-text="t('home.noResults')"
                                        />
                                    </div>

                                    <!-- Action Select -->
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('news.action.label')
                                        }}</Label>
                                        <SearchableSelect
                                            v-model="selectedAction"
                                            :options="actionOptions"
                                            :placeholder="t('news.allActions')"
                                            :empty-text="t('home.noResults')"
                                        />
                                    </div>

                                    <!-- Category Select -->
                                    <div class="grid gap-2">
                                        <Label>{{ t('news.category') }}</Label>
                                        <SearchableSelect
                                            v-model="selectedCategory"
                                            :options="categoryOptions"
                                            :placeholder="
                                                t('news.allCategories')
                                            "
                                            :empty-text="t('home.noResults')"
                                        />
                                    </div>

                                    <!-- Market Select -->
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('predictions.market')
                                        }}</Label>
                                        <SearchableSelect
                                            v-model="selectedMarket"
                                            :options="marketOptions"
                                            :placeholder="
                                                t('predictions.allMarkets')
                                            "
                                            :empty-text="t('home.noResults')"
                                        />
                                    </div>

                                    <!-- Sector Select -->
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('predictions.sector')
                                        }}</Label>
                                        <SearchableSelect
                                            v-model="selectedSector"
                                            :options="sectorOptions"
                                            :placeholder="
                                                t('predictions.allSectors')
                                            "
                                            :empty-text="t('home.noResults')"
                                        />
                                    </div>
                                </div>
                            </Deferred>
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" @click="clearFilters">
                                    {{ t('common.clear') }}
                                </Button>
                                <Button @click="applyFilters">
                                    {{ t('common.apply') }}
                                </Button>
                            </div>
                        </DialogContent>
                    </Dialog>

                    <!-- View Toggle -->
                    <div class="flex rounded-lg border border-border">
                        <Button
                            variant="ghost"
                            size="sm"
                            :class="viewMode === 'grid' ? 'bg-muted' : ''"
                            @click="viewMode = 'grid'"
                        >
                            <LayoutGrid class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :class="viewMode === 'list' ? 'bg-muted' : ''"
                            @click="viewMode = 'list'"
                        >
                            <List class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <!-- News Grid/List -->
            <div
                v-if="newsList.length > 0"
                :class="
                    viewMode === 'grid'
                        ? 'grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3'
                        : 'space-y-4'
                "
            >
                <template v-for="item in newsList" :key="item.id">
                    <NewsCard v-if="viewMode === 'grid'" :news="item" />
                    <NewsListItem v-else :news="item" />
                </template>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-col items-center justify-center py-16 text-center"
            >
                <Newspaper class="size-16 text-muted-foreground/30" />
                <h3 class="mt-4 text-lg font-semibold">
                    {{ t('news.noResults') }}
                </h3>
                <p class="mt-2 text-muted-foreground">
                    {{ t('news.noResultsDescription') }}
                </p>
                <Button variant="outline" class="mt-4" @click="clearFilters">
                    {{ t('news.clearFilters') }}
                </Button>
            </div>

            <!-- Pagination -->
            <div
                v-if="newsMeta && newsMeta.lastPage > 1"
                class="mt-8 flex items-center justify-center gap-2"
            >
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="newsMeta.currentPage <= 1"
                    @click="goToPage(newsMeta.currentPage - 1)"
                >
                    {{ t('common.previous') }}
                </Button>
                <span
                    dir="ltr"
                    class="text-sm text-muted-foreground tabular-nums"
                >
                    {{ newsMeta.currentPage }} / {{ newsMeta.lastPage }}
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="newsMeta.currentPage >= newsMeta.lastPage"
                    @click="goToPage(newsMeta.currentPage + 1)"
                >
                    {{ t('common.next') }}
                </Button>
            </div>
        </div>
    </GuestLayout>
</template>
