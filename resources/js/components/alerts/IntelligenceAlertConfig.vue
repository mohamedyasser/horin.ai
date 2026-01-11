<script setup lang="ts">
import { } from 'vue';
import { useI18n } from 'vue-i18n';
import type { AlertTriggerType, AlertParameters } from '@/types/alerts';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Slider } from '@/components/ui/slider';

const { t, locale } = useI18n();

interface Props {
    triggerType: AlertTriggerType;
    parameters: AlertParameters;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:parameters': [value: AlertParameters];
}>();

const updateParameter = <K extends keyof AlertParameters>(key: K, value: AlertParameters[K]) => {
    emit('update:parameters', { ...props.parameters, [key]: value });
};

const toggleArrayItem = (key: keyof AlertParameters, item: string) => {
    const current = (props.parameters[key] as string[]) || [];
    const updated = current.includes(item)
        ? current.filter(i => i !== item)
        : [...current, item];
    emit('update:parameters', { ...props.parameters, [key]: updated });
};

const isSelected = (key: keyof AlertParameters, item: string): boolean => {
    const current = (props.parameters[key] as string[]) || [];
    return current.includes(item);
};

// Configuration options
const predictionTypes = [
    { value: 'price_direction', label: { en: 'Price Direction', ar: 'اتجاه السعر' } },
    { value: 'price_target', label: { en: 'Price Target', ar: 'السعر المستهدف' } },
    { value: 'trend_reversal', label: { en: 'Trend Reversal', ar: 'انعكاس الاتجاه' } },
];

const horizons = [
    { value: '1hour', label: { en: '1 Hour', ar: 'ساعة' } },
    { value: '4hours', label: { en: '4 Hours', ar: '4 ساعات' } },
    { value: '1day', label: { en: '1 Day', ar: 'يوم' } },
    { value: '1week', label: { en: '1 Week', ar: 'أسبوع' } },
];

const signalIndicators = [
    { value: 'rsi', label: 'RSI' },
    { value: 'macd', label: 'MACD' },
    { value: 'bollinger', label: 'Bollinger Bands' },
    { value: 'sma', label: 'SMA' },
    { value: 'ema', label: 'EMA' },
];

const signalTypes = [
    { value: 'buy', label: { en: 'Buy Signal', ar: 'إشارة شراء' } },
    { value: 'sell', label: { en: 'Sell Signal', ar: 'إشارة بيع' } },
    { value: 'overbought', label: { en: 'Overbought', ar: 'تشبع شرائي' } },
    { value: 'oversold', label: { en: 'Oversold', ar: 'تشبع بيعي' } },
    { value: 'divergence', label: { en: 'Divergence', ar: 'تباعد' } },
];

const anomalyTypes = [
    { value: 'price_spike', label: { en: 'Price Spike', ar: 'قفزة سعرية' } },
    { value: 'volume_surge', label: { en: 'Volume Surge', ar: 'ارتفاع حجم التداول' } },
    { value: 'volatility_burst', label: { en: 'Volatility Burst', ar: 'انفجار التذبذب' } },
    { value: 'unusual_activity', label: { en: 'Unusual Activity', ar: 'نشاط غير عادي' } },
];

const severityLevels = [
    { value: 'low', label: { en: 'Low', ar: 'منخفض' } },
    { value: 'medium', label: { en: 'Medium', ar: 'متوسط' } },
    { value: 'high', label: { en: 'High', ar: 'عالي' } },
    { value: 'critical', label: { en: 'Critical', ar: 'حرج' } },
];

const chartPatterns = [
    { value: 'head_shoulders', label: { en: 'Head & Shoulders', ar: 'الرأس والكتفين' } },
    { value: 'double_top', label: { en: 'Double Top', ar: 'القمة المزدوجة' } },
    { value: 'double_bottom', label: { en: 'Double Bottom', ar: 'القاع المزدوج' } },
    { value: 'triangle', label: { en: 'Triangle', ar: 'المثلث' } },
    { value: 'wedge', label: { en: 'Wedge', ar: 'الوتد' } },
    { value: 'flag', label: { en: 'Flag', ar: 'العلم' } },
];

const recommendations = [
    { value: 'strong_buy', label: { en: 'Strong Buy', ar: 'شراء قوي' } },
    { value: 'buy', label: { en: 'Buy', ar: 'شراء' } },
    { value: 'hold', label: { en: 'Hold', ar: 'احتفاظ' } },
    { value: 'sell', label: { en: 'Sell', ar: 'بيع' } },
    { value: 'strong_sell', label: { en: 'Strong Sell', ar: 'بيع قوي' } },
];
</script>

<template>
    <div class="space-y-4">
        <!-- Prediction Configuration -->
        <template v-if="triggerType === 'prediction'">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.prediction_type') }}</Label>
                    <Select :model-value="parameters.prediction_type || 'price_direction'" @update:model-value="updateParameter('prediction_type', $event)">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="type in predictionTypes" :key="type.value" :value="type.value">
                                {{ locale === 'ar' ? type.label.ar : type.label.en }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.horizon') }}</Label>
                    <Select :model-value="parameters.horizon || '1day'" @update:model-value="updateParameter('horizon', $event)">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="h in horizons" :key="h.value" :value="h.value">
                                {{ locale === 'ar' ? h.label.ar : h.label.en }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="grid gap-2">
                <Label>{{ t('alerts.fields.min_confidence') }}: {{ ((parameters.min_confidence || 0.75) * 100).toFixed(0) }}%</Label>
                <Slider
                    :model-value="[(parameters.min_confidence || 0.75) * 100]"
                    :min="50"
                    :max="95"
                    :step="5"
                    @update:model-value="updateParameter('min_confidence', $event[0] / 100)"
                />
            </div>
        </template>

        <!-- Signal Configuration -->
        <template v-else-if="triggerType === 'signal'">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.indicators') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="indicator in signalIndicators"
                            :key="indicator.value"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                            :class="{
                                'border-primary bg-primary/10 text-primary': isSelected('indicators', indicator.value),
                                'hover:border-primary/50': !isSelected('indicators', indicator.value),
                            }"
                            @click="toggleArrayItem('indicators', indicator.value)"
                        >
                            {{ indicator.label }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.signal_types') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="signal in signalTypes"
                            :key="signal.value"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                            :class="{
                                'border-primary bg-primary/10 text-primary': isSelected('signal_types', signal.value),
                                'hover:border-primary/50': !isSelected('signal_types', signal.value),
                            }"
                            @click="toggleArrayItem('signal_types', signal.value)"
                        >
                            {{ locale === 'ar' ? signal.label.ar : signal.label.en }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.min_strength') }}: {{ ((parameters.min_strength || 0.7) * 100).toFixed(0) }}%</Label>
                    <Slider
                        :model-value="[(parameters.min_strength || 0.7) * 100]"
                        :min="50"
                        :max="95"
                        :step="5"
                        @update:model-value="updateParameter('min_strength', $event[0] / 100)"
                    />
                </div>
            </div>
        </template>

        <!-- Anomaly Configuration -->
        <template v-else-if="triggerType === 'anomaly'">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.anomaly_types') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="type in anomalyTypes"
                            :key="type.value"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                            :class="{
                                'border-primary bg-primary/10 text-primary': isSelected('anomaly_types', type.value),
                                'hover:border-primary/50': !isSelected('anomaly_types', type.value),
                            }"
                            @click="toggleArrayItem('anomaly_types', type.value)"
                        >
                            {{ locale === 'ar' ? type.label.ar : type.label.en }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.severity') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="level in severityLevels"
                            :key="level.value"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                            :class="{
                                'border-primary bg-primary/10 text-primary': isSelected('severity', level.value),
                                'hover:border-primary/50': !isSelected('severity', level.value),
                            }"
                            @click="toggleArrayItem('severity', level.value)"
                        >
                            {{ locale === 'ar' ? level.label.ar : level.label.en }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.min_confidence') }}: {{ ((parameters.min_confidence || 0.8) * 100).toFixed(0) }}%</Label>
                    <Slider
                        :model-value="[(parameters.min_confidence || 0.8) * 100]"
                        :min="50"
                        :max="95"
                        :step="5"
                        @update:model-value="updateParameter('min_confidence', $event[0] / 100)"
                    />
                </div>
            </div>
        </template>

        <!-- Pattern Configuration -->
        <template v-else-if="triggerType === 'pattern'">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.patterns') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="pattern in chartPatterns"
                            :key="pattern.value"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                            :class="{
                                'border-primary bg-primary/10 text-primary': isSelected('patterns', pattern.value),
                                'hover:border-primary/50': !isSelected('patterns', pattern.value),
                            }"
                            @click="toggleArrayItem('patterns', pattern.value)"
                        >
                            {{ locale === 'ar' ? pattern.label.ar : pattern.label.en }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.pattern_status') }}</Label>
                    <Select :model-value="parameters.pattern_status || 'confirmed'" @update:model-value="updateParameter('pattern_status', $event)">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="forming">{{ t('alerts.pattern_status.forming') }}</SelectItem>
                            <SelectItem value="confirmed">{{ t('alerts.pattern_status.confirmed') }}</SelectItem>
                            <SelectItem value="completed">{{ t('alerts.pattern_status.completed') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.min_confidence') }}: {{ ((parameters.min_confidence || 0.7) * 100).toFixed(0) }}%</Label>
                    <Slider
                        :model-value="[(parameters.min_confidence || 0.7) * 100]"
                        :min="50"
                        :max="95"
                        :step="5"
                        @update:model-value="updateParameter('min_confidence', $event[0] / 100)"
                    />
                </div>
            </div>
        </template>

        <!-- Recommendation Configuration -->
        <template v-else-if="triggerType === 'recommendation'">
            <div class="grid gap-4">
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.recommendations') }}</Label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="rec in recommendations"
                            :key="rec.value"
                            type="button"
                            class="rounded-md border px-3 py-1.5 text-sm transition-colors"
                            :class="{
                                'border-primary bg-primary/10 text-primary': isSelected('recommendations', rec.value),
                                'hover:border-primary/50': !isSelected('recommendations', rec.value),
                            }"
                            @click="toggleArrayItem('recommendations', rec.value)"
                        >
                            {{ locale === 'ar' ? rec.label.ar : rec.label.en }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-2">
                    <Label>{{ t('alerts.fields.trigger_on') }}</Label>
                    <Select :model-value="parameters.trigger_on || 'change'" @update:model-value="updateParameter('trigger_on', $event)">
                        <SelectTrigger>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="change">{{ t('alerts.recommendation_trigger.change') }}</SelectItem>
                            <SelectItem value="upgrade">{{ t('alerts.recommendation_trigger.upgrade') }}</SelectItem>
                            <SelectItem value="downgrade">{{ t('alerts.recommendation_trigger.downgrade') }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
        </template>
    </div>
</template>
