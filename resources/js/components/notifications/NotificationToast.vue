<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AlertNotification } from '@/types/alerts';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { X, AlertCircle, AlertTriangle, Bell, Info } from 'lucide-vue-next';

const { locale } = useI18n();

interface Props {
    notification: AlertNotification;
    duration?: number;
}

const props = withDefaults(defineProps<Props>(), {
    duration: 5000,
});

const emit = defineEmits<{
    dismiss: [];
}>();

const isVisible = ref(true);

const dismiss = () => {
    isVisible.value = false;
    setTimeout(() => emit('dismiss'), 300);
};

onMounted(() => {
    setTimeout(dismiss, props.duration);
});

const PriorityIcon = computed(() => {
    switch (props.notification.priority) {
        case 'critical': return AlertCircle;
        case 'high': return AlertTriangle;
        case 'medium': return Bell;
        default: return Info;
    }
});

const priorityStyles = computed(() => {
    switch (props.notification.priority) {
        case 'critical': return 'border-destructive bg-destructive/10';
        case 'high': return 'border-orange-500 bg-orange-500/10';
        case 'medium': return 'border-yellow-500 bg-yellow-500/10';
        default: return 'border-border bg-muted/50';
    }
});

const iconStyles = computed(() => {
    switch (props.notification.priority) {
        case 'critical': return 'text-destructive';
        case 'high': return 'text-orange-500';
        case 'medium': return 'text-yellow-500';
        default: return 'text-muted-foreground';
    }
});

const notificationTitle = computed(() => {
    const data = props.notification.data as { title_ar?: string } | undefined;
    return locale.value === 'ar' && data?.title_ar ? data.title_ar : props.notification.title;
});

const notificationBody = computed(() => {
    const data = props.notification.data as { body_ar?: string } | undefined;
    return locale.value === 'ar' && data?.body_ar ? data.body_ar : props.notification.body;
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
                'w-full max-w-sm border-s-4 shadow-lg',
                priorityStyles
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
                    </div>
                    <Button
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
