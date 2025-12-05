<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Props {
    title: string;
    description?: string;
    icon: Component;
    iconClass?: string;
    class?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
</script>

<template>
    <Card :class="props.class">
        <CardHeader class="pb-3">
            <CardTitle class="flex items-center gap-2 text-base">
                <component :is="icon" :class="['size-4', iconClass]" />
                {{ title }}
                <span v-if="description" class="text-xs font-normal text-muted-foreground">
                    {{ description }}
                </span>
            </CardTitle>
        </CardHeader>
        <CardContent>
            <slot />
        </CardContent>
    </Card>
</template>
