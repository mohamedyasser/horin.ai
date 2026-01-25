<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import LocalizedLink from '@/components/LocalizedLink.vue';
import NewsSentimentBadge from './NewsSentimentBadge.vue';
import NewsActionBadge from './NewsActionBadge.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Calendar, Building2 } from 'lucide-vue-next';
import type { AssetNewListItem } from '@/types/news';

interface Props {
    news: AssetNewListItem;
}

const props = defineProps<Props>();

const { locale } = useI18n();

const formattedDate = computed(() => {
    if (!props.news.date) return null;
    const date = new Date(props.news.date);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'short',
        day: 'numeric',
    });
});

const scoreColor = computed(() => {
    const score = props.news.score ?? 0;
    if (score >= 7) return 'text-green-600 dark:text-green-400';
    if (score >= 4) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
});
</script>

<template>
    <LocalizedLink :href="`/news/${news.slug}`" class="block group">
        <Card class="h-full overflow-hidden transition-all hover:shadow-lg hover:border-primary/50">
            <!-- Image -->
            <div class="relative aspect-video">
                <img
                    v-if="news.image_url"
                    :src="news.image_url"
                    :alt="news.title"
                    class="h-full w-full object-cover transition-transform group-hover:scale-105"
                />
                <div v-else class="flex h-full w-full items-center justify-center bg-muted">
                    <Building2 class="size-12 text-muted-foreground/30" />
                </div>

                <!-- Score badge -->
                <div
                    v-if="news.score"
                    class="absolute end-2 top-2 rounded-full bg-background/90 px-2 py-0.5 backdrop-blur-sm"
                >
                    <span class="text-xs font-bold" :class="scoreColor">
                        {{ news.score }}/10
                    </span>
                </div>

                <!-- Sentiment badge overlay -->
                <div class="absolute start-2 top-2">
                    <NewsSentimentBadge :sentiment="news.sentiment" size="sm" />
                </div>
            </div>

            <CardContent class="p-4">
                <!-- Category & Action -->
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span
                        v-if="news.category"
                        class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                    >
                        {{ news.category }}
                    </span>
                    <NewsActionBadge :action="news.action" size="sm" :show-icon="false" />
                </div>

                <!-- Title -->
                <h3 class="mb-2 line-clamp-2 font-semibold leading-tight group-hover:text-primary transition-colors">
                    {{ news.title }}
                </h3>

                <!-- Description -->
                <p class="mb-3 line-clamp-2 text-sm text-muted-foreground">
                    {{ news.description }}
                </p>

                <!-- Meta -->
                <div class="flex items-center justify-between text-xs text-muted-foreground">
                    <div class="flex items-center gap-2">
                        <span v-if="formattedDate" class="flex items-center gap-1">
                            <Calendar class="size-3" />
                            {{ formattedDate }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="news.asset" class="font-medium">
                            {{ news.asset.symbol }}
                        </span>
                        <span v-if="news.market" class="rounded bg-muted px-1.5 py-0.5 font-medium">
                            {{ news.market.code }}
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </LocalizedLink>
</template>
