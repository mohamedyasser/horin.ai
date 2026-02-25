<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

export interface AssetDisplayProps {
    symbol: string;
    name?: string;
    logo?: string | null;
    marketCode?: string | null;
    sectorName?: string | null;
    size?: 'sm' | 'md' | 'lg';
    showName?: boolean;
    showLogo?: boolean;
    layout?: 'inline' | 'stacked';
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<AssetDisplayProps>(), {
    size: 'md',
    showName: true,
    showLogo: true,
    layout: 'inline',
});

const initials = computed(() => {
    return props.symbol.slice(0, 2).toUpperCase();
});

const sizeClasses = computed(() => {
    const sizes = {
        sm: {
            avatar: 'size-6',
            symbol: 'text-sm font-medium',
            name: 'text-xs',
            badge: 'text-[10px] px-1 py-0.5',
        },
        md: {
            avatar: 'size-8',
            symbol: 'text-sm font-medium',
            name: 'text-sm',
            badge: 'text-xs px-1.5 py-0.5',
        },
        lg: {
            avatar: 'size-16',
            symbol: 'text-2xl font-bold sm:text-3xl',
            name: 'text-lg',
            badge: 'text-sm px-3 py-1',
        },
    };
    return sizes[props.size];
});
</script>

<template>
    <div
        :class="[
            'flex',
            layout === 'stacked' ? 'flex-col gap-1' : 'items-center gap-2',
            props.class,
        ]"
    >
        <!-- Logo/Avatar -->
        <Avatar v-if="showLogo" :class="sizeClasses.avatar">
            <AvatarImage v-if="logo" :src="logo" :alt="symbol" />
            <AvatarFallback
                :class="[
                    'bg-muted font-medium text-foreground',
                    size === 'sm'
                        ? 'text-[10px]'
                        : size === 'lg'
                          ? 'text-xl'
                          : 'text-xs',
                ]"
            >
                {{ initials }}
            </AvatarFallback>
        </Avatar>

        <!-- Content -->
        <div :class="layout === 'stacked' ? '' : 'flex items-center gap-2'">
            <!-- Symbol -->
            <span :class="sizeClasses.symbol">{{ symbol }}</span>

            <!-- Badge (Market Code or Sector) -->
            <span
                v-if="marketCode"
                :class="[
                    sizeClasses.badge,
                    'rounded bg-muted text-muted-foreground',
                ]"
            >
                {{ marketCode }}
            </span>
            <span
                v-else-if="sectorName"
                :class="[
                    sizeClasses.badge,
                    'rounded bg-muted text-muted-foreground',
                ]"
            >
                {{ sectorName }}
            </span>
        </div>

        <!-- Name -->
        <p
            v-if="showName && name"
            :class="[sizeClasses.name, 'text-muted-foreground']"
        >
            {{ name }}
        </p>
    </div>
</template>
