<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Badge } from '@/components/ui/badge';
import { ShoppingCart, TrendingDown, Eye } from 'lucide-vue-next';
import type { NewsAction } from '@/types/news';

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
                class: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 hover:bg-emerald-100',
                icon: ShoppingCart,
            };
        case 'sell':
            return {
                label: t('news.action.sell'),
                class: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400 hover:bg-rose-100',
                icon: TrendingDown,
            };
        case 'watch':
            return {
                label: t('news.action.watch'),
                class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 hover:bg-amber-100',
                icon: Eye,
            };
        default:
            return null;
    }
});

const sizeClass = computed(() => props.size === 'sm' ? 'text-xs px-1.5 py-0.5' : 'text-sm px-2 py-1');
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
            :class="size === 'sm' ? 'size-3 me-1' : 'size-4 me-1'"
        />
        {{ actionConfig.label }}
    </Badge>
</template>
