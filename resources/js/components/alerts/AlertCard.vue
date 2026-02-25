<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAlerts } from '@/composables/useAlerts';
import type { Alert } from '@/types/alerts';
import { Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Brain,
    Clock,
    Edit,
    Lightbulb,
    MoreVertical,
    Shapes,
    Trash2,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface Props {
    alert: Alert;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    snooze: [preset: string];
    delete: [];
}>();

const { alertTypeLabels, triggerTypeLabels, priorityLabels, statusLabels } =
    useAlerts();

const showSnoozeMenu = ref(false);

const typeLabel = computed(() => {
    const labels = alertTypeLabels[props.alert.type];
    return locale.value === 'ar' ? labels?.ar : labels?.en;
});

const triggerLabel = computed(() => {
    const labels = triggerTypeLabels[props.alert.trigger_type];
    return locale.value === 'ar' ? labels?.ar : labels?.en;
});

const priorityConfig = computed(
    () => priorityLabels[props.alert.priority] || priorityLabels.medium,
);
const statusConfig = computed(
    () => statusLabels[props.alert.status] || statusLabels.active,
);

const snoozePresets = [
    { value: '1h', label: t('alerts.snooze.1h') },
    { value: '4h', label: t('alerts.snooze.4h') },
    { value: '1d', label: t('alerts.snooze.1d') },
    {
        value: 'until_market_close',
        label: t('alerts.snooze.until_market_close'),
    },
    { value: 'until_market_open', label: t('alerts.snooze.until_market_open') },
];

const handleSnooze = (preset: string) => {
    emit('snooze', preset);
    showSnoozeMenu.value = false;
};

const getTypeIcon = (type: string) => {
    switch (type) {
        case 'price':
            return TrendingUp;
        case 'prediction':
            return Brain;
        case 'signal':
            return BarChart3;
        case 'anomaly':
            return AlertTriangle;
        case 'recommendation':
            return Lightbulb;
        case 'pattern':
            return Shapes;
        default:
            return TrendingUp;
    }
};

const getStatusVariant = (status: string) => {
    switch (status) {
        case 'active':
            return 'default';
        case 'triggered':
            return 'secondary';
        case 'paused':
            return 'outline';
        case 'expired':
            return 'destructive';
        default:
            return 'default';
    }
};

const getPriorityVariant = (priority: string) => {
    switch (priority) {
        case 'critical':
            return 'destructive';
        case 'high':
            return 'default';
        case 'medium':
            return 'secondary';
        case 'low':
            return 'outline';
        default:
            return 'secondary';
    }
};

const TypeIcon = computed(() => getTypeIcon(props.alert.type));
</script>

<template>
    <Card class="transition-colors hover:bg-muted/30">
        <CardContent class="p-4">
            <div class="flex items-start justify-between gap-4">
                <!-- Left: Alert Info -->
                <div class="flex items-start gap-4">
                    <!-- Icon -->
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted"
                    >
                        <component
                            :is="TypeIcon"
                            class="size-5 text-foreground"
                        />
                    </div>

                    <!-- Details -->
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold">
                                {{
                                    alert.asset?.symbol ||
                                    t('alerts.multiple_assets')
                                }}
                            </h3>
                            <Badge :variant="getStatusVariant(alert.status)">
                                {{
                                    locale === 'ar'
                                        ? statusConfig.ar
                                        : statusConfig.en
                                }}
                            </Badge>
                            <Badge
                                v-if="alert.is_snoozed"
                                variant="outline"
                                class="bg-muted"
                            >
                                {{ t('alerts.snoozed') }}
                            </Badge>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {{ typeLabel }} &bull; {{ triggerLabel }}
                        </p>

                        <!-- Parameters summary -->
                        <div class="text-sm text-foreground/80">
                            <template
                                v-if="alert.trigger_type === 'target_price'"
                            >
                                {{ t('alerts.params.target') }}:
                                {{ alert.parameters.target_price }}
                                {{ t('common.currency') }}
                                <span class="text-muted-foreground"
                                    >({{ alert.parameters.direction }})</span
                                >
                            </template>
                            <template
                                v-else-if="
                                    alert.trigger_type === 'daily_change'
                                "
                            >
                                {{ alert.parameters.threshold_percent }}%
                                {{ t('alerts.params.change') }}
                            </template>
                            <template v-else-if="alert.trigger_type === 'zone'">
                                {{ t('alerts.params.zone') }}:
                                {{ alert.parameters.zone_low }} -
                                {{ alert.parameters.zone_high }}
                                {{ t('common.currency') }}
                            </template>
                            <template
                                v-else-if="alert.scope !== 'single_asset'"
                            >
                                {{ t(`alerts.scope.${alert.scope}`) }}
                            </template>
                        </div>

                        <!-- Triggered info -->
                        <div
                            v-if="alert.triggered_count > 0"
                            class="text-xs text-muted-foreground"
                        >
                            {{
                                t('alerts.triggered_count', {
                                    count: alert.triggered_count,
                                })
                            }}
                            <span v-if="alert.last_triggered_at">
                                &bull; {{ t('alerts.last_triggered') }}:
                                {{
                                    new Date(
                                        alert.last_triggered_at,
                                    ).toLocaleString(locale)
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right: Actions -->
                <div class="flex items-center gap-2">
                    <!-- Priority badge -->
                    <Badge
                        :variant="getPriorityVariant(alert.priority)"
                        class="hidden sm:inline-flex"
                    >
                        {{
                            locale === 'ar'
                                ? priorityConfig.ar
                                : priorityConfig.en
                        }}
                    </Badge>

                    <!-- Actions dropdown -->
                    <DropdownMenu v-model:open="showSnoozeMenu">
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" size="icon">
                                <MoreVertical class="size-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <!-- Snooze options -->
                            <DropdownMenuItem
                                v-for="preset in snoozePresets"
                                :key="preset.value"
                                @click="handleSnooze(preset.value)"
                            >
                                <Clock class="me-2 size-4" />
                                {{ preset.label }}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="`/alerts/${alert.id}/edit`">
                                    <Edit class="me-2 size-4" />
                                    {{ t('common.edit') }}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                class="text-destructive focus:text-destructive"
                                @click="$emit('delete')"
                            >
                                <Trash2 class="me-2 size-4" />
                                {{ t('common.delete') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
