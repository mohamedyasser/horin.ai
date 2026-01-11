<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Alert } from '@/types/alerts';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ChevronRight, Link2, Clock, Timer, Target, Bell } from 'lucide-vue-next';

const { t } = useI18n();

interface AlertChain {
    id?: string;
    name: string;
    trigger_alert_id: string;
    activate_alert_id: string;
    delay_minutes: number;
    expires_after_minutes?: number;
}

interface Props {
    alerts: Alert[];
    existingChain?: AlertChain;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    save: [chain: Omit<AlertChain, 'id'>];
    cancel: [];
}>();

const chainName = ref(props.existingChain?.name || '');
const triggerAlertId = ref(props.existingChain?.trigger_alert_id || '');
const activateAlertId = ref(props.existingChain?.activate_alert_id || '');
const delayMinutes = ref(props.existingChain?.delay_minutes || 0);
const expiresAfterMinutes = ref<number | undefined>(props.existingChain?.expires_after_minutes);

const activeAlerts = computed(() =>
    props.alerts.filter(a => a.status === 'active' || a.status === 'chained')
);

const chainableAlerts = computed(() =>
    props.alerts.filter(a => a.status === 'chained' && a.id !== triggerAlertId.value)
);

const canSave = computed(() =>
    chainName.value && triggerAlertId.value && activateAlertId.value
);

const triggerAlert = computed(() =>
    props.alerts.find(a => a.id === triggerAlertId.value)
);

const activateAlert = computed(() =>
    props.alerts.find(a => a.id === activateAlertId.value)
);

const getAlertLabel = (alert: Alert) => {
    const symbol = alert.asset?.symbol || t('alerts.multiple_assets');
    const type = alert.trigger_type.replace('_', ' ');
    return `${symbol} - ${type}`;
};

const handleSave = () => {
    emit('save', {
        name: chainName.value,
        trigger_alert_id: triggerAlertId.value,
        activate_alert_id: activateAlertId.value,
        delay_minutes: delayMinutes.value,
        expires_after_minutes: expiresAfterMinutes.value || undefined,
    });
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="flex items-center gap-2">
                <Link2 class="size-5" />
                {{ existingChain ? t('alerts.chains.edit_chain') : t('alerts.chains.create_chain') }}
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-6">
            <!-- Chain Name -->
            <div class="space-y-2">
                <Label for="chain-name">{{ t('alerts.chains.name') }}</Label>
                <Input
                    id="chain-name"
                    v-model="chainName"
                    :placeholder="t('alerts.chains.name_placeholder')"
                />
            </div>

            <!-- Alert Selection -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Trigger Alert -->
                <div class="space-y-2">
                    <Label for="trigger-alert">{{ t('alerts.chains.when_triggers') }}</Label>
                    <Select v-model="triggerAlertId">
                        <SelectTrigger id="trigger-alert">
                            <SelectValue :placeholder="t('alerts.chains.select_alert')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="alert in activeAlerts"
                                :key="alert.id"
                                :value="alert.id"
                            >
                                {{ getAlertLabel(alert) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Activate Alert -->
                <div class="space-y-2">
                    <Label for="activate-alert">{{ t('alerts.chains.then_activate') }}</Label>
                    <Select v-model="activateAlertId">
                        <SelectTrigger id="activate-alert">
                            <SelectValue :placeholder="t('alerts.chains.select_alert')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="alert in chainableAlerts"
                                :key="alert.id"
                                :value="alert.id"
                            >
                                {{ getAlertLabel(alert) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Timing Options -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Delay -->
                <div class="space-y-2">
                    <Label for="delay" class="flex items-center gap-2">
                        <Clock class="size-4" />
                        {{ t('alerts.chains.delay_minutes') }}
                    </Label>
                    <Input
                        id="delay"
                        v-model.number="delayMinutes"
                        type="number"
                        min="0"
                    />
                </div>

                <!-- Expiration -->
                <div class="space-y-2">
                    <Label for="expires" class="flex items-center gap-2">
                        <Timer class="size-4" />
                        {{ t('alerts.chains.expires_after') }}
                    </Label>
                    <Input
                        id="expires"
                        v-model.number="expiresAfterMinutes"
                        type="number"
                        min="1"
                        :placeholder="t('alerts.chains.no_expiration')"
                    />
                </div>
            </div>

            <!-- Visual Representation -->
            <div class="rounded-lg bg-muted p-4">
                <div class="flex items-center justify-center gap-4">
                    <!-- Trigger Alert Box -->
                    <div class="text-center">
                        <div class="mb-2 flex size-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                            <Target class="size-8 text-blue-600 dark:text-blue-400" />
                        </div>
                        <span class="text-sm text-muted-foreground">{{ t('alerts.chains.trigger') }}</span>
                        <p v-if="triggerAlert" class="mt-1 text-xs font-medium">
                            {{ triggerAlert.asset?.symbol }}
                        </p>
                    </div>

                    <!-- Arrow with Delay -->
                    <div class="flex items-center">
                        <div class="h-0.5 w-8 bg-border"></div>
                        <span v-if="delayMinutes > 0" class="mx-1 text-xs text-muted-foreground">
                            {{ delayMinutes }}{{ t('common.min_abbr') }}
                        </span>
                        <ChevronRight class="size-6 text-muted-foreground" />
                        <div class="h-0.5 w-8 bg-border"></div>
                    </div>

                    <!-- Activate Alert Box -->
                    <div class="text-center">
                        <div class="mb-2 flex size-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                            <Bell class="size-8 text-green-600 dark:text-green-400" />
                        </div>
                        <span class="text-sm text-muted-foreground">{{ t('alerts.chains.activate') }}</span>
                        <p v-if="activateAlert" class="mt-1 text-xs font-medium">
                            {{ activateAlert.asset?.symbol }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-2">
                <Button variant="outline" @click="$emit('cancel')">
                    {{ t('common.cancel') }}
                </Button>
                <Button :disabled="!canSave" @click="handleSave">
                    {{ existingChain ? t('common.update') : t('common.create') }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
