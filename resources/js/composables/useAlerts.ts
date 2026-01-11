import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { Alert, AlertType, AlertTriggerType, AlertParameters } from '@/types/alerts';

export function useAlerts() {
    const isSubmitting = ref(false);
    const errors = ref<Record<string, string>>({});

    const alertTypeLabels: Record<AlertType, { en: string; ar: string }> = {
        price: { en: 'Price', ar: 'السعر' },
        prediction: { en: 'AI Prediction', ar: 'تنبؤ الذكاء الاصطناعي' },
        signal: { en: 'Technical Signal', ar: 'إشارة تقنية' },
        anomaly: { en: 'Anomaly', ar: 'شذوذ' },
        pattern: { en: 'Chart Pattern', ar: 'نمط الرسم البياني' },
        recommendation: { en: 'Recommendation', ar: 'توصية' },
    };

    const triggerTypeLabels: Record<AlertTriggerType, { en: string; ar: string }> = {
        target_price: { en: 'Target Price', ar: 'السعر المستهدف' },
        breakout: { en: 'Breakout', ar: 'اختراق' },
        zone: { en: 'Support/Resistance Zone', ar: 'منطقة الدعم/المقاومة' },
        gap: { en: 'Price Gap', ar: 'فجوة السعر' },
        '52week': { en: '52-Week High/Low', ar: 'أعلى/أدنى 52 أسبوع' },
        daily_change: { en: 'Daily % Change', ar: 'التغير اليومي %' },
        entry_return: { en: 'Return to Entry', ar: 'العودة لسعر الدخول' },
        prediction: { en: 'AI Prediction', ar: 'تنبؤ الذكاء الاصطناعي' },
        signal: { en: 'Technical Signal', ar: 'إشارة تقنية' },
        anomaly: { en: 'Anomaly Detection', ar: 'اكتشاف الشذوذ' },
        pattern: { en: 'Pattern Detection', ar: 'اكتشاف النمط' },
        recommendation: { en: 'Recommendation Change', ar: 'تغير التوصية' },
        compound_intelligence: { en: 'Compound Alert', ar: 'تنبيه مركب' },
    };

    const priorityLabels = {
        critical: { en: 'Critical', ar: 'حرج', color: 'red' },
        high: { en: 'High', ar: 'عالي', color: 'orange' },
        medium: { en: 'Medium', ar: 'متوسط', color: 'yellow' },
        low: { en: 'Low', ar: 'منخفض', color: 'gray' },
    };

    const statusLabels = {
        active: { en: 'Active', ar: 'نشط', color: 'green' },
        triggered: { en: 'Triggered', ar: 'تم التفعيل', color: 'blue' },
        paused: { en: 'Paused', ar: 'متوقف', color: 'gray' },
        expired: { en: 'Expired', ar: 'منتهي', color: 'red' },
        chained: { en: 'Chained', ar: 'مرتبط', color: 'purple' },
    };

    const createAlert = async (data: Partial<Alert>) => {
        isSubmitting.value = true;
        errors.value = {};

        router.post('/alerts', data, {
            onSuccess: () => {
                isSubmitting.value = false;
            },
            onError: (err) => {
                errors.value = err;
                isSubmitting.value = false;
            },
        });
    };

    const updateAlert = async (alertId: string, data: Partial<Alert>) => {
        isSubmitting.value = true;
        errors.value = {};

        router.patch(`/alerts/${alertId}`, data, {
            onSuccess: () => {
                isSubmitting.value = false;
            },
            onError: (err) => {
                errors.value = err;
                isSubmitting.value = false;
            },
        });
    };

    const deleteAlert = async (alertId: string) => {
        router.delete(`/alerts/${alertId}`);
    };

    const snoozeAlert = async (alertId: string, preset: string) => {
        router.post(`/alerts/${alertId}/snooze`, { preset });
    };

    const unsnoozeAlert = async (alertId: string) => {
        router.delete(`/alerts/${alertId}/snooze`);
    };

    const duplicateAlert = async (alertId: string) => {
        router.post(`/alerts/${alertId}/duplicate`);
    };

    const getDefaultParameters = (triggerType: AlertTriggerType): AlertParameters => {
        const defaults: Record<AlertTriggerType, AlertParameters> = {
            target_price: { target_price: 0, direction: 'above' },
            breakout: { level: 0, direction: 'above', confirmation: 'sustained', consecutive_ticks: 2 },
            zone: { zone_low: 0, zone_high: 0, trigger_on: 'enter', cooldown_hours: 4 },
            gap: { gap_threshold_percent: 3, direction: 'both' },
            '52week': { type: 'high', cooldown_hours: 24 },
            daily_change: { threshold_percent: 5, direction: 'both', from_reference: 'open' },
            entry_return: { entry_price: 0, tolerance_percent: 0.5 },
            prediction: { prediction_type: 'price_direction', horizon: '1hour', direction: 'up', min_confidence: 0.75 },
            signal: { indicators: [], signal_types: [], min_strength: 0.7, any_or_all: 'any' },
            anomaly: { anomaly_types: ['price_spike', 'volume_surge'], min_confidence: 0.8, severity: ['high', 'critical'] },
            pattern: { patterns: [], pattern_status: 'confirmed', min_confidence: 0.7 },
            recommendation: { trigger_on: 'change', recommendations: ['strong_buy', 'buy'], min_score: 0.75 },
            compound_intelligence: { conditions: [] },
        };

        return defaults[triggerType] || {};
    };

    return {
        isSubmitting,
        errors,
        alertTypeLabels,
        triggerTypeLabels,
        priorityLabels,
        statusLabels,
        createAlert,
        updateAlert,
        deleteAlert,
        snoozeAlert,
        unsnoozeAlert,
        duplicateAlert,
        getDefaultParameters,
    };
}
