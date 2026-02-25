<script setup lang="ts">
import AlertChainManager from '@/components/alerts/AlertChainManager.vue';
import DeliveryConfig from '@/components/alerts/DeliveryConfig.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useAlerts } from '@/composables/useAlerts';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import type {
    Alert,
    AlertDirection,
    AlertParameters,
    AlertPriority,
    AlertType,
    DeliveryConfig as DeliveryConfigType,
    EscalationConfig,
} from '@/types/alerts';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    Brain,
    Lightbulb,
    Save,
    Shapes,
    Trash2,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface Props {
    alert: {
        data: Alert;
    };
    userAlerts?: Alert[];
    templates: Array<{
        id: string;
        name: string;
        type: AlertType;
        trigger_type: string;
    }>;
}

const props = defineProps<Props>();

const { alertTypeLabels, triggerTypeLabels } = useAlerts();

const alert = computed(() => props.alert.data);

// Form state - initialize from existing alert
const parameters = ref<AlertParameters>({ ...alert.value.parameters });
const direction = ref<AlertDirection>(alert.value.direction || 'above');
const priority = ref<AlertPriority>(alert.value.priority);
const isRecurring = ref(alert.value.is_recurring);
const cooldownMinutes = ref(alert.value.cooldown_minutes);
const marketHoursOnly = ref(alert.value.market_hours_only);
const maxTriggers = ref<number | undefined>(alert.value.max_triggers);
const status = ref(alert.value.status);
const deliveryConfig = ref<DeliveryConfigType>(
    alert.value.delivery_config || {
        channels: ['telegram', 'in_app'],
        sound_enabled: true,
    },
);
const escalationConfig = ref<EscalationConfig>(
    alert.value.escalation_config || {
        enabled: false,
        levels: [],
        max_escalations: 0,
    },
);

const currentPrice = computed(() => alert.value.asset?.last_price || 0);

const getTypeIcon = (type: AlertType) => {
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

const handleSubmit = () => {
    const data = {
        direction: direction.value,
        parameters: parameters.value,
        priority: priority.value,
        is_recurring: isRecurring.value,
        cooldown_minutes: cooldownMinutes.value,
        market_hours_only: marketHoursOnly.value,
        max_triggers: maxTriggers.value || null,
        status: status.value,
        delivery_config: deliveryConfig.value,
        escalation_config: escalationConfig.value,
    };

    router.patch(`/alerts/${alert.value.id}`, data);
};

const handleDelete = () => {
    if (confirm(t('alerts.confirmDelete'))) {
        router.delete(`/alerts/${alert.value.id}`);
    }
};

const handleRefresh = () => {
    router.reload({ only: ['alert', 'userAlerts'] });
};

const breadcrumbs: BreadcrumbItemType[] = [
    { title: t('alerts.title'), href: '/alerts' },
    { title: t('common.edit'), href: `/alerts/${alert.value.id}/edit` },
];

const priorities: AlertPriority[] = ['critical', 'high', 'medium', 'low'];
const directions: AlertDirection[] = [
    'above',
    'below',
    'both',
    'cross_up',
    'cross_down',
];
const statuses = ['active', 'paused'] as const;

const TypeIcon = computed(() => getTypeIcon(alert.value.type));
</script>

<template>
    <Head :title="t('alerts.edit')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="router.visit('/alerts')"
                    >
                        <ArrowLeft class="size-4 rtl:rotate-180" />
                    </Button>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-full bg-muted"
                        >
                            <component
                                :is="TypeIcon"
                                class="size-5 text-foreground"
                            />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight">
                                {{
                                    alert.asset?.symbol ||
                                    t('alerts.multiple_assets')
                                }}
                            </h1>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    locale === 'ar'
                                        ? alertTypeLabels[alert.type].ar
                                        : alertTypeLabels[alert.type].en
                                }}
                                &bull;
                                {{
                                    locale === 'ar'
                                        ? triggerTypeLabels[alert.trigger_type]
                                              ?.ar
                                        : triggerTypeLabels[alert.trigger_type]
                                              ?.en
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Badge
                        :variant="
                            alert.status === 'active' ? 'default' : 'secondary'
                        "
                    >
                        {{ t(`alerts.status.${alert.status}`) }}
                    </Badge>
                    <Button
                        variant="destructive"
                        size="icon"
                        @click="handleDelete"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Form -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Alert Info (Read-only) -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('alerts.info') }}</CardTitle>
                            <CardDescription>{{
                                t('alerts.info_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="space-y-1">
                                    <Label class="text-muted-foreground">{{
                                        t('alerts.fields.type')
                                    }}</Label>
                                    <p class="font-medium">
                                        {{
                                            locale === 'ar'
                                                ? alertTypeLabels[alert.type].ar
                                                : alertTypeLabels[alert.type].en
                                        }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-muted-foreground">{{
                                        t('alerts.fields.trigger')
                                    }}</Label>
                                    <p class="font-medium">
                                        {{
                                            locale === 'ar'
                                                ? triggerTypeLabels[
                                                      alert.trigger_type
                                                  ]?.ar
                                                : triggerTypeLabels[
                                                      alert.trigger_type
                                                  ]?.en
                                        }}
                                    </p>
                                </div>
                                <div v-if="alert.asset" class="space-y-1">
                                    <Label class="text-muted-foreground">{{
                                        t('alerts.fields.asset')
                                    }}</Label>
                                    <p class="font-medium">
                                        {{ alert.asset.symbol }} -
                                        {{
                                            locale === 'ar'
                                                ? alert.asset.name_ar
                                                : alert.asset.name
                                        }}
                                    </p>
                                </div>
                                <div class="space-y-1">
                                    <Label class="text-muted-foreground">{{
                                        t('alerts.fields.triggered_count')
                                    }}</Label>
                                    <p class="font-medium tabular-nums">
                                        {{ alert.triggered_count }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Parameters -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{
                                t('alerts.steps.parameters')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('alerts.steps.parameters_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Price Alert Parameters -->
                            <template
                                v-if="alert.trigger_type === 'target_price'"
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.target_price')
                                        }}</Label>
                                        <Input
                                            v-model.number="
                                                parameters.target_price
                                            "
                                            type="number"
                                            step="0.01"
                                        />
                                        <p
                                            v-if="currentPrice > 0"
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t('alerts.current_price') }}:
                                            {{ currentPrice }}
                                            {{ t('common.currency') }}
                                        </p>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.direction')
                                        }}</Label>
                                        <Select v-model="direction">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="dir in directions"
                                                    :key="dir"
                                                    :value="dir"
                                                >
                                                    {{
                                                        t(
                                                            `alerts.direction.${dir}`,
                                                        )
                                                    }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </template>

                            <!-- Zone Alert Parameters -->
                            <template v-else-if="alert.trigger_type === 'zone'">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.zone_low')
                                        }}</Label>
                                        <Input
                                            v-model.number="parameters.zone_low"
                                            type="number"
                                            step="0.01"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.zone_high')
                                        }}</Label>
                                        <Input
                                            v-model.number="
                                                parameters.zone_high
                                            "
                                            type="number"
                                            step="0.01"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- Daily Change Parameters -->
                            <template
                                v-else-if="
                                    alert.trigger_type === 'daily_change'
                                "
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.threshold_percent')
                                        }}</Label>
                                        <Input
                                            v-model.number="
                                                parameters.threshold_percent
                                            "
                                            type="number"
                                            step="0.1"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.direction')
                                        }}</Label>
                                        <Select v-model="direction">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="above">{{
                                                    t('alerts.direction.above')
                                                }}</SelectItem>
                                                <SelectItem value="below">{{
                                                    t('alerts.direction.below')
                                                }}</SelectItem>
                                                <SelectItem value="both">{{
                                                    t('alerts.direction.both')
                                                }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </template>

                            <!-- Prediction Parameters -->
                            <template
                                v-else-if="alert.trigger_type === 'prediction'"
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.horizon')
                                        }}</Label>
                                        <Select v-model="parameters.horizon">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1hour"
                                                    >1
                                                    {{
                                                        t('common.hour')
                                                    }}</SelectItem
                                                >
                                                <SelectItem value="4hours"
                                                    >4
                                                    {{
                                                        t('common.hours')
                                                    }}</SelectItem
                                                >
                                                <SelectItem value="1day"
                                                    >1
                                                    {{
                                                        t('common.day')
                                                    }}</SelectItem
                                                >
                                                <SelectItem value="1week"
                                                    >1
                                                    {{
                                                        t('common.week')
                                                    }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.min_confidence')
                                        }}</Label>
                                        <Input
                                            v-model.number="
                                                parameters.min_confidence
                                            "
                                            type="number"
                                            step="0.05"
                                            min="0"
                                            max="1"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- Signal Parameters -->
                            <template
                                v-else-if="alert.trigger_type === 'signal'"
                            >
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.min_strength')
                                    }}</Label>
                                    <Input
                                        v-model.number="parameters.min_strength"
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        max="1"
                                    />
                                </div>
                            </template>

                            <!-- Anomaly Parameters -->
                            <template
                                v-else-if="alert.trigger_type === 'anomaly'"
                            >
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.min_confidence')
                                    }}</Label>
                                    <Input
                                        v-model.number="
                                            parameters.min_confidence
                                        "
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        max="1"
                                    />
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Options -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{
                                t('alerts.steps.options')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('alerts.steps.options_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.status')
                                    }}</Label>
                                    <Select v-model="status">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="s in statuses"
                                                :key="s"
                                                :value="s"
                                            >
                                                {{ t(`alerts.status.${s}`) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.priority')
                                    }}</Label>
                                    <Select v-model="priority">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="p in priorities"
                                                :key="p"
                                                :value="p"
                                            >
                                                {{ t(`alerts.priority.${p}`) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label>{{ t('alerts.fields.cooldown') }}</Label>
                                <Input
                                    v-model.number="cooldownMinutes"
                                    type="number"
                                    min="0"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ t('alerts.fields.cooldown_hint') }}
                                </p>
                            </div>

                            <div
                                class="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div class="space-y-0.5">
                                    <Label>{{
                                        t('alerts.fields.recurring')
                                    }}</Label>
                                    <p class="text-sm text-muted-foreground">
                                        {{ t('alerts.fields.recurring_hint') }}
                                    </p>
                                </div>
                                <Switch v-model:checked="isRecurring" />
                            </div>

                            <div
                                class="flex items-center justify-between rounded-lg border p-4"
                            >
                                <div class="space-y-0.5">
                                    <Label>{{
                                        t('alerts.fields.market_hours_only')
                                    }}</Label>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            t('alerts.fields.market_hours_hint')
                                        }}
                                    </p>
                                </div>
                                <Switch v-model:checked="marketHoursOnly" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Delivery Configuration -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{
                                t('alerts.steps.delivery')
                            }}</CardTitle>
                            <CardDescription>{{
                                t('alerts.steps.delivery_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DeliveryConfig
                                :delivery-config="deliveryConfig"
                                :escalation-config="escalationConfig"
                                :priority="priority"
                                @update:delivery-config="
                                    deliveryConfig = $event
                                "
                                @update:escalation-config="
                                    escalationConfig = $event
                                "
                                @update:priority="priority = $event"
                            />
                        </CardContent>
                    </Card>

                    <!-- Alert Chains -->
                    <AlertChainManager
                        v-if="userAlerts && userAlerts.length > 0"
                        :alert="alert"
                        :user-alerts="userAlerts"
                        @refresh="handleRefresh"
                    />
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <Card class="sticky top-6">
                        <CardHeader>
                            <CardTitle>{{ t('common.actions') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <Button class="w-full" @click="handleSubmit">
                                <Save class="me-2 size-4" />
                                {{ t('common.save_changes') }}
                            </Button>
                            <Button
                                variant="outline"
                                class="w-full"
                                @click="router.visit(`/alerts/${alert.id}`)"
                            >
                                {{ t('common.view') }}
                            </Button>
                            <Button
                                variant="destructive"
                                class="w-full"
                                @click="handleDelete"
                            >
                                <Trash2 class="me-2 size-4" />
                                {{ t('common.delete') }}
                            </Button>
                        </CardContent>
                    </Card>

                    <!-- Activity -->
                    <Card v-if="alert.triggered_count > 0">
                        <CardHeader>
                            <CardTitle>{{ t('alerts.activity') }}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">{{
                                        t('alerts.fields.triggered_count')
                                    }}</span>
                                    <span class="font-medium tabular-nums">{{
                                        alert.triggered_count
                                    }}</span>
                                </div>
                                <div
                                    v-if="alert.last_triggered_at"
                                    class="flex justify-between"
                                >
                                    <span class="text-muted-foreground">{{
                                        t('alerts.last_triggered')
                                    }}</span>
                                    <span class="font-medium tabular-nums">{{
                                        new Date(
                                            alert.last_triggered_at,
                                        ).toLocaleDateString(locale)
                                    }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
