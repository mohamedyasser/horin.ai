<script setup lang="ts">
import { cn } from '@/lib/utils';

interface Props {
    selected?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    selected: false,
    disabled: false,
});

const emit = defineEmits<{
    select: [];
}>();
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        :class="
            cn(
                'flex flex-col items-center gap-2 rounded-lg border-2 p-4 text-center transition-all',
                'hover:border-primary/50 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none',
                selected ? 'border-primary bg-primary/5' : 'border-border',
                disabled && 'cursor-not-allowed opacity-50',
            )
        "
        @click="emit('select')"
    >
        <slot />
    </button>
</template>
