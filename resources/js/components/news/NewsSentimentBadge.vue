<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { TrendingUp, TrendingDown, Minus } from 'lucide-vue-next';
import type { NewsSentiment } from '@/types/news';

interface Props {
    sentiment: NewsSentiment | null;
    showIcon?: boolean;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    showIcon: true,
    size: 'md',
});

const { t } = useI18n();

const sentimentConfig = computed(() => {
    switch (props.sentiment) {
        case 'positive':
            return {
                label: t('news.sentiment.positive'),
                variant: 'default' as const,
                class: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 hover:bg-green-100',
                icon: TrendingUp,
            };
        case 'negative':
            return {
                label: t('news.sentiment.negative'),
                variant: 'default' as const,
                class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 hover:bg-red-100',
                icon: TrendingDown,
            };
        case 'neutral':
        default:
            return {
                label: t('news.sentiment.neutral'),
                variant: 'default' as const,
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400 hover:bg-gray-100',
                icon: Minus,
            };
    }
});

const sizeClass = computed(() => props.size === 'sm' ? 'text-xs px-1.5 py-0.5' : 'text-sm px-2 py-1');
</script>

<template>
    <Badge
        v-if="sentiment"
        :variant="sentimentConfig.variant"
        :class="[sentimentConfig.class, sizeClass]"
    >
        <component
            :is="sentimentConfig.icon"
            v-if="showIcon"
            :class="size === 'sm' ? 'size-3 me-1' : 'size-4 me-1'"
        />
        {{ sentimentConfig.label }}
    </Badge>
</template>
