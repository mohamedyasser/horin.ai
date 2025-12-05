import { computed, type Ref, type ComputedRef } from 'vue';
import type { AssetListItem } from '@/types';

export function useAssetStats(
    assets: Ref<AssetListItem[]> | ComputedRef<AssetListItem[]>
) {
    // Helper to calculate gain percent for an asset
    const calculateGainPercent = (asset: AssetListItem) => {
        if (!asset.latestPrediction || !asset.latestPrice?.last) return 0;
        return ((asset.latestPrediction.predictedPrice - asset.latestPrice.last) / asset.latestPrice.last) * 100;
    };

    // Filter assets that have predictions
    const assetsWithPredictions = computed(() =>
        assets.value.filter((a) => a.latestPrediction)
    );

    const topGainers = computed(() =>
        [...assetsWithPredictions.value]
            .filter((a) => a.latestPrediction)
            .sort((a, b) => {
                const gainA = calculateGainPercent(a);
                const gainB = calculateGainPercent(b);
                return gainB - gainA;
            })
            .slice(0, 5)
    );

    const mostConfident = computed(() =>
        [...assetsWithPredictions.value]
            .filter((a) => a.latestPrediction)
            .sort((a, b) => b.latestPrediction!.confidence - a.latestPrediction!.confidence)
            .slice(0, 5)
    );

    return {
        assetsWithPredictions,
        topGainers,
        mostConfident,
        calculateGainPercent,
    };
}
