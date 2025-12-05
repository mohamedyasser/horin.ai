import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { useDebounceFn } from '@vueuse/core';

interface UseServerSearchOptions {
    /** Debounce delay in milliseconds (default: 300) */
    delay?: number;
    /** Initial search value from server filters */
    initialValue?: string | null;
    /** Additional query params to preserve */
    preserveParams?: string[];
    /** Props to reload via Inertia partial reload (default: ['assets', 'filters']) */
    only?: string[];
}

export function useServerSearch(options: UseServerSearchOptions = {}) {
    const { delay = 300, initialValue = null, preserveParams = [], only = ['assets', 'filters'] } = options;

    const searchQuery = ref(initialValue || '');
    const isSearching = ref(false);

    const performSearch = useDebounceFn((query: string) => {
        isSearching.value = true;

        // Get current URL params to preserve
        const currentParams = new URLSearchParams(window.location.search);
        const preservedData: Record<string, string> = {};

        // Preserve specified params (like market_id, page, etc.)
        preserveParams.forEach((param) => {
            const value = currentParams.get(param);
            if (value) {
                preservedData[param] = value;
            }
        });

        // Build new data object
        const data: Record<string, string | undefined> = {
            ...preservedData,
            search: query || undefined,
        };

        // Remove undefined values
        Object.keys(data).forEach((key) => {
            if (data[key] === undefined) {
                delete data[key];
            }
        });

        router.visit(window.location.pathname, {
            data,
            preserveState: true,
            preserveScroll: true,
            only,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    }, delay);

    // Watch for changes and trigger search
    watch(searchQuery, (newValue) => {
        performSearch(newValue);
    });

    // Clear search
    function clearSearch() {
        searchQuery.value = '';
    }

    return {
        searchQuery,
        isSearching,
        clearSearch,
    };
}
