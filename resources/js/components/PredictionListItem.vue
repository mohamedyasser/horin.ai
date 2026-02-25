<script setup lang="ts">
import AssetDisplay from '@/components/AssetDisplay.vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import type { HTMLAttributes } from 'vue';

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
            '-mx-2 flex items-center justify-between rounded px-2 py-1 transition-colors hover:bg-muted/30',
            props.class,
        ]"
    >
        <AssetDisplay
            :symbol="symbol"
            :market-code="marketCode"
            :show-name="false"
            :show-logo="false"
            size="sm"
        />
        <slot name="value" />
    </LocalizedLink>
</template>
