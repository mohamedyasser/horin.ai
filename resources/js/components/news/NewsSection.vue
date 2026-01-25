<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import LocalizedLink from '@/components/LocalizedLink.vue';
import NewsCard from './NewsCard.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Newspaper, ArrowRight } from 'lucide-vue-next';
import type { AssetNewListItem } from '@/types/news';

interface Props {
    news?: AssetNewListItem[];
    title?: string;
    showViewAll?: boolean;
    viewAllHref?: string;
    loading?: boolean;
    columns?: 2 | 3 | 4;
}

const props = withDefaults(defineProps<Props>(), {
    showViewAll: false,
    viewAllHref: '/news',
    loading: false,
    columns: 4,
});

const { t } = useI18n();

const gridClass = {
    2: 'grid gap-6 sm:grid-cols-2',
    3: 'grid gap-6 sm:grid-cols-2 lg:grid-cols-3',
    4: 'grid gap-6 sm:grid-cols-2 lg:grid-cols-4',
};
</script>

<template>
    <section>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-xl font-semibold">
                {{ title || t('news.latestNews') }}
            </h2>
            <Button v-if="showViewAll" variant="ghost" size="sm" as-child>
                <LocalizedLink :href="viewAllHref">
                    {{ t('common.more') }}
                    <ArrowRight class="ms-1 size-4" />
                </LocalizedLink>
            </Button>
        </div>

        <!-- Loading state -->
        <div v-if="loading" :class="gridClass[columns]">
            <Skeleton v-for="i in columns" :key="i" class="h-64" />
        </div>

        <!-- News grid -->
        <div v-else-if="news?.length" :class="gridClass[columns]">
            <NewsCard
                v-for="item in news"
                :key="item.id"
                :news="item"
            />
        </div>

        <!-- Empty state -->
        <div
            v-else
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <Newspaper class="size-12 text-muted-foreground/30" />
            <p class="mt-4 text-sm text-muted-foreground">
                {{ t('news.noResults') }}
            </p>
        </div>
    </section>
</template>
