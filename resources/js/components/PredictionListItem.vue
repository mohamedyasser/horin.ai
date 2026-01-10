<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import LocalizedLink from '@/components/LocalizedLink.vue';
import AssetDisplay from '@/components/AssetDisplay.vue';

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
