<script setup lang="ts">
import { computed } from 'vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const { locale } = useI18n();
const currentDir = computed(() => locale.value === 'ar' ? 'rtl' : 'ltr');

const isOpen = usePage().props.sidebarOpen;
</script>

<template>
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col" :dir="currentDir" :lang="locale">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :dir="currentDir" :lang="locale">
        <slot />
    </SidebarProvider>
</template>
