<script setup lang="ts">
import AssetSelector from '@/components/AssetSelector.vue';
import CompoundAlertBuilder from '@/components/alerts/CompoundAlertBuilder.vue';
import DeliveryConfig from '@/components/alerts/DeliveryConfig.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import {
    Stepper,
    StepperDescription,
    StepperIndicator,
    StepperItem,
    StepperSeparator,
    StepperTitle,
    StepperTrigger,
} from '@/components/ui/stepper';
import { Switch } from '@/components/ui/switch';
import { useAlerts } from '@/composables/useAlerts';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import type {
    AlertDirection,
    AlertParameters,
    AlertPriority,
    AlertScope,
    AlertTriggerType,
    AlertType,
    DeliveryConfig as DeliveryConfigType,
    EscalationConfig,
} from '@/types/alerts';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    BarChart3,
    Bell,
    Brain,
    Check,
    ChevronLeft,
    ChevronRight,
    Lightbulb,
    Save,
    Settings2,
    Shapes,
    SlidersHorizontal,
    Target,
    TrendingUp,
    Zap,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

interface AssetInfo {
    id: string;
    symbol: string;
    name: string;
    name_ar: string;
    last_price: number;
}

interface AlertTypeOption {
    label: string;
    triggers: Record<string, string>;
}

interface FilterOption {
    id: string;
    name: string;
    name_ar: string;
}

interface Props {
    asset?: AssetInfo | null;
    templates: Array<{
        id: string;
        name: string;
        type: AlertType;
        trigger_type: string;
    }>;
    userAssets: Array<{ asset: AssetInfo }>;
    alertTypes: Record<AlertType, AlertTypeOption>;
    markets?: FilterOption[];
    sectors?: FilterOption[];
}

const props = defineProps<Props>();

const { alertTypeLabels, triggerTypeLabels, getDefaultParameters } =
    useAlerts();

// Stepper state
const currentStep = ref(1);

// Steps definition
const steps = [
    {
        step: 1,
        title: 'alerts.steps.type',
        description: 'alerts.steps.type_description',
        icon: Zap,
    },
    {
        step: 2,
        title: 'alerts.steps.trigger',
        description: 'alerts.steps.trigger_description',
        icon: Target,
    },
    {
        step: 3,
        title: 'alerts.steps.parameters',
        description: 'alerts.steps.parameters_description',
        icon: Settings2,
    },
    {
        step: 4,
        title: 'alerts.steps.delivery',
        description: 'alerts.steps.delivery_description',
        icon: Bell,
    },
    {
        step: 5,
        title: 'alerts.steps.options',
        description: 'alerts.steps.options_description',
        icon: SlidersHorizontal,
    },
];

// Form state using Inertia useForm for validation handling
const form = useForm({
    asset_id: props.asset?.id || (null as string | null),
    type: 'price' as AlertType,
    trigger_type: 'target_price' as AlertTriggerType,
    scope: 'single_asset' as AlertScope,
    direction: 'above' as AlertDirection,
    condition_logic: 'single' as 'single' | 'and' | 'or',
    parameters: {} as AlertParameters,
    compound_conditions: [] as Array<{
        id: string;
        type:
            | 'price_threshold'
            | 'signal'
            | 'anomaly'
            | 'pattern'
            | 'recommendation'
            | 'prediction';
        parameters: Record<string, unknown>;
    }>,
    priority: 'medium' as AlertPriority,
    is_recurring: false,
    cooldown_minutes: 60,
    market_hours_only: true,
    max_triggers: null as number | null,
    delivery_config: {
        channels: ['telegram', 'in_app'],
        sound_enabled: true,
    } as DeliveryConfigType,
    escalation_config: {
        enabled: false,
        levels: [],
        max_escalations: 0,
    } as EscalationConfig,
});

const selectedAssetObject = ref<AssetInfo | null>(props.asset || null);

// Initialize parameters when trigger type changes
watch(
    () => form.trigger_type,
    (newType) => {
        form.parameters = getDefaultParameters(newType);
    },
);

// Initialize on mount
form.parameters = getDefaultParameters(form.trigger_type);

const availableTriggers = computed(() => {
    const typeOption = props.alertTypes[form.type];
    return typeOption?.triggers || {};
});

const selectedAssetInfo = computed(() => {
    if (selectedAssetObject.value) {
        return selectedAssetObject.value;
    }
    if (props.asset && props.asset.id === form.asset_id) {
        return props.asset;
    }
    return props.userAssets.find((w) => w.asset.id === form.asset_id)?.asset;
});

const currentPrice = computed(() => selectedAssetInfo.value?.last_price || 0);

const handleTypeChange = (type: AlertType) => {
    form.type = type;
    // Auto-select first trigger type for this alert type
    const triggers = props.alertTypes[type]?.triggers || {};
    const firstTrigger = Object.keys(triggers)[0] as AlertTriggerType;
    if (firstTrigger) {
        form.trigger_type = firstTrigger;
    }
};

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

// Step validation
const canProceed = computed(() => {
    switch (currentStep.value) {
        case 1:
            return !!form.type;
        case 2:
            return !!form.trigger_type;
        case 3:
            return !!form.asset_id || form.scope !== 'single_asset';
        case 4:
            return (
                form.delivery_config.channels &&
                form.delivery_config.channels.length > 0
            );
        case 5:
            return true;
        default:
            return true;
    }
});

const nextStep = () => {
    if (currentStep.value < steps.length && canProceed.value) {
        currentStep.value++;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const handleSubmit = () => {
    form.post('/alerts');
};

const breadcrumbs: BreadcrumbItemType[] = [
    { title: t('alerts.title'), href: '/alerts' },
    { title: t('alerts.create'), href: '/alerts/create' },
];

const alertTypesList: AlertType[] = [
    'price',
    'prediction',
    'signal',
    'anomaly',
    'pattern',
    'recommendation',
];
const priorities: AlertPriority[] = ['critical', 'high', 'medium', 'low'];
const directions: AlertDirection[] = [
    'above',
    'below',
    'both',
    'cross_up',
    'cross_down',
];
</script>

<template>
    <Head :title="t('alerts.create')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    @click="router.visit('/alerts')"
                >
                    <ArrowLeft class="size-4 rtl:rotate-180" />
                </Button>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ t('alerts.create') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ t('alerts.create_description') }}
                    </p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-4">
                <!-- Stepper Sidebar -->
                <div class="lg:col-span-1">
                    <Card class="sticky top-6">
                        <CardContent class="p-4">
                            <Stepper
                                v-model="currentStep"
                                orientation="vertical"
                                class="w-full flex-col"
                            >
                                <StepperItem
                                    v-for="step in steps"
                                    :key="step.step"
                                    v-slot="{ state }"
                                    :step="step.step"
                                    class="relative flex w-full items-start gap-4"
                                >
                                    <StepperTrigger as-child>
                                        <Button
                                            :variant="
                                                state === 'active'
                                                    ? 'default'
                                                    : state === 'completed'
                                                      ? 'outline'
                                                      : 'ghost'
                                            "
                                            size="icon"
                                            class="z-10 shrink-0 rounded-full"
                                            :class="{
                                                'bg-foreground text-background':
                                                    state === 'active',
                                                'border-foreground text-foreground':
                                                    state === 'completed',
                                            }"
                                        >
                                            <Check
                                                v-if="state === 'completed'"
                                                class="size-4"
                                            />
                                            <StepperIndicator v-else>
                                                <component
                                                    :is="step.icon"
                                                    class="size-4"
                                                />
                                            </StepperIndicator>
                                        </Button>
                                    </StepperTrigger>

                                    <div class="flex flex-col gap-1 py-2">
                                        <StepperTitle
                                            :class="{
                                                'font-semibold text-foreground':
                                                    state === 'active',
                                                'text-muted-foreground':
                                                    state !== 'active',
                                            }"
                                        >
                                            {{ t(step.title) }}
                                        </StepperTitle>
                                        <StepperDescription
                                            class="sr-only text-xs text-muted-foreground md:not-sr-only"
                                        >
                                            {{ t(step.description) }}
                                        </StepperDescription>
                                    </div>

                                    <StepperSeparator
                                        v-if="step.step < steps.length"
                                        class="absolute start-[18px] top-[40px] block h-[calc(100%-16px)] w-0.5 shrink-0 rounded-full bg-muted group-data-[state=completed]:bg-foreground"
                                    />
                                </StepperItem>
                            </Stepper>
                        </CardContent>
                    </Card>
                </div>

                <!-- Main Form Content -->
                <div class="space-y-6 lg:col-span-3">
                    <!-- Step 1: Alert Type -->
                    <Card v-show="currentStep === 1">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Zap class="size-5" />
                                {{ t('alerts.steps.type') }}
                            </CardTitle>
                            <CardDescription>{{
                                t('alerts.steps.type_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <button
                                    v-for="type in alertTypesList"
                                    :key="type"
                                    type="button"
                                    class="flex flex-col items-center gap-3 rounded-md border-2 p-6 transition-all hover:border-foreground/50 hover:bg-muted/30"
                                    :class="{
                                        'border-foreground bg-muted':
                                            form.type === type,
                                        'border-border': form.type !== type,
                                    }"
                                    @click="handleTypeChange(type)"
                                >
                                    <div
                                        class="flex size-12 items-center justify-center rounded-full"
                                        :class="{
                                            'bg-muted text-foreground':
                                                form.type === type,
                                            'bg-muted text-muted-foreground':
                                                form.type !== type,
                                        }"
                                    >
                                        <component
                                            :is="getTypeIcon(type)"
                                            class="size-6"
                                        />
                                    </div>
                                    <span class="text-sm font-medium">
                                        {{
                                            locale === 'ar'
                                                ? alertTypeLabels[type].ar
                                                : alertTypeLabels[type].en
                                        }}
                                    </span>
                                </button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Step 2: Trigger Condition -->
                    <Card v-show="currentStep === 2">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Target class="size-5" />
                                {{ t('alerts.steps.trigger') }}
                            </CardTitle>
                            <CardDescription>{{
                                t('alerts.steps.trigger_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-2">
                                <Label>{{
                                    t('alerts.fields.trigger_type')
                                }}</Label>
                                <Select v-model="form.trigger_type">
                                    <SelectTrigger class="w-full">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(
                                                label, trigger
                                            ) in availableTriggers"
                                            :key="trigger"
                                            :value="trigger"
                                        >
                                            {{ label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="form.errors.trigger_type"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.trigger_type }}
                                </p>
                            </div>

                            <!-- Trigger-specific quick preview -->
                            <div class="rounded-lg border bg-muted/30 p-4">
                                <h4 class="mb-2 text-sm font-medium">
                                    {{ t('alerts.trigger_preview') }}
                                </h4>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        locale === 'ar'
                                            ? triggerTypeLabels[
                                                  form.trigger_type
                                              ]?.ar
                                            : triggerTypeLabels[
                                                  form.trigger_type
                                              ]?.en
                                    }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Step 3: Alert Settings -->
                    <Card v-show="currentStep === 3">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Settings2 class="size-5" />
                                {{ t('alerts.steps.parameters') }}
                            </CardTitle>
                            <CardDescription>{{
                                t('alerts.steps.parameters_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Asset Selection with Search and Filters -->
                            <AssetSelector
                                v-model="form.asset_id"
                                :selected-asset="selectedAssetObject"
                                :markets="markets"
                                :sectors="sectors"
                                @update:selected-asset="
                                    selectedAssetObject = $event
                                "
                            />
                            <p
                                v-if="form.errors.asset_id"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.asset_id }}
                            </p>
                            <p
                                v-if="currentPrice > 0"
                                class="text-sm text-muted-foreground"
                            >
                                {{ t('alerts.current_price') }}:
                                {{ currentPrice }} {{ t('common.currency') }}
                            </p>

                            <!-- Price Alert Parameters -->
                            <template
                                v-if="form.trigger_type === 'target_price'"
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.target_price')
                                        }}</Label>
                                        <Input
                                            v-model.number="
                                                form.parameters.target_price
                                            "
                                            type="number"
                                            step="0.01"
                                            :placeholder="
                                                currentPrice.toString()
                                            "
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.direction')
                                        }}</Label>
                                        <Select v-model="form.direction">
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
                            <template v-else-if="form.trigger_type === 'zone'">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.zone_low')
                                        }}</Label>
                                        <Input
                                            v-model.number="
                                                form.parameters.zone_low
                                            "
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
                                                form.parameters.zone_high
                                            "
                                            type="number"
                                            step="0.01"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- Daily Change Parameters -->
                            <template
                                v-else-if="form.trigger_type === 'daily_change'"
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.threshold_percent')
                                        }}</Label>
                                        <Input
                                            v-model.number="
                                                form.parameters
                                                    .threshold_percent
                                            "
                                            type="number"
                                            step="0.1"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.direction')
                                        }}</Label>
                                        <Select v-model="form.direction">
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

                            <!-- Gap Alert Parameters -->
                            <template v-else-if="form.trigger_type === 'gap'">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.gap_threshold')
                                        }}</Label>
                                        <Input
                                            v-model.number="
                                                form.parameters
                                                    .gap_threshold_percent
                                            "
                                            type="number"
                                            step="0.5"
                                            placeholder="3"
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t('alerts.fields.gap_hint') }}
                                        </p>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.direction')
                                        }}</Label>
                                        <Select v-model="form.direction">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="above">{{
                                                    t('alerts.direction.gap_up')
                                                }}</SelectItem>
                                                <SelectItem value="below">{{
                                                    t(
                                                        'alerts.direction.gap_down',
                                                    )
                                                }}</SelectItem>
                                                <SelectItem value="both">{{
                                                    t('alerts.direction.both')
                                                }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </template>

                            <!-- 52-Week High/Low Parameters -->
                            <template
                                v-else-if="form.trigger_type === '52week'"
                            >
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.52week_type')
                                    }}</Label>
                                    <Select v-model="form.parameters.type">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="high">{{
                                                t('alerts.52week.new_high')
                                            }}</SelectItem>
                                            <SelectItem value="low">{{
                                                t('alerts.52week.new_low')
                                            }}</SelectItem>
                                            <SelectItem value="both">{{
                                                t('alerts.52week.both')
                                            }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p class="text-xs text-muted-foreground">
                                        {{ t('alerts.fields.52week_hint') }}
                                    </p>
                                </div>
                            </template>

                            <!-- Prediction Parameters -->
                            <template
                                v-else-if="form.trigger_type === 'prediction'"
                            >
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{
                                            t('alerts.fields.horizon')
                                        }}</Label>
                                        <Select
                                            v-model="form.parameters.horizon"
                                        >
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
                                                form.parameters.min_confidence
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
                                v-else-if="form.trigger_type === 'signal'"
                            >
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.min_strength')
                                    }}</Label>
                                    <Input
                                        v-model.number="
                                            form.parameters.min_strength
                                        "
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        max="1"
                                    />
                                </div>
                            </template>

                            <!-- Anomaly Parameters -->
                            <template
                                v-else-if="form.trigger_type === 'anomaly'"
                            >
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.min_confidence')
                                    }}</Label>
                                    <Input
                                        v-model.number="
                                            form.parameters.min_confidence
                                        "
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        max="1"
                                    />
                                </div>
                            </template>

                            <!-- Compound Intelligence Parameters -->
                            <template
                                v-else-if="
                                    form.trigger_type ===
                                    'compound_intelligence'
                                "
                            >
                                <CompoundAlertBuilder
                                    :conditions="form.compound_conditions"
                                    :logic="
                                        form.condition_logic === 'single'
                                            ? 'and'
                                            : form.condition_logic
                                    "
                                    @update:conditions="
                                        form.compound_conditions = $event;
                                        form.parameters = {
                                            conditions: $event,
                                            logic: form.condition_logic,
                                        };
                                    "
                                    @update:logic="
                                        form.condition_logic = $event;
                                        form.parameters = {
                                            conditions:
                                                form.compound_conditions,
                                            logic: $event,
                                        };
                                    "
                                />
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Step 4: Delivery Settings -->
                    <Card v-show="currentStep === 4">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Bell class="size-5" />
                                {{ t('alerts.steps.delivery') }}
                            </CardTitle>
                            <CardDescription>{{
                                t('alerts.steps.delivery_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <DeliveryConfig
                                :delivery-config="form.delivery_config"
                                :escalation-config="form.escalation_config"
                                :priority="form.priority"
                                @update:delivery-config="
                                    form.delivery_config = $event
                                "
                                @update:escalation-config="
                                    form.escalation_config = $event
                                "
                                @update:priority="form.priority = $event"
                            />
                        </CardContent>
                    </Card>

                    <!-- Step 5: Advanced Options -->
                    <Card v-show="currentStep === 5">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <SlidersHorizontal class="size-5" />
                                {{ t('alerts.steps.options') }}
                            </CardTitle>
                            <CardDescription>{{
                                t('alerts.steps.options_description')
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.priority')
                                    }}</Label>
                                    <Select v-model="form.priority">
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
                                <div class="grid gap-2">
                                    <Label>{{
                                        t('alerts.fields.cooldown')
                                    }}</Label>
                                    <Input
                                        v-model.number="form.cooldown_minutes"
                                        type="number"
                                        min="0"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        {{ t('alerts.fields.cooldown_hint') }}
                                    </p>
                                </div>
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
                                <Switch v-model:checked="form.is_recurring" />
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
                                <Switch
                                    v-model:checked="form.market_hours_only"
                                />
                            </div>

                            <!-- Summary before submit -->
                            <div class="rounded-lg border bg-muted/30 p-4">
                                <h4 class="mb-3 font-medium">
                                    {{ t('alerts.summary') }}
                                </h4>
                                <div class="space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">{{
                                            t('alerts.fields.type')
                                        }}</span>
                                        <span class="font-medium">
                                            {{
                                                locale === 'ar'
                                                    ? alertTypeLabels[form.type]
                                                          .ar
                                                    : alertTypeLabels[form.type]
                                                          .en
                                            }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">{{
                                            t('alerts.fields.trigger')
                                        }}</span>
                                        <span class="font-medium">
                                            {{
                                                locale === 'ar'
                                                    ? triggerTypeLabels[
                                                          form.trigger_type
                                                      ]?.ar
                                                    : triggerTypeLabels[
                                                          form.trigger_type
                                                      ]?.en
                                            }}
                                        </span>
                                    </div>
                                    <div
                                        v-if="selectedAssetInfo"
                                        class="flex justify-between text-sm"
                                    >
                                        <span class="text-muted-foreground">{{
                                            t('alerts.fields.asset')
                                        }}</span>
                                        <span class="font-medium">{{
                                            selectedAssetInfo.symbol
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">{{
                                            t('alerts.fields.priority')
                                        }}</span>
                                        <span class="font-medium">{{
                                            t(
                                                `alerts.priority.${form.priority}`,
                                            )
                                        }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted-foreground">{{
                                            t('alerts.fields.channels')
                                        }}</span>
                                        <span class="font-medium">{{
                                            form.delivery_config.channels
                                                ?.length || 0
                                        }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Server Validation Errors -->
                            <Alert v-if="form.hasErrors" variant="destructive">
                                <AlertDescription>
                                    <ul class="list-inside list-disc space-y-1">
                                        <li
                                            v-for="(error, key) in form.errors"
                                            :key="key"
                                        >
                                            {{ error }}
                                        </li>
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>

                    <!-- Navigation Buttons -->
                    <div class="flex items-center justify-between">
                        <Button
                            variant="outline"
                            :disabled="currentStep === 1"
                            @click="prevStep"
                        >
                            <ChevronLeft class="me-2 size-4 rtl:rotate-180" />
                            {{ t('common.previous') }}
                        </Button>

                        <div class="flex items-center gap-2">
                            <span class="text-sm text-muted-foreground">
                                {{ currentStep }} / {{ steps.length }}
                            </span>
                        </div>

                        <Button
                            v-if="currentStep < steps.length"
                            :disabled="!canProceed"
                            @click="nextStep"
                        >
                            {{ t('common.next') }}
                            <ChevronRight class="ms-2 size-4 rtl:rotate-180" />
                        </Button>
                        <Button
                            v-else
                            :disabled="
                                (!form.asset_id &&
                                    form.scope === 'single_asset') ||
                                form.processing
                            "
                            @click="handleSubmit"
                        >
                            <Save class="me-2 size-4" />
                            {{
                                form.processing
                                    ? t('common.saving')
                                    : t('alerts.create_alert')
                            }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
