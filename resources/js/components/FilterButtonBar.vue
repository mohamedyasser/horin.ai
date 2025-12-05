<script setup lang="ts" generic="T extends string | number">
import { Button } from '@/components/ui/button';
import { useI18n } from 'vue-i18n';

export interface FilterOption<V = string> {
    value: V;
    label: string;
}

interface Props {
    /** Currently selected value */
    modelValue: T | null;
    /** Available filter options */
    options: FilterOption<T>[];
    /** Label for the "All" option */
    allLabel?: string;
    /** Translation key for the "All" option (overrides allLabel) */
    allLabelKey?: string;
    /** Whether to show the "All" option */
    showAll?: boolean;
    /** Size of the buttons */
    size?: 'default' | 'sm' | 'lg' | 'icon';
}

const props = withDefaults(defineProps<Props>(), {
    showAll: true,
    size: 'sm',
});

const emit = defineEmits<{
    'update:modelValue': [value: T | null];
}>();

const { t } = useI18n();

const getAllLabel = () => {
    if (props.allLabelKey) {
        return t(props.allLabelKey);
    }
    return props.allLabel ?? t('common.all');
};

const selectOption = (value: T | null) => {
    emit('update:modelValue', value);
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <Button
            v-if="showAll"
            :variant="modelValue === null ? 'default' : 'outline'"
            :size="size"
            @click="selectOption(null)"
        >
            {{ getAllLabel() }}
        </Button>
        <Button
            v-for="option in options"
            :key="String(option.value)"
            :variant="modelValue === option.value ? 'default' : 'outline'"
            :size="size"
            @click="selectOption(option.value)"
        >
            {{ option.label }}
        </Button>
    </div>
</template>
