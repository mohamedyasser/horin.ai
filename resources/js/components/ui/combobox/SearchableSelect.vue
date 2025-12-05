<script setup lang="ts" generic="T extends string | number">
import { computed } from 'vue';
import {
    ComboboxRoot,
    ComboboxAnchor,
    ComboboxInput,
    ComboboxTrigger,
    ComboboxPortal,
    ComboboxContent,
    ComboboxViewport,
    ComboboxEmpty,
    ComboboxItem,
    ComboboxItemIndicator,
} from 'reka-ui';
import { Check, ChevronDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

export interface SelectOption<V = string> {
    value: V;
    label: string;
}

interface Props {
    modelValue: T | null;
    options: SelectOption<T>[];
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select...',
    searchPlaceholder: 'Search...',
    emptyText: 'No results found.',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: T | null];
}>();

const selectedOption = computed(() =>
    props.options.find((o) => o.value === props.modelValue)
);

const handleSelect = (option: SelectOption<T>) => {
    emit('update:modelValue', option.value);
};
</script>

<template>
    <ComboboxRoot
        :model-value="selectedOption"
        :display-value="(opt) => opt?.label ?? ''"
        :disabled="disabled"
        @update:model-value="(opt) => opt && handleSelect(opt)"
    >
        <ComboboxAnchor
            :class="cn(
                'flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs ring-offset-background',
                'focus-within:outline-none focus-within:ring-1 focus-within:ring-ring',
                'disabled:cursor-not-allowed disabled:opacity-50',
                props.class
            )"
        >
            <ComboboxInput
                :placeholder="selectedOption ? selectedOption.label : placeholder"
                :class="cn(
                    'flex-1 bg-transparent outline-none placeholder:text-muted-foreground',
                    'disabled:cursor-not-allowed'
                )"
            />
            <ComboboxTrigger class="flex items-center">
                <ChevronDown class="size-4 opacity-50" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                :class="cn(
                    'relative z-50 max-h-60 min-w-[8rem] overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md',
                    'data-[state=open]:animate-in data-[state=closed]:animate-out',
                    'data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
                    'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                    'data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2'
                )"
            >
                <ComboboxViewport class="p-1">
                    <ComboboxEmpty class="py-6 text-center text-sm text-muted-foreground">
                        {{ emptyText }}
                    </ComboboxEmpty>

                    <ComboboxItem
                        v-for="option in options"
                        :key="String(option.value)"
                        :value="option"
                        :class="cn(
                            'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pe-8 ps-2 text-sm outline-none',
                            'data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground',
                            'data-[disabled]:pointer-events-none data-[disabled]:opacity-50'
                        )"
                    >
                        <span class="truncate">{{ option.label }}</span>
                        <ComboboxItemIndicator class="absolute end-2 flex items-center justify-center">
                            <Check class="size-4" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
