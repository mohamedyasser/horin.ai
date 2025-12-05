<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import LocalizedLink from '@/components/LocalizedLink.vue';

interface Props {
    symbol: string;
    href: string;
    marketCode?: string;
    class?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
</script>

<template>
    <LocalizedLink
        :href="href"
        :class="[
            'flex items-center justify-between hover:bg-muted/30 -mx-2 px-2 py-1 rounded transition-colors',
            props.class,
        ]"
    >
        <div>
            <span class="font-medium">{{ symbol }}</span>
            <span v-if="marketCode" class="ms-1 text-xs text-muted-foreground">
                {{ marketCode }}
            </span>
        </div>
        <slot name="value" />
    </LocalizedLink>
</template>
