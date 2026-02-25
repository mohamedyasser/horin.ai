<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { NewsAction } from '@/types/news';
import { Eye, ShoppingCart, TrendingDown } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Props {
    action: NewsAction | null;
    showIcon?: boolean;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    showIcon: true,
    size: 'md',
});

const { t } = useI18n();

const actionConfig = computed(() => {
    switch (props.action) {
        case 'buy':
            return {
                label: t('news.action.buy'),
                class: 'bg-gain-muted text-gain hover:bg-gain-muted',
                icon: ShoppingCart,
            };
        case 'sell':
            return {
                label: t('news.action.sell'),
                class: 'bg-loss-muted text-loss hover:bg-loss-muted',
                icon: TrendingDown,
            };
        case 'watch':
            return {
                label: t('news.action.watch'),
                class: 'bg-muted text-muted-foreground hover:bg-muted',
                icon: Eye,
            };
        default:
            return null;
    }
});

const sizeClass = computed(() =>
    props.size === 'sm' ? 'text-xs px-1.5 py-0.5' : 'text-sm px-2 py-1',
);
</script>

<template>
    <Badge
        v-if="actionConfig"
        variant="default"
        :class="[actionConfig.class, sizeClass]"
    >
        <component
            :is="actionConfig.icon"
            v-if="showIcon"
            :class="size === 'sm' ? 'me-1 size-3' : 'me-1 size-4'"
        />
        {{ actionConfig.label }}
    </Badge>
</template>
