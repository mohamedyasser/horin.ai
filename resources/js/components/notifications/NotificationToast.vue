<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { AlertNotification } from '@/types/alerts';
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Bell,
    Check,
    ExternalLink,
    Info,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { locale, t } = useI18n();

interface Props {
    notification: AlertNotification;
    duration?: number;
    playSound?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    duration: 5000,
    playSound: true,
});

const emit = defineEmits<{
    dismiss: [];
    acknowledge: [notificationId: string];
    view: [alertId: string];
}>();

const isVisible = ref(true);
const isAcknowledged = ref(false);

const dismiss = () => {
    isVisible.value = false;
    setTimeout(() => emit('dismiss'), 300);
};

const acknowledge = () => {
    isAcknowledged.value = true;
    emit('acknowledge', props.notification.id);
    dismiss();
};

const viewAlert = () => {
    const alertId = props.notification.data?.alert_id;
    if (alertId) {
        emit('view', alertId);
        router.visit(`/alerts/${alertId}`);
    }
};

// Play notification sound for critical alerts
const playNotificationSound = () => {
    if (!props.playSound) return;

    try {
        const audio = new Audio('/sounds/notification.mp3');
        audio.volume = props.notification.priority === 'critical' ? 0.8 : 0.5;
        audio.play().catch(() => {
            // Audio autoplay may be blocked, ignore
        });
    } catch {
        // Audio not supported
    }
};

onMounted(() => {
    // Critical alerts require acknowledgment and don't auto-dismiss
    if (props.notification.priority !== 'critical') {
        setTimeout(dismiss, props.duration);
    }

    // Play sound for high/critical priority
    if (
        props.notification.priority === 'critical' ||
        props.notification.priority === 'high'
    ) {
        playNotificationSound();
    }
});

const PriorityIcon = computed(() => {
    switch (props.notification.priority) {
        case 'critical':
            return AlertCircle;
        case 'high':
            return AlertTriangle;
        case 'medium':
            return Bell;
        default:
            return Info;
    }
});

const priorityStyles = computed(() => {
    switch (props.notification.priority) {
        case 'critical':
            return 'border-destructive bg-destructive/10';
        case 'high':
            return 'border-foreground bg-muted';
        case 'medium':
            return 'border-muted-foreground bg-muted/50';
        default:
            return 'border-border bg-muted/50';
    }
});

const iconStyles = computed(() => {
    switch (props.notification.priority) {
        case 'critical':
            return 'text-destructive';
        case 'high':
            return 'text-foreground';
        case 'medium':
            return 'text-muted-foreground';
        default:
            return 'text-muted-foreground';
    }
});

const notificationTitle = computed(() => {
    const data = props.notification.data as { title_ar?: string } | undefined;
    return locale.value === 'ar' && data?.title_ar
        ? data.title_ar
        : props.notification.title;
});

const notificationBody = computed(() => {
    const data = props.notification.data as { body_ar?: string } | undefined;
    return locale.value === 'ar' && data?.body_ar
        ? data.body_ar
        : props.notification.body;
});
</script>

<template>
    <Transition
        enter-active-class="duration-300 ease-out"
        enter-from-class="translate-y-2 opacity-0 sm:translate-x-2 sm:translate-y-0"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <Card
            v-if="isVisible"
            :class="[
                'w-full max-w-sm border-s-4',
                priorityStyles,
                notification.priority === 'critical' && 'animate-pulse',
            ]"
        >
            <CardContent class="p-4">
                <div class="flex items-start gap-3">
                    <component
                        :is="PriorityIcon"
                        :class="['size-5 shrink-0', iconStyles]"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium">
                            {{ notificationTitle }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ notificationBody }}
                        </p>

                        <!-- Action buttons for critical alerts -->
                        <div
                            v-if="notification.priority === 'critical'"
                            class="mt-3 flex gap-2"
                        >
                            <Button
                                size="sm"
                                variant="outline"
                                @click="viewAlert"
                            >
                                <ExternalLink class="me-1.5 size-3.5" />
                                {{ t('alerts.view_alert', 'View Alert') }}
                            </Button>
                            <Button size="sm" @click="acknowledge">
                                <Check class="me-1.5 size-3.5" />
                                {{ t('alerts.acknowledge', 'Acknowledge') }}
                            </Button>
                        </div>
                    </div>
                    <Button
                        v-if="notification.priority !== 'critical'"
                        variant="ghost"
                        size="icon"
                        class="size-6 shrink-0"
                        @click="dismiss"
                    >
                        <span class="sr-only">Close</span>
                        <X class="size-4" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    </Transition>
</template>
