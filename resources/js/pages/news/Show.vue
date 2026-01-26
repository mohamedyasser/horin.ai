<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import GuestLayout from '@/layouts/GuestLayout.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import NewsSentimentBadge from '@/components/news/NewsSentimentBadge.vue';
import NewsActionBadge from '@/components/news/NewsActionBadge.vue';
import NewsCard from '@/components/news/NewsCard.vue';
import NewsComments from '@/components/news/NewsComments.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Separator } from '@/components/ui/separator';
import {
    ChevronLeft,
    Calendar,
    Building2,
    Bookmark,
    BookmarkCheck,
    ThumbsUp,
    ThumbsDown,
    Share2,
    Copy,
    Check,
    AlertTriangle,
    TrendingUp,
    Tag,
    MapPin,
} from 'lucide-vue-next';
import type { AssetNew, AssetNewListItem } from '@/types/news';

interface Props {
    canLogin?: boolean;
    canRegister?: boolean;
    news: AssetNew;
    relatedNews?: AssetNewListItem[];
    similarNews?: AssetNewListItem[];
}

const props = withDefaults(defineProps<Props>(), {
    canLogin: true,
    canRegister: true,
});

const { t, locale } = useI18n();

// Local state
const isBookmarking = ref(false);
const isRating = ref(false);
const showCopied = ref(false);

// Computed
const formattedDate = computed(() => {
    if (!props.news.date) return null;
    const date = new Date(props.news.date);
    return date.toLocaleDateString(locale.value === 'ar' ? 'ar-EG' : 'en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    });
});

const scoreColor = computed(() => {
    const score = props.news.score ?? 0;
    if (score >= 7) return 'text-green-600 dark:text-green-400';
    if (score >= 4) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
});

const scoreBgColor = computed(() => {
    const score = props.news.score ?? 0;
    if (score >= 7) return 'bg-green-100 dark:bg-green-900/30';
    if (score >= 4) return 'bg-yellow-100 dark:bg-yellow-900/30';
    return 'bg-red-100 dark:bg-red-900/30';
});

// Actions
const toggleBookmark = () => {
    if (isBookmarking.value) return;
    isBookmarking.value = true;

    const endpoint = props.news.bookmarked
        ? `/api/news/${props.news.id}/bookmark`
        : `/api/news/${props.news.id}/bookmark`;

    router.post(
        endpoint,
        {},
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isBookmarking.value = false;
            },
        }
    );
};

const rateNews = (helpful: boolean) => {
    if (isRating.value) return;
    isRating.value = true;

    router.post(
        `/api/news/${props.news.id}/rate`,
        { helpful },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isRating.value = false;
            },
        }
    );
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(window.location.href);
        showCopied.value = true;
        setTimeout(() => {
            showCopied.value = false;
        }, 2000);
    } catch (e) {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = window.location.href;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showCopied.value = true;
        setTimeout(() => {
            showCopied.value = false;
        }, 2000);
    }
};
</script>

<template>
    <Head :title="news.title">
        <meta name="description" :content="news.meta_description || news.description">
        <meta v-if="news.meta_tags?.length" name="keywords" :content="news.meta_tags.join(', ')">
    </Head>

    <GuestLayout :can-login="canLogin" :can-register="canRegister">
        <!-- Back Navigation -->
        <section class="border-b border-border/40 bg-muted/30">
            <div class="mx-auto max-w-4xl px-4 py-4">
                <LocalizedLink href="/news" class="inline-flex items-center text-sm text-muted-foreground hover:text-foreground transition-colors">
                    <ChevronLeft class="me-1 size-4 rtl:-scale-x-100" />
                    {{ t('common.previous') }}
                </LocalizedLink>
            </div>
        </section>

        <!-- Article Content -->
        <article class="mx-auto max-w-4xl px-4 py-8">
            <!-- Header -->
            <header class="mb-8">
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
                    <div
                        v-if="news.score"
                        class="rounded-full px-3 py-1 text-sm font-bold"
                        :class="[scoreBgColor, scoreColor]"
                    >
                        {{ news.score }}/10
                    </div>
                </div>

                <!-- Title -->
                <h1 class="mb-4 text-3xl font-bold leading-tight md:text-4xl">
                    {{ news.title }}
                </h1>

                <!-- Meta -->
                <div class="flex flex-wrap items-center gap-4 text-sm text-muted-foreground">
                    <span v-if="formattedDate" class="flex items-center gap-1">
                        <Calendar class="size-4" />
                        {{ formattedDate }}
                    </span>
                    <LocalizedLink
                        v-if="news.asset"
                        :href="`/assets/${news.asset.symbol}`"
                        class="flex items-center gap-1 font-medium hover:text-foreground transition-colors"
                    >
                        <Building2 class="size-4" />
                        {{ news.asset.symbol }}
                    </LocalizedLink>
                    <span v-if="news.market" class="rounded bg-muted px-2 py-0.5 font-medium">
                        {{ news.market.code }}
                    </span>
                    <span v-if="news.country" class="flex items-center gap-1">
                        <MapPin class="size-4" />
                        {{ news.country.name }}
                    </span>
                </div>
            </header>

            <!-- Featured Image -->
            <div v-if="news.image_url" class="mb-8 overflow-hidden rounded-xl">
                <img
                    :src="news.image_url"
                    :alt="news.title"
                    class="h-auto w-full object-cover"
                />
            </div>

            <!-- Description -->
            <p class="mb-8 text-lg text-muted-foreground leading-relaxed">
                {{ news.description }}
            </p>

            <!-- Content -->
            <div class="prose prose-lg dark:prose-invert max-w-none mb-8" v-html="news.content"></div>

            <!-- Risks & Opportunities -->
            <div v-if="news.risks?.length || news.opportunities?.length" class="mb-8 grid gap-6 md:grid-cols-2">
                <!-- Risks -->
                <Card v-if="news.risks?.length">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-red-600 dark:text-red-400">
                            <AlertTriangle class="size-5" />
                            {{ t('news.risks') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li v-for="(risk, index) in news.risks" :key="index" class="flex items-start gap-2 text-sm">
                                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-red-500"></span>
                                {{ risk }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Opportunities -->
                <Card v-if="news.opportunities?.length">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-green-600 dark:text-green-400">
                            <TrendingUp class="size-5" />
                            {{ t('news.opportunities') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-2">
                            <li v-for="(opportunity, index) in news.opportunities" :key="index" class="flex items-start gap-2 text-sm">
                                <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-green-500"></span>
                                {{ opportunity }}
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Affected Sectors -->
            <div v-if="news.affected_sectors?.length" class="mb-8">
                <h3 class="mb-3 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Tag class="size-4" />
                    {{ t('news.affectedSectors') }}
                </h3>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="sector in news.affected_sectors"
                        :key="sector"
                        class="rounded-full bg-muted px-3 py-1 text-sm"
                    >
                        {{ sector }}
                    </span>
                </div>
            </div>

            <Separator class="my-8" />

            <!-- Actions -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <!-- Rating -->
                <div class="flex items-center gap-2">
                    <span class="text-sm text-muted-foreground">{{ t('news.rating.helpful') }}?</span>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="isRating"
                        :class="news.user_rating === true ? 'bg-green-100 dark:bg-green-900/30' : ''"
                        @click="rateNews(true)"
                    >
                        <ThumbsUp class="me-1 size-4" />
                        {{ news.ratings_summary?.helpful_count || 0 }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="isRating"
                        :class="news.user_rating === false ? 'bg-red-100 dark:bg-red-900/30' : ''"
                        @click="rateNews(false)"
                    >
                        <ThumbsDown class="me-1 size-4" />
                        {{ news.ratings_summary?.not_helpful_count || 0 }}
                    </Button>
                </div>

                <!-- Share & Bookmark -->
                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="copyLink">
                        <Check v-if="showCopied" class="me-1 size-4 text-green-600" />
                        <Copy v-else class="me-1 size-4" />
                        {{ showCopied ? t('news.share.copied') : t('news.share.copy') }}
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="isBookmarking"
                        @click="toggleBookmark"
                    >
                        <BookmarkCheck v-if="news.bookmarked" class="me-1 size-4 text-primary" />
                        <Bookmark v-else class="me-1 size-4" />
                        {{ news.bookmarked ? t('news.bookmark.remove') : t('news.bookmark.add') }}
                    </Button>
                </div>
            </div>

            <!-- Comments Section -->
            <div class="mt-12">
                <NewsComments :news-id="news.id" :initial-count="news.comments_count" />
            </div>
        </article>

        <!-- Related News -->
        <Deferred data="relatedNews">
            <template #fallback>
                <section class="border-t border-border/40 bg-muted/30">
                    <div class="mx-auto max-w-7xl px-4 py-12">
                        <h2 class="mb-6 text-xl font-semibold">{{ t('news.relatedAsset') }}</h2>
                        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <Skeleton v-for="i in 3" :key="i" class="h-64" />
                        </div>
                    </div>
                </section>
            </template>

            <section v-if="relatedNews?.length" class="border-t border-border/40 bg-muted/30">
                <div class="mx-auto max-w-7xl px-4 py-12">
                    <h2 class="mb-6 text-xl font-semibold">{{ t('news.relatedAsset') }}</h2>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <NewsCard
                            v-for="item in relatedNews.slice(0, 3)"
                            :key="item.id"
                            :news="item"
                        />
                    </div>
                </div>
            </section>
        </Deferred>

        <!-- Similar News -->
        <Deferred data="similarNews">
            <template #fallback>
                <section class="border-t border-border/40">
                    <div class="mx-auto max-w-7xl px-4 py-12">
                        <h2 class="mb-6 text-xl font-semibold">{{ t('news.latestNews') }}</h2>
                        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            <Skeleton v-for="i in 4" :key="i" class="h-64" />
                        </div>
                    </div>
                </section>
            </template>

            <section v-if="similarNews?.length" class="border-t border-border/40">
                <div class="mx-auto max-w-7xl px-4 py-12">
                    <h2 class="mb-6 text-xl font-semibold">{{ t('news.latestNews') }}</h2>
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <NewsCard
                            v-for="item in similarNews"
                            :key="item.id"
                            :news="item"
                        />
                    </div>
                </div>
            </section>
        </Deferred>
    </GuestLayout>
</template>
