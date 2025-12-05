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
            'border-b border-border last:border-0 transition-colors cursor-pointer',
            'hover:bg-muted/30 focus-visible:bg-muted/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
            active ? 'bg-muted/50' : '',
            props.class,
        ]"
        @click="handleClick"
        @keydown.enter="handleKeydown"
        @keydown.space="handleKeydown"
    >
        <slot />
    </tr>
</template>
