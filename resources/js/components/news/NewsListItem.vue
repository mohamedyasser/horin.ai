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
        year: 'numeric',
    });
});

const scoreColor = computed(() => {
    const score = props.news.score ?? 0;
    if (score >= 7) return 'text-gain';
    if (score >= 4) return 'text-foreground';
    return 'text-loss';
});
</script>

<template>
    <LocalizedLink :href="`/news/${news.slug}`" class="block group">
        <Card class="overflow-hidden border border-border rounded-md hover:bg-muted/30 cursor-pointer transition-colors duration-200">
            <CardContent class="p-0">
                <div class="flex gap-4 p-4">
                    <!-- Image -->
                    <div class="relative hidden shrink-0 sm:block">
                        <div class="h-24 w-36 overflow-hidden rounded-md">
                            <img
                                v-if="news.image_url"
                                :src="news.image_url"
                                :alt="news.title"
                                class="h-full w-full object-cover transition-transform group-hover:scale-105"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center bg-muted">
                                <Building2 class="size-8 text-muted-foreground/30" />
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="flex flex-1 flex-col">
                        <!-- Top row: badges and score -->
                        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <NewsSentimentBadge :sentiment="news.sentiment" size="sm" />
                                <NewsActionBadge :action="news.action" size="sm" :show-icon="false" />
                                <span
                                    v-if="news.category"
                                    class="rounded bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground"
                                >
                                    {{ news.category }}
                                </span>
                            </div>
                            <div
                                v-if="news.score"
                                class="rounded-full bg-muted px-2 py-0.5"
                            >
                                <span class="text-xs font-bold tabular-nums" :class="scoreColor">
                                    {{ news.score }}/10
                                </span>
                            </div>
                        </div>

                        <!-- Title -->
                        <h3 class="mb-1 line-clamp-1 font-semibold group-hover:text-foreground transition-colors">
                            {{ news.title }}
                        </h3>

                        <!-- Description -->
                        <p class="mb-2 line-clamp-2 text-sm text-muted-foreground">
                            {{ news.description }}
                        </p>

                        <!-- Meta -->
                        <div class="mt-auto flex items-center justify-between text-xs text-muted-foreground">
                            <div class="flex items-center gap-3">
                                <span v-if="formattedDate" class="flex items-center gap-1">
                                    <Calendar class="size-3" />
                                    {{ formattedDate }}
                                </span>
                                <LocalizedLink
                                    v-if="news.asset"
                                    :href="`/assets/${news.asset.symbol}`"
                                    class="flex items-center gap-1 font-medium hover:text-foreground"
                                    @click.stop
                                >
                                    <Building2 class="size-3" />
                                    {{ news.asset.symbol }}
                                </LocalizedLink>
                            </div>
                            <span v-if="news.market" class="rounded bg-muted px-1.5 py-0.5 font-medium">
                                {{ news.market.code }}
                            </span>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </LocalizedLink>
</template>
