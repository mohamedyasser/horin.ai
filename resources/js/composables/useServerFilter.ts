import { router } from '@inertiajs/vue3';
import { ref, watch, type Ref } from 'vue';

export interface FilterOption<T = string> {
    value: T;
    label: string;
}

interface UseServerFilterOptions<T> {
    /** The URL parameter name for this filter */
    paramName: string;
    /** Initial value from server filters */
    initialValue?: T | null;
    /** Additional query params to preserve */
    preserveParams?: string[];
    /** Props to reload via Inertia partial reload */
    only?: string[];
}

export function useServerFilter<T = string>(options: UseServerFilterOptions<T>) {
    const { paramName, initialValue = null, preserveParams = [], only = [] } = options;

    const selectedValue = ref<T | null>(initialValue) as Ref<T | null>;
    const isFiltering = ref(false);

    const applyFilter = (value: T | null) => {
        selectedValue.value = value;
        isFiltering.value = true;

        // Get current URL params to preserve
        const currentParams = new URLSearchParams(window.location.search);
        const data: Record<string, string | undefined> = {};

        // Preserve specified params
        preserveParams.forEach((param) => {
            const paramValue = currentParams.get(param);
            if (paramValue) {
                data[param] = paramValue;
            }
        });

        // Set the filter value
        if (value !== null) {
            data[paramName] = String(value);
        }

        router.visit(window.location.pathname, {
            data,
            preserveState: true,
            preserveScroll: true,
            only: only.length > 0 ? only : undefined,
            onFinish: () => {
                isFiltering.value = false;
            },
            onError: (error) => {
                isFiltering.value = false;
                console.error('Filter error:', error);
            },
        });
    };

    const clearFilter = () => {
        applyFilter(null);
    };

    return {
        selectedValue,
        isFiltering,
        applyFilter,
        clearFilter,
    };
}
