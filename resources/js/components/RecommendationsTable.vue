<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { TrendingUp, TrendingDown, Minus, Search } from 'lucide-vue-next';
import { useRecommendationFormatters } from '@/composables/useRecommendationFormatters';
import ClickableTableRow from '@/components/ClickableTableRow.vue';
import type { Recommendation } from '@/types';

const { t, locale } = useI18n();
const { getActionColor, getActionIcon, formatTimeAgo } = useRecommendationFormatters();

interface Props {
    recommendations: Recommendation[];
}

defineProps<Props>();

const getIconComponent = (action: string) => {
    const icon = getActionIcon(action);
    if (icon === 'TrendingUp') return TrendingUp;
    if (icon === 'TrendingDown') return TrendingDown;
    return Minus;
};

const navigateToAsset = (symbol: string) => {
    router.visit(`/${locale.value}/assets/${symbol}`);
};
</script>

<template>
    <div class="rounded-lg border border-border">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-border bg-muted/50">
                        <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                            {{ t('home.table.symbol') }}
                        </th>
                        <th class="px-4 py-3 text-start text-sm font-medium text-muted-foreground">
                            {{ t('home.table.name') }}
                        </th>
                        <th class="px-4 py-3 text-center text-sm font-medium text-muted-foreground">
                            {{ t('recommendations.title') }}
                        </th>
                        <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                            {{ t('recommendations.score') }}
                        </th>
                        <th class="px-4 py-3 text-end text-sm font-medium text-muted-foreground">
                            {{ t('recommendations.updated') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <ClickableTableRow
                        v-for="rec in recommendations"
                        :key="rec.id"
                        :aria-label="`View details for ${rec.asset?.symbol}`"
                        class="border-border/50"
                        @click="navigateToAsset(rec.asset?.symbol ?? '')"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ rec.asset?.symbol }}</span>
                                <span v-if="rec.asset?.market" class="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                                    {{ rec.asset.market.code }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-muted-foreground">
                            {{ rec.asset?.name }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span
                                :class="getActionColor(rec.recommendation)"
                                class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-xs font-bold"
                            >
                                <component :is="getIconComponent(rec.recommendation)" class="size-3" />
                                {{ t(`recommendations.actions.${rec.recommendation}`) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-end">
                            <span class="font-medium">{{ rec.score.toFixed(1) }}</span>
                        </td>
                        <td class="px-4 py-3 text-end text-sm text-muted-foreground">
                            {{ formatTimeAgo(rec.created_at) }}
                        </td>
                    </ClickableTableRow>
                </tbody>
            </table>
        </div>

        <!-- Empty State -->
        <div
            v-if="recommendations.length === 0"
            class="flex flex-col items-center justify-center py-12 text-center"
        >
            <Search class="size-12 text-muted-foreground/50" />
            <p class="mt-4 text-muted-foreground">
                {{ t('recommendations.noData') }}
            </p>
        </div>
    </div>
</template>
