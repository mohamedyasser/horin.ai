<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 42"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            fill-rule="evenodd"
            d="M7 6h8v11h10V6h8v30h-8V25H15v11H7V6z"
        />
    </svg>
</template>
