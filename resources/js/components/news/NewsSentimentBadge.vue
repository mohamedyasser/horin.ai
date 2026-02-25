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
                class: 'bg-gain-muted text-gain hover:bg-gain-muted',
                icon: TrendingUp,
            };
        case 'negative':
            return {
                label: t('news.sentiment.negative'),
                variant: 'default' as const,
                class: 'bg-loss-muted text-loss hover:bg-loss-muted',
                icon: TrendingDown,
            };
        case 'neutral':
        default:
            return {
                label: t('news.sentiment.neutral'),
                variant: 'default' as const,
                class: 'bg-muted text-muted-foreground hover:bg-muted',
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
