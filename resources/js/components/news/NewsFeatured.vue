<script setup lang="ts">
import LocalizedLink from '@/components/LocalizedLink.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { AssetNew } from '@/types/news';
import { ArrowRight, Building2, Calendar } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import NewsActionBadge from './NewsActionBadge.vue';
import NewsSentimentBadge from './NewsSentimentBadge.vue';

interface Props {
    news: AssetNew;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

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
    <Card class="overflow-hidden rounded-md border border-border">
        <CardContent class="p-0">
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Image -->
                <div class="relative aspect-video md:aspect-auto md:h-full">
                    <img
                        v-if="news.image_url"
                        :src="news.image_url"
                        :alt="news.title"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center bg-muted"
                    >
                        <Building2 class="size-16 text-muted-foreground/30" />
                    </div>
                    <!-- Score overlay -->
                    <div
                        v-if="news.score"
                        class="absolute start-4 top-4 rounded-full bg-background/90 px-3 py-1 backdrop-blur-sm"
                    >
                        <span
                            class="text-sm font-bold tabular-nums"
                            :class="scoreColor"
                        >
                            {{ news.score }}/10
                        </span>
                    </div>
                </div>

                <!-- Content -->
                <div class="flex flex-col justify-center p-6">
                    <!-- Badges -->
                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <NewsSentimentBadge :sentiment="news.sentiment" />
                        <NewsActionBadge :action="news.action" />
                        <span
                            v-if="news.category"
                            class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ news.category }}
                        </span>
                    </div>

                    <!-- Title -->
                    <h2
                        class="mb-3 text-2xl leading-tight font-bold md:text-3xl"
                    >
                        {{ news.title }}
                    </h2>

                    <!-- Description -->
                    <p class="mb-4 line-clamp-3 text-muted-foreground">
                        {{ news.description }}
                    </p>

                    <!-- Meta -->
                    <div
                        class="mb-6 flex flex-wrap items-center gap-4 text-sm text-muted-foreground"
                    >
                        <span
                            v-if="formattedDate"
                            class="flex items-center gap-1"
                        >
                            <Calendar class="size-4" />
                            {{ formattedDate }}
                        </span>
                        <LocalizedLink
                            v-if="news.asset"
                            :href="`/assets/${news.asset.symbol}`"
                            class="flex items-center gap-1 transition-colors hover:text-foreground"
                        >
                            <Building2 class="size-4" />
                            {{ news.asset.symbol }}
                        </LocalizedLink>
                        <span
                            v-if="news.market"
                            class="rounded bg-muted px-2 py-0.5 text-xs font-medium"
                        >
                            {{ news.market.code }}
                        </span>
                    </div>

                    <!-- CTA -->
                    <Button as-child class="w-fit">
                        <LocalizedLink :href="`/news/${news.slug}`">
                            {{ t('news.readMore') }}
                            <ArrowRight class="ms-2 size-4 rtl:-scale-x-100" />
                        </LocalizedLink>
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
