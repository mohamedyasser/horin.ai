<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { AlertNotification } from '@/types/alerts';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Inbox } from 'lucide-vue-next';

const { t, locale } = useI18n();

interface Props {
    notifications: AlertNotification[];
}

defineProps<Props>();

const emit = defineEmits<{
    markRead: [id: string];
    markAllRead: [];
    close: [];
}>();

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now.getTime() - date.getTime();

    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return t('time.just_now');
    if (minutes < 60) return t('time.minutes_ago', { count: minutes });
    if (hours < 24) return t('time.hours_ago', { count: hours });
    return t('time.days_ago', { count: days });
};

const getPriorityStyles = (priority: string) => {
    switch (priority) {
        case 'critical': return 'border-s-destructive';
        case 'high': return 'border-s-orange-500';
        case 'medium': return 'border-s-yellow-500';
        default: return 'border-s-muted';
    }
};
</script>

<template>
    <Card class="absolute end-0 top-full z-50 mt-2 w-96 shadow-lg">
        <!-- Header -->
        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-3">
            <CardTitle class="text-base">{{ t('notifications.title') }}</CardTitle>
            <Button
                v-if="notifications.some(n => !n.read_at)"
                variant="ghost"
                size="sm"
                class="text-primary"
                @click="emit('markAllRead')"
            >
                {{ t('notifications.mark_all_read') }}
            </Button>
        </CardHeader>

        <Separator />

        <!-- Notifications list -->
        <CardContent class="max-h-96 overflow-y-auto p-0">
            <template v-if="notifications.length > 0">
                <button
                    v-for="notification in notifications.slice(0, 10)"
                    :key="notification.id"
                    :class="[
                        'block w-full border-s-4 px-4 py-3 text-start transition-colors',
                        getPriorityStyles(notification.priority),
                        notification.read_at
                            ? 'bg-background hover:bg-muted/50'
                            : 'bg-muted/30 hover:bg-muted/50',
                    ]"
                    @click="emit('markRead', notification.id)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ locale === 'ar' && notification.data?.title_ar ? notification.data.title_ar : notification.title }}
                            </p>
                            <p class="line-clamp-2 text-sm text-muted-foreground">
                                {{ locale === 'ar' && notification.data?.body_ar ? notification.data.body_ar : notification.body }}
                            </p>
                        </div>
                        <span class="shrink-0 text-xs text-muted-foreground">
                            {{ formatTime(notification.created_at) }}
                        </span>
                    </div>
                </button>
            </template>

            <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                <Inbox class="size-8 text-muted-foreground/50" />
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t('notifications.empty') }}
                </p>
            </div>
        </CardContent>

        <Separator />

        <!-- Footer -->
        <CardFooter class="justify-center p-3">
            <Button as-child variant="ghost" size="sm" @click="emit('close')">
                <Link href="/alerts/history">
                    {{ t('notifications.view_all') }}
                </Link>
            </Button>
        </CardFooter>
    </Card>
</template>
