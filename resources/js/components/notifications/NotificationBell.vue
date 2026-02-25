<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useNotifications } from '@/composables/useNotifications';
import { usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import NotificationDropdown from './NotificationDropdown.vue';

const page = usePage();
const userId = (page.props.auth as { user?: { id: string } })?.user?.id ?? '';

const { notifications, unreadCount, markAsRead, markAllAsRead } =
    useNotifications(userId);

const showDropdown = ref(false);

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value;
};

const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.notification-bell')) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="notification-bell relative">
        <Button
            variant="ghost"
            size="icon"
            class="relative"
            @click="toggleDropdown"
        >
            <Bell class="size-5" />

            <!-- Unread badge -->
            <span
                v-if="unreadCount > 0"
                class="absolute -end-1 -top-1 flex size-5 items-center justify-center rounded-full bg-destructive text-[10px] font-bold text-destructive-foreground"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </Button>

        <!-- Dropdown -->
        <NotificationDropdown
            v-if="showDropdown"
            :notifications="notifications"
            @mark-read="markAsRead"
            @mark-all-read="markAllAsRead"
            @close="showDropdown = false"
        />
    </div>
</template>
