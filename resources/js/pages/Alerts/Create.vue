<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { AlertType, AlertTriggerType, AlertDirection, AlertPriority, AlertScope, AlertParameters } from '@/types/alerts';
import type { BreadcrumbItemType } from '@/types';
import { useAlerts } from '@/composables/useAlerts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Alert, AlertDescription } from '@/components/ui/alert';
import AssetSelector from '@/components/AssetSelector.vue';
import {
    TrendingUp,
    Brain,
    BarChart3,
    AlertTriangle,
    Lightbulb,
    Shapes,
    ArrowLeft,
    Save,
} from 'lucide-vue-next';

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
    templates: Array<{ id: string; name: string; type: AlertType; trigger_type: string }>;
    userAssets: Array<{ asset: AssetInfo }>;
    alertTypes: Record<AlertType, AlertTypeOption>;
    markets?: FilterOption[];
    sectors?: FilterOption[];
}

const props = defineProps<Props>();

const { alertTypeLabels, triggerTypeLabels, getDefaultParameters } = useAlerts();

// Form state
const selectedType = ref<AlertType>('price');
const selectedTriggerType = ref<AlertTriggerType>('target_price');
const selectedAsset = ref<string>(props.asset?.id || '');
const selectedAssetObject = ref<AssetInfo | null>(props.asset || null);
const parameters = ref<AlertParameters>({});
const direction = ref<AlertDirection>('above');
const priority = ref<AlertPriority>('medium');
const scope = ref<AlertScope>('single_asset');
const isRecurring = ref(false);
const cooldownMinutes = ref(60);
const marketHoursOnly = ref(true);
const maxTriggers = ref<number | undefined>(undefined);

// Initialize parameters when trigger type changes
watch(selectedTriggerType, (newType) => {
    parameters.value = getDefaultParameters(newType);
});

// Initialize on mount
parameters.value = getDefaultParameters(selectedTriggerType.value);

const availableTriggers = computed(() => {
    const typeOption = props.alertTypes[selectedType.value];
    return typeOption?.triggers || {};
});

const selectedAssetInfo = computed(() => {
    if (selectedAssetObject.value) {
        return selectedAssetObject.value;
    }
    if (props.asset && props.asset.id === selectedAsset.value) {
        return props.asset;
    }
    return props.userAssets.find(w => w.asset.id === selectedAsset.value)?.asset;
});

const currentPrice = computed(() => selectedAssetInfo.value?.last_price || 0);

const handleTypeChange = (type: AlertType) => {
    selectedType.value = type;
    // Auto-select first trigger type for this alert type
    const triggers = props.alertTypes[type]?.triggers || {};
    const firstTrigger = Object.keys(triggers)[0] as AlertTriggerType;
    if (firstTrigger) {
        selectedTriggerType.value = firstTrigger;
    }
};

const getTypeIcon = (type: AlertType) => {
    switch (type) {
        case 'price': return TrendingUp;
        case 'prediction': return Brain;
        case 'signal': return BarChart3;
        case 'anomaly': return AlertTriangle;
        case 'recommendation': return Lightbulb;
        case 'pattern': return Shapes;
        default: return TrendingUp;
    }
};

const handleSubmit = () => {
    const data = {
        asset_id: selectedAsset.value || null,
        type: selectedType.value,
        trigger_type: selectedTriggerType.value,
        scope: scope.value,
        direction: direction.value,
        parameters: parameters.value,
        priority: priority.value,
        is_recurring: isRecurring.value,
        cooldown_minutes: cooldownMinutes.value,
        market_hours_only: marketHoursOnly.value,
        max_triggers: maxTriggers.value || null,
    };

    router.post('/alerts', data);
};

const breadcrumbs: BreadcrumbItemType[] = [
    { title: t('alerts.title'), href: '/alerts' },
    { title: t('alerts.create'), href: '/alerts/create' },
];

const alertTypesList: AlertType[] = ['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation'];
const priorities: AlertPriority[] = ['critical', 'high', 'medium', 'low'];
const directions: AlertDirection[] = ['above', 'below', 'both', 'cross_up', 'cross_down'];
</script>

<template>
    <Head :title="t('alerts.create')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" @click="router.visit('/alerts')">
                    <ArrowLeft class="size-4 rtl:rotate-180" />
                </Button>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ t('alerts.create') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ t('alerts.create_description') }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Form -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Step 1: Select Alert Type -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('alerts.steps.type') }}</CardTitle>
                            <CardDescription>{{ t('alerts.steps.type_description') }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <button
                                    v-for="type in alertTypesList"
                                    :key="type"
                                    type="button"
                                    class="flex flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors hover:border-primary/50"
                                    :class="{ 'border-primary bg-primary/5': selectedType === type, 'border-border': selectedType !== type }"
                                    @click="handleTypeChange(type)"
                                >
                                    <component :is="getTypeIcon(type)" class="size-6" />
                                    <span class="text-sm font-medium">
                                        {{ locale === 'ar' ? alertTypeLabels[type].ar : alertTypeLabels[type].en }}
                                    </span>
                                </button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Step 2: Select Trigger -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('alerts.steps.trigger') }}</CardTitle>
                            <CardDescription>{{ t('alerts.steps.trigger_description') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-2">
                                <Label>{{ t('alerts.fields.trigger_type') }}</Label>
                                <Select v-model="selectedTriggerType">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="(label, trigger) in availableTriggers"
                                            :key="trigger"
                                            :value="trigger"
                                        >
                                            {{ label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Step 3: Configure Parameters -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('alerts.steps.parameters') }}</CardTitle>
                            <CardDescription>{{ t('alerts.steps.parameters_description') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Asset Selection with Search and Filters -->
                            <AssetSelector
                                v-model="selectedAsset"
                                :selected-asset="selectedAssetObject"
                                :markets="markets"
                                :sectors="sectors"
                                @update:selected-asset="selectedAssetObject = $event"
                            />
                            <p v-if="currentPrice > 0" class="text-sm text-muted-foreground">
                                {{ t('alerts.current_price') }}: {{ currentPrice }} {{ t('common.currency') }}
                            </p>

                            <!-- Price Alert Parameters -->
                            <template v-if="selectedTriggerType === 'target_price'">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{ t('alerts.fields.target_price') }}</Label>
                                        <Input
                                            v-model.number="parameters.target_price"
                                            type="number"
                                            step="0.01"
                                            :placeholder="currentPrice.toString()"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{ t('alerts.fields.direction') }}</Label>
                                        <Select v-model="direction">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="dir in directions" :key="dir" :value="dir">
                                                    {{ t(`alerts.direction.${dir}`) }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </template>

                            <!-- Zone Alert Parameters -->
                            <template v-else-if="selectedTriggerType === 'zone'">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{ t('alerts.fields.zone_low') }}</Label>
                                        <Input
                                            v-model.number="parameters.zone_low"
                                            type="number"
                                            step="0.01"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{ t('alerts.fields.zone_high') }}</Label>
                                        <Input
                                            v-model.number="parameters.zone_high"
                                            type="number"
                                            step="0.01"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- Daily Change Parameters -->
                            <template v-else-if="selectedTriggerType === 'daily_change'">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{ t('alerts.fields.threshold_percent') }}</Label>
                                        <Input
                                            v-model.number="parameters.threshold_percent"
                                            type="number"
                                            step="0.1"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{ t('alerts.fields.direction') }}</Label>
                                        <Select v-model="direction">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="above">{{ t('alerts.direction.above') }}</SelectItem>
                                                <SelectItem value="below">{{ t('alerts.direction.below') }}</SelectItem>
                                                <SelectItem value="both">{{ t('alerts.direction.both') }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </template>

                            <!-- Prediction Parameters -->
                            <template v-else-if="selectedTriggerType === 'prediction'">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="grid gap-2">
                                        <Label>{{ t('alerts.fields.horizon') }}</Label>
                                        <Select v-model="parameters.horizon">
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1hour">1 {{ t('common.hour') }}</SelectItem>
                                                <SelectItem value="4hours">4 {{ t('common.hours') }}</SelectItem>
                                                <SelectItem value="1day">1 {{ t('common.day') }}</SelectItem>
                                                <SelectItem value="1week">1 {{ t('common.week') }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid gap-2">
                                        <Label>{{ t('alerts.fields.min_confidence') }}</Label>
                                        <Input
                                            v-model.number="parameters.min_confidence"
                                            type="number"
                                            step="0.05"
                                            min="0"
                                            max="1"
                                        />
                                    </div>
                                </div>
                            </template>

                            <!-- Signal Parameters -->
                            <template v-else-if="selectedTriggerType === 'signal'">
                                <div class="grid gap-2">
                                    <Label>{{ t('alerts.fields.min_strength') }}</Label>
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
                            <template v-else-if="selectedTriggerType === 'anomaly'">
                                <div class="grid gap-2">
                                    <Label>{{ t('alerts.fields.min_confidence') }}</Label>
                                    <Input
                                        v-model.number="parameters.min_confidence"
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        max="1"
                                    />
                                </div>
                            </template>
                        </CardContent>
                    </Card>

                    <!-- Step 4: Advanced Options -->
                    <Card>
                        <CardHeader>
                            <CardTitle>{{ t('alerts.steps.options') }}</CardTitle>
                            <CardDescription>{{ t('alerts.steps.options_description') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label>{{ t('alerts.fields.priority') }}</Label>
                                    <Select v-model="priority">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="p in priorities" :key="p" :value="p">
                                                {{ t(`alerts.priority.${p}`) }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid gap-2">
                                    <Label>{{ t('alerts.fields.cooldown') }}</Label>
                                    <Input
                                        v-model.number="cooldownMinutes"
                                        type="number"
                                        min="0"
                                    />
                                    <p class="text-xs text-muted-foreground">{{ t('alerts.fields.cooldown_hint') }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between rounded-lg border p-4">
                                <div class="space-y-0.5">
                                    <Label>{{ t('alerts.fields.recurring') }}</Label>
                                    <p class="text-sm text-muted-foreground">{{ t('alerts.fields.recurring_hint') }}</p>
                                </div>
                                <Switch v-model:checked="isRecurring" />
                            </div>

                            <div class="flex items-center justify-between rounded-lg border p-4">
                                <div class="space-y-0.5">
                                    <Label>{{ t('alerts.fields.market_hours_only') }}</Label>
                                    <p class="text-sm text-muted-foreground">{{ t('alerts.fields.market_hours_hint') }}</p>
                                </div>
                                <Switch v-model:checked="marketHoursOnly" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar Summary -->
                <div class="space-y-6">
                    <Card class="sticky top-6">
                        <CardHeader>
                            <CardTitle>{{ t('alerts.summary') }}</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">{{ t('alerts.fields.type') }}</span>
                                    <span class="font-medium">
                                        {{ locale === 'ar' ? alertTypeLabels[selectedType].ar : alertTypeLabels[selectedType].en }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">{{ t('alerts.fields.trigger') }}</span>
                                    <span class="font-medium">
                                        {{ locale === 'ar' ? triggerTypeLabels[selectedTriggerType]?.ar : triggerTypeLabels[selectedTriggerType]?.en }}
                                    </span>
                                </div>
                                <div v-if="selectedAssetInfo" class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">{{ t('alerts.fields.asset') }}</span>
                                    <span class="font-medium">{{ selectedAssetInfo.symbol }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-muted-foreground">{{ t('alerts.fields.priority') }}</span>
                                    <span class="font-medium">{{ t(`alerts.priority.${priority}`) }}</span>
                                </div>
                            </div>

                            <Alert v-if="!selectedAsset && scope === 'single_asset'" variant="destructive">
                                <AlertDescription>{{ t('alerts.validation.asset_required') }}</AlertDescription>
                            </Alert>

                            <Button
                                class="w-full"
                                :disabled="!selectedAsset && scope === 'single_asset'"
                                @click="handleSubmit"
                            >
                                <Save class="me-2 size-4" />
                                {{ t('alerts.create_alert') }}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
