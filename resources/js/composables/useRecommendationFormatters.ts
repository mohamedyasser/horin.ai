// resources/js/composables/useRecommendationFormatters.ts

/**
 * Shared formatting utilities for recommendation data display.
 */
export function useRecommendationFormatters() {
    /**
     * Get Tailwind color classes based on recommendation action
     */
    const getActionColor = (action: string): string => {
        switch (action?.toUpperCase()) {
            case 'STRONG_BUY':
                return 'bg-green-600 text-white';
            case 'BUY':
                return 'bg-green-500 text-white';
            case 'HOLD':
                return 'bg-gray-500 text-white';
            case 'SELL':
                return 'bg-red-500 text-white';
            case 'STRONG_SELL':
                return 'bg-red-600 text-white';
            default:
                return 'bg-gray-400 text-white';
        }
    };

    /**
     * Get Tailwind text color classes based on recommendation action
     */
    const getActionTextColor = (action: string): string => {
        switch (action?.toUpperCase()) {
            case 'STRONG_BUY':
            case 'BUY':
                return 'text-green-600 dark:text-green-400';
            case 'SELL':
            case 'STRONG_SELL':
                return 'text-red-600 dark:text-red-400';
            default:
                return 'text-gray-600 dark:text-gray-400';
        }
    };

    /**
     * Get icon name based on recommendation action
     */
    const getActionIcon = (action: string): 'TrendingUp' | 'TrendingDown' | 'Minus' => {
        const upper = action?.toUpperCase();
        if (upper?.includes('BUY')) return 'TrendingUp';
        if (upper?.includes('SELL')) return 'TrendingDown';
        return 'Minus';
    };

    /**
     * Get risk/reward label from ratio
     */
    const getRiskRewardLabel = (ratio: number | null): string => {
        if (ratio === null) return '-';
        if (ratio >= 3) return 'excellent';
        if (ratio >= 2) return 'good';
        if (ratio >= 1) return 'fair';
        return 'poor';
    };

    /**
     * Get Tailwind color classes for risk/reward ratio
     */
    const getRiskRewardColor = (ratio: number | null): string => {
        if (ratio === null) return 'text-gray-500';
        if (ratio >= 3) return 'text-green-600 dark:text-green-400';
        if (ratio >= 2) return 'text-green-500 dark:text-green-400';
        if (ratio >= 1) return 'text-yellow-500 dark:text-yellow-400';
        return 'text-red-500 dark:text-red-400';
    };

    /**
     * Format time ago string
     */
    const formatTimeAgo = (date: Date | string | null): string => {
        if (!date) return '-';
        const d = typeof date === 'string' ? new Date(date) : date;
        const minutes = Math.floor((Date.now() - d.getTime()) / 60000);

        if (minutes < 1) return 'just_now';
        if (minutes < 60) return `${minutes}m`;
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return `${hours}h`;
        return `${Math.floor(hours / 24)}d`;
    };

    /**
     * Check if recommendation is stale (older than threshold)
     */
    const isStale = (date: Date | string | null, thresholdMinutes: number = 30): boolean => {
        if (!date) return true;
        const d = typeof date === 'string' ? new Date(date) : date;
        return (Date.now() - d.getTime()) > thresholdMinutes * 60 * 1000;
    };

    /**
     * Get strength color based on percentage
     */
    const getStrengthColor = (strength: number): string => {
        if (strength >= 80) return 'text-green-600 dark:text-green-400';
        if (strength >= 60) return 'text-yellow-600 dark:text-yellow-400';
        return 'text-red-600 dark:text-red-400';
    };

    return {
        getActionColor,
        getActionTextColor,
        getActionIcon,
        getRiskRewardLabel,
        getRiskRewardColor,
        formatTimeAgo,
        isStale,
        getStrengthColor,
    };
}
