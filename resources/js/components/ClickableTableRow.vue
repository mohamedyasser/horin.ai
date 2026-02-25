<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

interface Props {
    ariaLabel: string;
    active?: boolean;
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    active: false,
});

const emit = defineEmits<{
    (e: 'click'): void;
}>();

const handleClick = () => {
    emit('click');
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        emit('click');
    }
};
</script>

<template>
    <tr
        role="button"
        tabindex="0"
        :aria-label="ariaLabel"
        :class="[
            'cursor-pointer border-b border-border transition-colors duration-150 last:border-0',
            'hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none',
            active ? 'bg-muted' : '',
            props.class,
        ]"
        @click="handleClick"
        @keydown.enter="handleKeydown"
        @keydown.space="handleKeydown"
    >
        <slot />
    </tr>
</template>
