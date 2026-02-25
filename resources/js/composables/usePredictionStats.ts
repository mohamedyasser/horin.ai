import type { PredictionListItem } from '@/types';
import { computed, type ComputedRef, type Ref } from 'vue';

export function usePredictionStats(
    predictions: Ref<PredictionListItem[]> | ComputedRef<PredictionListItem[]>,
) {
    const topGainers = computed(() =>
        [...predictions.value]
            .sort((a, b) => b.expectedGainPercent - a.expectedGainPercent)
            .slice(0, 5),
    );

    const mostConfident = computed(() =>
        [...predictions.value]
            .sort((a, b) => b.confidence - a.confidence)
            .slice(0, 5),
    );

    return {
        topGainers,
        mostConfident,
    };
}
