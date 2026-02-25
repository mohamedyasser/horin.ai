<script setup lang="ts">
import { useAlerts } from '@/composables/useAlerts';
import type { AlertType } from '@/types/alerts';
import {
    AlertTriangle,
    BarChart3,
    Brain,
    Lightbulb,
    Shapes,
    TrendingUp,
} from 'lucide-vue-next';
import {} from 'vue';
import { useI18n } from 'vue-i18n';

const { locale } = useI18n();

interface Props {
    modelValue: AlertType;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: AlertType];
}>();

const { alertTypeLabels } = useAlerts();

const alertTypes: AlertType[] = [
    'price',
    'prediction',
    'signal',
    'anomaly',
    'pattern',
    'recommendation',
];

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

const getTypeDescription = (type: AlertType): string => {
    const descriptions: Record<AlertType, { en: string; ar: string }> = {
        price: {
            en: 'Price targets, breakouts, zones',
            ar: 'أهداف السعر، الاختراقات، المناطق',
        },
        prediction: {
            en: 'AI-powered price predictions',
            ar: 'تنبؤات الذكاء الاصطناعي',
        },
        signal: {
            en: 'Technical indicator signals',
            ar: 'إشارات المؤشرات الفنية',
        },
        anomaly: {
            en: 'Unusual market behavior',
            ar: 'سلوك السوق غير الطبيعي',
        },
        pattern: {
            en: 'Chart pattern detection',
            ar: 'اكتشاف أنماط الرسم البياني',
        },
        recommendation: {
            en: 'Buy/sell recommendation changes',
            ar: 'تغيرات توصيات الشراء/البيع',
        },
    };
    return locale.value === 'ar'
        ? descriptions[type].ar
        : descriptions[type].en;
};

const handleSelect = (type: AlertType) => {
    if (!props.disabled) {
        emit('update:modelValue', type);
    }
};
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <button
            v-for="type in alertTypes"
            :key="type"
            type="button"
            :disabled="disabled"
            class="flex flex-col items-center gap-2 rounded-lg border-2 p-4 transition-colors"
            :class="{
                'border-foreground bg-muted': modelValue === type,
                'border-border hover:border-foreground/50':
                    modelValue !== type && !disabled,
                'cursor-not-allowed opacity-50': disabled,
            }"
            @click="handleSelect(type)"
        >
            <component :is="getTypeIcon(type)" class="size-6" />
            <span class="text-sm font-medium">
                {{
                    locale === 'ar'
                        ? alertTypeLabels[type].ar
                        : alertTypeLabels[type].en
                }}
            </span>
            <span class="text-center text-xs text-muted-foreground">
                {{ getTypeDescription(type) }}
            </span>
        </button>
    </div>
</template>
