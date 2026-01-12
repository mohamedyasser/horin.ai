<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AlertTriggerType } from '@/types/alerts';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Plus, Trash2, GripVertical } from 'lucide-vue-next';

const { t, locale } = useI18n();

interface Condition {
    id: string;
    type: 'price_threshold' | 'signal' | 'anomaly' | 'pattern' | 'recommendation' | 'prediction';
    parameters: Record<string, unknown>;
}

interface Props {
    conditions: Condition[];
    logic: 'and' | 'or';
}

const props = withDefaults(defineProps<Props>(), {
    conditions: () => [],
    logic: 'and',
});

const emit = defineEmits<{
    'update:conditions': [conditions: Condition[]];
    'update:logic': [logic: 'and' | 'or'];
}>();

const conditionTypes = [
    { value: 'price_threshold', label: { en: 'Price Threshold', ar: 'حد السعر' }, icon: '💰' },
    { value: 'signal', label: { en: 'Technical Signal', ar: 'إشارة فنية' }, icon: '📉' },
    { value: 'anomaly', label: { en: 'Anomaly', ar: 'شذوذ' }, icon: '⚠️' },
    { value: 'pattern', label: { en: 'Chart Pattern', ar: 'نمط الرسم' }, icon: '📊' },
    { value: 'recommendation', label: { en: 'Recommendation', ar: 'توصية' }, icon: '💡' },
    { value: 'prediction', label: { en: 'AI Prediction', ar: 'توقع ذكي' }, icon: '🔮' },
];

const logicOptions = [
    { value: 'and', label: { en: 'ALL conditions (AND)', ar: 'جميع الشروط (و)' } },
    { value: 'or', label: { en: 'ANY condition (OR)', ar: 'أي شرط (أو)' } },
];

const generateId = () => Math.random().toString(36).substring(2, 9);

const addCondition = () => {
    const newCondition: Condition = {
        id: generateId(),
        type: 'price_threshold',
        parameters: { threshold: 0, direction: 'above' },
    };
    emit('update:conditions', [...props.conditions, newCondition]);
};

const removeCondition = (id: string) => {
    emit('update:conditions', props.conditions.filter(c => c.id !== id));
};

const updateCondition = (id: string, updates: Partial<Condition>) => {
    const updated = props.conditions.map(c =>
        c.id === id ? { ...c, ...updates } : c
    );
    emit('update:conditions', updated);
};

const updateConditionType = (id: string, type: Condition['type']) => {
    const defaultParams = getDefaultParams(type);
    updateCondition(id, { type, parameters: defaultParams });
};

const updateConditionParam = (id: string, key: string, value: unknown) => {
    const condition = props.conditions.find(c => c.id === id);
    if (!condition) return;
    updateCondition(id, {
        parameters: { ...condition.parameters, [key]: value }
    });
};

const getDefaultParams = (type: Condition['type']): Record<string, unknown> => {
    return {
        price_threshold: { threshold: 0, direction: 'above' },
        signal: { min_strength: 0.7, indicators: [] },
        anomaly: { min_score: 0.8, anomaly_types: [] },
        pattern: { patterns: [], min_confidence: 0.7 },
        recommendation: { recommendations: ['strong_buy', 'buy'], min_score: 0.75 },
        prediction: { direction: 'up', min_confidence: 0.75 },
    }[type] || {};
};

const getConditionLabel = (type: string) => {
    const found = conditionTypes.find(ct => ct.value === type);
    return found ? (locale.value === 'ar' ? found.label.ar : found.label.en) : type;
};

const canAddMore = computed(() => props.conditions.length < 5);
</script>

<template>
    <div class="space-y-4">
        <!-- Logic Selector -->
        <div class="space-y-2">
            <Label>{{ t('alerts.compound.logic') }}</Label>
            <div class="flex gap-2">
                <button
                    v-for="opt in logicOptions"
                    :key="opt.value"
                    type="button"
                    class="flex-1 rounded-lg border-2 px-4 py-2 text-sm font-medium transition-colors"
                    :class="{
                        'border-primary bg-primary/5': logic === opt.value,
                        'border-border hover:border-primary/50': logic !== opt.value,
                    }"
                    @click="emit('update:logic', opt.value as 'and' | 'or')"
                >
                    {{ locale === 'ar' ? opt.label.ar : opt.label.en }}
                </button>
            </div>
            <p class="text-xs text-muted-foreground">
                {{ logic === 'and'
                    ? t('alerts.compound.and_hint')
                    : t('alerts.compound.or_hint')
                }}
            </p>
        </div>

        <!-- Conditions List -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <Label>{{ t('alerts.compound.conditions') }}</Label>
                <Badge variant="outline">
                    {{ conditions.length }}/5
                </Badge>
            </div>

            <div v-if="conditions.length === 0" class="rounded-lg border-2 border-dashed p-8 text-center">
                <p class="text-sm text-muted-foreground">
                    {{ t('alerts.compound.no_conditions') }}
                </p>
                <Button variant="outline" size="sm" class="mt-3" @click="addCondition">
                    <Plus class="me-2 size-4" />
                    {{ t('alerts.compound.add_first') }}
                </Button>
            </div>

            <TransitionGroup name="list" tag="div" class="space-y-3">
                <Card v-for="(condition, index) in conditions" :key="condition.id">
                    <CardContent class="p-4">
                        <div class="flex items-start gap-3">
                            <div class="flex size-8 shrink-0 cursor-grab items-center justify-center rounded-md bg-muted">
                                <GripVertical class="size-4 text-muted-foreground" />
                            </div>

                            <div class="min-w-0 flex-1 space-y-3">
                                <!-- Condition Type -->
                                <div class="flex items-center gap-3">
                                    <Badge>{{ index + 1 }}</Badge>
                                    <Select
                                        :model-value="condition.type"
                                        @update:model-value="updateConditionType(condition.id, $event as Condition['type'])"
                                    >
                                        <SelectTrigger class="flex-1">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="ct in conditionTypes" :key="ct.value" :value="ct.value">
                                                {{ ct.icon }} {{ locale === 'ar' ? ct.label.ar : ct.label.en }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Condition Parameters -->
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <!-- Price Threshold -->
                                    <template v-if="condition.type === 'price_threshold'">
                                        <div class="grid gap-1">
                                            <Label class="text-xs">{{ t('alerts.fields.threshold') }}</Label>
                                            <Input
                                                :model-value="condition.parameters.threshold as number"
                                                type="number"
                                                step="0.01"
                                                @update:model-value="updateConditionParam(condition.id, 'threshold', Number($event))"
                                            />
                                        </div>
                                        <div class="grid gap-1">
                                            <Label class="text-xs">{{ t('alerts.fields.direction') }}</Label>
                                            <Select
                                                :model-value="condition.parameters.direction as string"
                                                @update:model-value="updateConditionParam(condition.id, 'direction', $event)"
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="above">{{ t('alerts.direction.above') }}</SelectItem>
                                                    <SelectItem value="below">{{ t('alerts.direction.below') }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </template>

                                    <!-- Signal -->
                                    <template v-else-if="condition.type === 'signal'">
                                        <div class="col-span-2 grid gap-1">
                                            <Label class="text-xs">{{ t('alerts.fields.min_strength') }}</Label>
                                            <Input
                                                :model-value="condition.parameters.min_strength as number"
                                                type="number"
                                                step="0.05"
                                                min="0"
                                                max="1"
                                                @update:model-value="updateConditionParam(condition.id, 'min_strength', Number($event))"
                                            />
                                        </div>
                                    </template>

                                    <!-- Anomaly -->
                                    <template v-else-if="condition.type === 'anomaly'">
                                        <div class="col-span-2 grid gap-1">
                                            <Label class="text-xs">{{ t('alerts.fields.min_score') }}</Label>
                                            <Input
                                                :model-value="condition.parameters.min_score as number"
                                                type="number"
                                                step="0.05"
                                                min="0"
                                                max="1"
                                                @update:model-value="updateConditionParam(condition.id, 'min_score', Number($event))"
                                            />
                                        </div>
                                    </template>

                                    <!-- Pattern -->
                                    <template v-else-if="condition.type === 'pattern'">
                                        <div class="col-span-2 grid gap-1">
                                            <Label class="text-xs">{{ t('alerts.fields.min_confidence') }}</Label>
                                            <Input
                                                :model-value="condition.parameters.min_confidence as number"
                                                type="number"
                                                step="0.05"
                                                min="0"
                                                max="1"
                                                @update:model-value="updateConditionParam(condition.id, 'min_confidence', Number($event))"
                                            />
                                        </div>
                                    </template>

                                    <!-- Recommendation -->
                                    <template v-else-if="condition.type === 'recommendation'">
                                        <div class="col-span-2 grid gap-1">
                                            <Label class="text-xs">{{ t('alerts.fields.min_score') }}</Label>
                                            <Input
                                                :model-value="condition.parameters.min_score as number"
                                                type="number"
                                                step="0.05"
                                                min="0"
                                                max="1"
                                                @update:model-value="updateConditionParam(condition.id, 'min_score', Number($event))"
                                            />
                                        </div>
                                    </template>

                                    <!-- Prediction -->
                                    <template v-else-if="condition.type === 'prediction'">
                                        <div class="grid gap-1">
                                            <Label class="text-xs">{{ t('alerts.fields.prediction_direction') }}</Label>
                                            <Select
                                                :model-value="condition.parameters.direction as string"
                                                @update:model-value="updateConditionParam(condition.id, 'direction', $event)"
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="up">{{ t('alerts.direction.bullish') }}</SelectItem>
                                                    <SelectItem value="down">{{ t('alerts.direction.bearish') }}</SelectItem>
                                                    <SelectItem value="both">{{ t('alerts.direction.both') }}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div class="grid gap-1">
                                            <Label class="text-xs">{{ t('alerts.fields.min_confidence') }}</Label>
                                            <Input
                                                :model-value="condition.parameters.min_confidence as number"
                                                type="number"
                                                step="0.05"
                                                min="0"
                                                max="1"
                                                @update:model-value="updateConditionParam(condition.id, 'min_confidence', Number($event))"
                                            />
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <Button
                                variant="ghost"
                                size="icon"
                                class="shrink-0"
                                @click="removeCondition(condition.id)"
                            >
                                <Trash2 class="size-4 text-muted-foreground hover:text-destructive" />
                            </Button>
                        </div>

                        <!-- Logic connector between conditions -->
                        <div
                            v-if="index < conditions.length - 1"
                            class="mt-3 flex items-center justify-center"
                        >
                            <Badge variant="secondary" class="px-3">
                                {{ logic === 'and'
                                    ? (locale === 'ar' ? 'و' : 'AND')
                                    : (locale === 'ar' ? 'أو' : 'OR')
                                }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>
            </TransitionGroup>

            <!-- Add Condition Button -->
            <Button
                v-if="canAddMore && conditions.length > 0"
                variant="outline"
                class="w-full"
                @click="addCondition"
            >
                <Plus class="me-2 size-4" />
                {{ t('alerts.compound.add_condition') }}
            </Button>
        </div>
    </div>
</template>

<style scoped>
.list-enter-active,
.list-leave-active {
    transition: all 0.3s ease;
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateX(-20px);
}
</style>
