<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AlertTriggerType, AlertDirection, AlertParameters } from '@/types/alerts';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const { t } = useI18n();

interface Props {
    triggerType: AlertTriggerType;
    parameters: AlertParameters;
    direction: AlertDirection;
    currentPrice?: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:parameters': [value: AlertParameters];
    'update:direction': [value: AlertDirection];
}>();

const directions: AlertDirection[] = ['above', 'below', 'both', 'cross_up', 'cross_down'];

const updateParameter = <K extends keyof AlertParameters>(key: K, value: AlertParameters[K]) => {
    emit('update:parameters', { ...props.parameters, [key]: value });
};

const percentFromCurrent = computed(() => {
    if (!props.currentPrice || !props.parameters.target_price) return null;
    const diff = ((props.parameters.target_price - props.currentPrice) / props.currentPrice) * 100;
    return diff.toFixed(2);
});
</script>

<template>
    <div class="space-y-4">
        <!-- Target Price Configuration -->
        <template v-if="triggerType === 'target_price'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.target_price') }}</Label>
                    <Input
                        :model-value="parameters.target_price"
                        type="number"
                        step="0.01"
                        :placeholder="currentPrice?.toString()"
                        @update:model-value="updateParameter('target_price', Number($event))"
                    />
                    <p v-if="currentPrice" class="text-xs text-muted-foreground">
                        {{ t('alerts.current_price') }}: {{ currentPrice }} {{ t('common.currency') }}
                        <span v-if="percentFromCurrent" class="ms-1" :class="{ 'text-green-600': Number(percentFromCurrent) > 0, 'text-red-600': Number(percentFromCurrent) < 0 }">
                            ({{ Number(percentFromCurrent) > 0 ? '+' : '' }}{{ percentFromCurrent }}%)
                        </span>
                    </p>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.direction') }}</Label>
                    <Select :model-value="direction" @update:model-value="emit('update:direction', $event as AlertDirection)">
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

        <!-- Breakout Configuration -->
        <template v-else-if="triggerType === 'breakout'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.level') }}</Label>
                    <Input
                        :model-value="parameters.level"
                        type="number"
                        step="0.01"
                        @update:model-value="updateParameter('level', Number($event))"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.direction') }}</Label>
                    <Select :model-value="direction" @update:model-value="emit('update:direction', $event as AlertDirection)">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="above">{{ t('alerts.direction.above') }}</SelectItem>
                            <SelectItem value="below">{{ t('alerts.direction.below') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="grid gap-2">
                <Label>{{ t('alerts.fields.confirmation') }}</Label>
                <Select :model-value="parameters.confirmation || 'sustained'" @update:model-value="updateParameter('confirmation', $event)">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="instant">{{ t('alerts.confirmation.instant') }}</SelectItem>
                        <SelectItem value="sustained">{{ t('alerts.confirmation.sustained') }}</SelectItem>
                        <SelectItem value="closing">{{ t('alerts.confirmation.closing') }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </template>

        <!-- Zone Configuration -->
        <template v-else-if="triggerType === 'zone'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.zone_low') }}</Label>
                    <Input
                        :model-value="parameters.zone_low"
                        type="number"
                        step="0.01"
                        @update:model-value="updateParameter('zone_low', Number($event))"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.zone_high') }}</Label>
                    <Input
                        :model-value="parameters.zone_high"
                        type="number"
                        step="0.01"
                        @update:model-value="updateParameter('zone_high', Number($event))"
                    />
                </div>
            </div>
            <div class="grid gap-2">
                <Label>{{ t('alerts.fields.trigger_on') }}</Label>
                <Select :model-value="parameters.trigger_on || 'enter'" @update:model-value="updateParameter('trigger_on', $event)">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="enter">{{ t('alerts.trigger_on.enter') }}</SelectItem>
                        <SelectItem value="exit">{{ t('alerts.trigger_on.exit') }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </template>

        <!-- Gap Configuration -->
        <template v-else-if="triggerType === 'gap'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.gap_threshold') }}</Label>
                    <Input
                        :model-value="parameters.gap_threshold_percent"
                        type="number"
                        step="0.5"
                        min="0"
                        @update:model-value="updateParameter('gap_threshold_percent', Number($event))"
                    />
                    <p class="text-xs text-muted-foreground">{{ t('alerts.fields.gap_threshold_hint') }}</p>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.direction') }}</Label>
                    <Select :model-value="direction" @update:model-value="emit('update:direction', $event as AlertDirection)">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="above">{{ t('alerts.direction.gap_up') }}</SelectItem>
                            <SelectItem value="below">{{ t('alerts.direction.gap_down') }}</SelectItem>
                            <SelectItem value="both">{{ t('alerts.direction.both') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </template>

        <!-- 52 Week High/Low Configuration -->
        <template v-else-if="triggerType === '52week'">
            <div class="grid gap-2">
                <Label>{{ t('alerts.fields.52week_type') }}</Label>
                <Select :model-value="parameters.type || 'high'" @update:model-value="updateParameter('type', $event)">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="high">{{ t('alerts.52week.high') }}</SelectItem>
                        <SelectItem value="low">{{ t('alerts.52week.low') }}</SelectItem>
                        <SelectItem value="both">{{ t('alerts.52week.both') }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </template>

        <!-- Daily Change Configuration -->
        <template v-else-if="triggerType === 'daily_change'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.threshold_percent') }}</Label>
                    <Input
                        :model-value="parameters.threshold_percent"
                        type="number"
                        step="0.5"
                        min="0"
                        @update:model-value="updateParameter('threshold_percent', Number($event))"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.direction') }}</Label>
                    <Select :model-value="direction" @update:model-value="emit('update:direction', $event as AlertDirection)">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="above">{{ t('alerts.direction.up') }}</SelectItem>
                            <SelectItem value="below">{{ t('alerts.direction.down') }}</SelectItem>
                            <SelectItem value="both">{{ t('alerts.direction.both') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="grid gap-2">
                <Label>{{ t('alerts.fields.from_reference') }}</Label>
                <Select :model-value="parameters.from_reference || 'open'" @update:model-value="updateParameter('from_reference', $event)">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="open">{{ t('alerts.reference.open') }}</SelectItem>
                        <SelectItem value="previous_close">{{ t('alerts.reference.previous_close') }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </template>

        <!-- Entry Return Configuration -->
        <template v-else-if="triggerType === 'entry_return'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.entry_price') }}</Label>
                    <Input
                        :model-value="parameters.entry_price"
                        type="number"
                        step="0.01"
                        @update:model-value="updateParameter('entry_price', Number($event))"
                    />
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.tolerance_percent') }}</Label>
                    <Input
                        :model-value="parameters.tolerance_percent"
                        type="number"
                        step="0.1"
                        min="0"
                        @update:model-value="updateParameter('tolerance_percent', Number($event))"
                    />
                </div>
            </div>
        </template>
    </div>
</template>
