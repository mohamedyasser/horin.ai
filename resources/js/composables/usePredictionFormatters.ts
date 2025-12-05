// resources/js/composables/usePredictionFormatters.ts

/**
 * Shared formatting utilities for prediction data display.
 * Consolidates duplicated helper functions from 6+ page components.
 */
export function usePredictionFormatters() {
    /**
     * Format a gain percentage with sign prefix
     * @param gain - The gain percentage value
     * @returns Formatted string like "+5.2%" or "-3.1%"
     */
    const formatGain = (gain: number): string => {
        const sign = gain >= 0 ? '+' : '';
        return `${sign}${gain.toFixed(1)}%`;
    };

    /**
     * Get Tailwind color classes based on confidence level
     * @param confidence - Confidence percentage (0-100)
     * @returns Tailwind CSS classes for text color
     */
    const getConfidenceColor = (confidence: number): string => {
        if (confidence >= 85) return 'text-green-600 dark:text-green-400';
        if (confidence >= 70) return 'text-yellow-600 dark:text-yellow-400';
        return 'text-red-600 dark:text-red-400';
    };

    /**
     * Get Tailwind color classes for market open/closed status
     * @param isOpen - Whether the market is currently open
     * @returns Tailwind CSS classes for badge styling
     */
    const getStatusColor = (isOpen: boolean): string => {
        return isOpen
            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
            : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400';
    };

    /**
     * Calculate expected gain percentage from predicted and current prices
     * @param predictedPrice - The predicted future price
     * @param currentPrice - The current price (nullable)
     * @returns Gain percentage or 0 if current price is invalid
     */
    const calculateGainPercent = (predictedPrice: number, currentPrice: number | null): number => {
        if (!currentPrice || currentPrice === 0) return 0;
        return ((predictedPrice - currentPrice) / currentPrice) * 100;
    };

    /**
     * Get Tailwind color classes based on gain direction
     * @param gain - The gain percentage
     * @returns Tailwind CSS classes for positive (green) or negative (red)
     */
    const getGainColor = (gain: number): string => {
        return gain >= 0
            ? 'text-green-600 dark:text-green-400'
            : 'text-red-600 dark:text-red-400';
    };

    return {
        formatGain,
        getConfidenceColor,
        getStatusColor,
        calculateGainPercent,
        getGainColor,
    };
}
