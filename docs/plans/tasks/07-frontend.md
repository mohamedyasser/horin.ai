# Task 07: Frontend Implementation

**Priority:** P1
**Effort:** 5 days
**Dependencies:** Task 06

---

## Objective

Build all Vue 3 + Inertia.js pages and components for alert management, including the creation wizard, list view, history, and real-time notifications.

---

## Checklist

- [ ] Create Alerts Index page
- [ ] Create Alert Create wizard (4 steps)
- [ ] Create Alert Edit page
- [ ] Create Alert History page
- [ ] Create Alert Preferences page
- [ ] Create alert components
- [ ] Create notification components
- [ ] Create `useNotifications` composable
- [ ] Create `useAlerts` composable
- [ ] Set up WebSocket (Echo + Reverb)
- [ ] Add toast notifications
- [ ] Implement localization (AR/EN)
- [ ] Add TypeScript types

---

## File Structure

```
resources/js/
├── pages/
│   ├── Alerts/
│   │   ├── Index.vue           # Alert list & management
│   │   ├── Create.vue          # Create wizard (4 steps)
│   │   ├── Edit.vue            # Edit existing alert
│   │   └── History.vue         # Triggered alert history
│   └── Settings/
│       └── Alerts.vue          # Alert preferences
│
├── components/
│   ├── alerts/
│   │   ├── AlertCard.vue
│   │   ├── AlertTypeSelector.vue
│   │   ├── PriceAlertConfig.vue
│   │   ├── IntelligenceAlertConfig.vue
│   │   ├── DeliveryConfig.vue
│   │   ├── AlertSummary.vue
│   │   └── BacktestResult.vue
│   │
│   └── notifications/
│       ├── NotificationBell.vue
│       ├── NotificationDropdown.vue
│       └── NotificationToast.vue
│
├── composables/
│   ├── useAlerts.ts
│   └── useNotifications.ts
│
└── types/
    └── alerts.ts
```

---

## TypeScript Types

Create `resources/js/types/alerts.ts`:

```typescript
export type AlertType = 'price' | 'prediction' | 'signal' | 'anomaly' | 'pattern' | 'recommendation';

export type AlertTriggerType =
    | 'target_price'
    | 'breakout'
    | 'zone'
    | 'gap'
    | '52week'
    | 'daily_change'
    | 'entry_return'
    | 'prediction'
    | 'signal'
    | 'anomaly'
    | 'pattern'
    | 'recommendation'
    | 'compound_intelligence';

export type AlertStatus = 'active' | 'triggered' | 'paused' | 'expired' | 'chained' | 'deleted';
export type AlertPriority = 'critical' | 'high' | 'medium' | 'low';
export type AlertScope = 'single_asset' | 'watchlist' | 'portfolio' | 'sector' | 'market';
export type AlertDirection = 'above' | 'below' | 'both' | 'cross_up' | 'cross_down';
export type ConditionLogic = 'single' | 'and' | 'or';

export interface AlertParameters {
    target_price?: number;
    level?: number;
    direction?: AlertDirection;
    zone_low?: number;
    zone_high?: number;
    gap_threshold_percent?: number;
    threshold_percent?: number;
    type?: string;
    entry_price?: number;
    tolerance_percent?: number;
    indicators?: string[];
    signal_types?: string[];
    min_strength?: number;
    min_confidence?: number;
    patterns?: string[];
    anomaly_types?: string[];
    severity?: string[];
    recommendations?: string[];
    conditions?: AlertCondition[];
}

export interface AlertCondition {
    type: AlertType;
    [key: string]: unknown;
}

export interface DeliveryConfig {
    channels?: ('telegram' | 'push' | 'email' | 'in_app')[];
}

export interface EscalationConfig {
    enabled: boolean;
    levels: EscalationLevel[];
    max_escalations: number;
}

export interface EscalationLevel {
    level: number;
    channel: string;
    delay_minutes: number;
    condition?: string;
}

export interface Asset {
    id: string;
    symbol: string;
    name: string;
    name_ar: string;
    last_price: number;
}

export interface Alert {
    id: string;
    user_id: string;
    asset?: Asset;
    template?: { id: string; name: string };
    type: AlertType;
    trigger_type: AlertTriggerType;
    scope: AlertScope;
    direction?: AlertDirection;
    condition_logic: ConditionLogic;
    parameters: AlertParameters;
    status: AlertStatus;
    priority: AlertPriority;
    is_recurring: boolean;
    cooldown_minutes: number;
    max_triggers?: number;
    triggered_count: number;
    delivery_config?: DeliveryConfig;
    escalation_config?: EscalationConfig;
    snoozed_until?: string;
    is_snoozed: boolean;
    last_triggered_at?: string;
    expires_at?: string;
    market_hours_only: boolean;
    created_at: string;
    updated_at: string;
}

export interface AlertHistory {
    id: string;
    alert_id: string;
    alert?: {
        type: AlertType;
        trigger_type: AlertTriggerType;
        parameters: AlertParameters;
    };
    asset?: Asset;
    triggered_at: string;
    trigger_value: number;
    trigger_context: Record<string, unknown>;
    notification_sent: boolean;
    acknowledged_at?: string;
    escalation_level: number;
}

export interface AlertNotification {
    id: string;
    type: string;
    channel: string;
    priority: AlertPriority;
    title: string;
    body: string;
    data: {
        type: string;
        alert_id: string;
        asset_id: string;
        history_id: string;
        screen: string;
        params: Record<string, unknown>;
    };
    status: string;
    read_at?: string;
    created_at: string;
}

export interface AlertPreferences {
    user_id: string;
    default_channels: string[];
    quiet_hours_start?: string;
    quiet_hours_end?: string;
    timezone: string;
    max_alerts_per_hour: number;
    max_alerts_per_day: number;
    digest_enabled: boolean;
    digest_time: string;
    smart_defaults_enabled: boolean;
}

export interface BacktestResult {
    id: string;
    alert_id: string;
    lookback_days: number;
    trigger_count: number;
    triggers: BacktestTrigger[];
    avg_return_1d?: number;
    avg_return_1w?: number;
    avg_return_1m?: number;
    win_rate?: number;
    completed_at: string;
}

export interface BacktestTrigger {
    date: string;
    trigger_price: number;
    performance: {
        '1d': number;
        '1w': number;
        '1m': number;
    };
}
```

---

## useNotifications Composable

Create `resources/js/composables/useNotifications.ts`:

```typescript
import { ref, onMounted, onUnmounted, computed } from 'vue';
import type { AlertNotification } from '@/types/alerts';

export function useNotifications(userId: string) {
    const notifications = ref<AlertNotification[]>([]);
    const toastQueue = ref<AlertNotification[]>([]);
    const isConnected = ref(false);
    const reconnectAttempts = ref(0);
    const MAX_RECONNECT_DELAY = 30000; // 30 seconds

    let reconnectTimeout: ReturnType<typeof setTimeout> | null = null;

    const unreadCount = computed(() =>
        notifications.value.filter(n => !n.read_at).length
    );

    const setupAlertChannel = () => {
        if (!window.Echo) {
            console.error('Echo not initialized');
            return;
        }

        window.Echo
            .private(`user.${userId}.alerts`)
            .listen('.alert.triggered', (event: AlertNotification) => {
                handleAlert(event);
            })
            .error((error: Error) => {
                console.error('WebSocket error:', error);
                isConnected.value = false;
                scheduleReconnect();
            });

        // Mark as connected when subscription succeeds
        isConnected.value = true;
        reconnectAttempts.value = 0;
    };

    const handleAlert = (event: AlertNotification) => {
        // Add to notifications list
        notifications.value.unshift(event);

        // Show toast for high priority
        if (['critical', 'high'].includes(event.priority)) {
            toastQueue.value.push(event);
        }

        // Play sound for critical alerts
        if (event.priority === 'critical') {
            playSound();
        }
    };

    const scheduleReconnect = () => {
        if (reconnectTimeout) return;

        reconnectAttempts.value++;
        const delay = Math.min(
            Math.pow(2, reconnectAttempts.value) * 1000,
            MAX_RECONNECT_DELAY
        );

        console.log(`Reconnecting in ${delay}ms (attempt ${reconnectAttempts.value})`);

        reconnectTimeout = setTimeout(() => {
            reconnectTimeout = null;
            window.Echo?.connector.connect();
            setupAlertChannel();
        }, delay);
    };

    const fetchNotifications = async () => {
        try {
            const response = await fetch('/api/notifications?per_page=50');
            const data = await response.json();
            notifications.value = data.data;
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
        }
    };

    const fetchMissedNotifications = async () => {
        const lastSeen = notifications.value[0]?.created_at;
        if (!lastSeen) return;

        try {
            const response = await fetch(`/api/notifications?since=${lastSeen}`);
            const data = await response.json();

            if (data.data.length > 0) {
                notifications.value = [...data.data, ...notifications.value];
            }
        } catch (error) {
            console.error('Failed to fetch missed notifications:', error);
        }
    };

    const markAsRead = async (notificationId: string) => {
        try {
            await fetch(`/api/notifications/${notificationId}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            const notification = notifications.value.find(n => n.id === notificationId);
            if (notification) {
                notification.read_at = new Date().toISOString();
            }
        } catch (error) {
            console.error('Failed to mark as read:', error);
        }
    };

    const markAllAsRead = async () => {
        try {
            await fetch('/api/notifications/read-all', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });

            notifications.value.forEach(n => {
                if (!n.read_at) {
                    n.read_at = new Date().toISOString();
                }
            });
        } catch (error) {
            console.error('Failed to mark all as read:', error);
        }
    };

    const dismissToast = (notificationId: string) => {
        const index = toastQueue.value.findIndex(n => n.id === notificationId);
        if (index !== -1) {
            toastQueue.value.splice(index, 1);
        }
    };

    const playSound = () => {
        const audio = new Audio('/sounds/alert-critical.mp3');
        audio.play().catch(() => {
            // Audio play failed (likely due to autoplay policy)
        });
    };

    // Handle tab visibility change
    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            fetchMissedNotifications();

            if (!isConnected.value) {
                window.Echo?.connector.connect();
                setupAlertChannel();
            }
        }
    };

    onMounted(() => {
        document.addEventListener('visibilitychange', handleVisibilityChange);
        fetchNotifications();
        setupAlertChannel();
    });

    onUnmounted(() => {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        if (reconnectTimeout) {
            clearTimeout(reconnectTimeout);
        }
    });

    return {
        notifications,
        toastQueue,
        unreadCount,
        isConnected,
        markAsRead,
        markAllAsRead,
        dismissToast,
        fetchNotifications,
    };
}
```

---

## useAlerts Composable

Create `resources/js/composables/useAlerts.ts`:

```typescript
import { ref, computed } from 'vue';
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
```

---

## Alerts Index Page

Create `resources/js/pages/Alerts/Index.vue`:

```vue
<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { Alert } from '@/types/alerts';
import { useAlerts } from '@/composables/useAlerts';
import AlertCard from '@/components/alerts/AlertCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';

interface Props {
    alerts: {
        data: Alert[];
        meta: {
            current_page: number;
            last_page: number;
        };
    };
    filters: {
        type?: string;
        status?: string;
        asset_id?: string;
    };
    stats: {
        active: number;
        triggered_today: number;
        total: number;
    };
}

const props = defineProps<Props>();

const { statusLabels, snoozeAlert, deleteAlert } = useAlerts();

const selectedFilter = ref(props.filters.status || 'all');

const filteredAlerts = computed(() => {
    if (selectedFilter.value === 'all') {
        return props.alerts.data;
    }
    return props.alerts.data.filter(a => a.status === selectedFilter.value);
});

const applyFilter = (status: string) => {
    selectedFilter.value = status;
    router.get('/alerts', { status: status === 'all' ? undefined : status }, {
        preserveState: true,
        replace: true,
    });
};

const handleSnooze = (alertId: string, preset: string) => {
    snoozeAlert(alertId, preset);
};

const handleDelete = (alertId: string) => {
    if (confirm('Are you sure you want to delete this alert?')) {
        deleteAlert(alertId);
    }
};
</script>

<template>
    <Head title="Alerts" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $t('alerts.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $t('alerts.subtitle') }}
                    </p>
                </div>
                <Link
                    href="/alerts/create"
                    class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition"
                >
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('alerts.create') }}
                </Link>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('alerts.stats.active') }}</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.active }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('alerts.stats.triggered_today') }}</div>
                    <div class="text-2xl font-bold text-green-600">{{ stats.triggered_today }}</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('alerts.stats.total') }}</div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex space-x-2 mb-6 overflow-x-auto">
                <button
                    v-for="status in ['all', 'active', 'triggered', 'paused', 'expired']"
                    :key="status"
                    @click="applyFilter(status)"
                    :class="[
                        'px-4 py-2 rounded-full text-sm font-medium transition whitespace-nowrap',
                        selectedFilter === status
                            ? 'bg-primary-600 text-white'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                    ]"
                >
                    {{ status === 'all' ? $t('alerts.filters.all') : $t(`alerts.status.${status}`) }}
                </button>
            </div>

            <!-- Alert List -->
            <div class="space-y-4">
                <AlertCard
                    v-for="alert in filteredAlerts"
                    :key="alert.id"
                    :alert="alert"
                    @snooze="handleSnooze(alert.id, $event)"
                    @delete="handleDelete(alert.id)"
                />

                <div v-if="filteredAlerts.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('alerts.empty.title') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('alerts.empty.description') }}</p>
                    <div class="mt-6">
                        <Link
                            href="/alerts/create"
                            class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg"
                        >
                            {{ $t('alerts.create_first') }}
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="alerts.meta.last_page > 1" class="mt-8 flex justify-center">
                <!-- Add pagination component -->
            </div>
        </div>
    </AppLayout>
</template>
```

---

## AlertCard Component

Create `resources/js/components/alerts/AlertCard.vue`:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import type { Alert } from '@/types/alerts';
import { useAlerts } from '@/composables/useAlerts';

interface Props {
    alert: Alert;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    snooze: [preset: string];
    delete: [];
}>();

const { alertTypeLabels, triggerTypeLabels, priorityLabels, statusLabels } = useAlerts();

const showSnoozeMenu = ref(false);

const typeLabel = computed(() => {
    const labels = alertTypeLabels[props.alert.type];
    return labels?.en || props.alert.type;
});

const triggerLabel = computed(() => {
    const labels = triggerTypeLabels[props.alert.trigger_type];
    return labels?.en || props.alert.trigger_type;
});

const priorityConfig = computed(() => priorityLabels[props.alert.priority] || priorityLabels.medium);
const statusConfig = computed(() => statusLabels[props.alert.status] || statusLabels.active);

const snoozePresets = [
    { value: '1h', label: '1 hour' },
    { value: '4h', label: '4 hours' },
    { value: '1d', label: '1 day' },
    { value: 'until_market_close', label: 'Until market close' },
    { value: 'until_market_open', label: 'Until market open' },
];

const handleSnooze = (preset: string) => {
    emit('snooze', preset);
    showSnoozeMenu.value = false;
};
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition">
        <div class="flex items-start justify-between">
            <!-- Left: Alert Info -->
            <div class="flex items-start space-x-4">
                <!-- Icon -->
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                    <span v-if="alert.type === 'price'" class="text-lg">💰</span>
                    <span v-else-if="alert.type === 'signal'" class="text-lg">📊</span>
                    <span v-else-if="alert.type === 'anomaly'" class="text-lg">⚠️</span>
                    <span v-else-if="alert.type === 'pattern'" class="text-lg">📐</span>
                    <span v-else-if="alert.type === 'prediction'" class="text-lg">🤖</span>
                    <span v-else-if="alert.type === 'recommendation'" class="text-lg">💡</span>
                    <span v-else class="text-lg">🔔</span>
                </div>

                <!-- Details -->
                <div>
                    <div class="flex items-center space-x-2">
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            {{ alert.asset?.symbol || 'Multiple Assets' }}
                        </h3>
                        <span
                            :class="[
                                'px-2 py-0.5 text-xs rounded-full',
                                statusConfig.color === 'green' && 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                statusConfig.color === 'blue' && 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                statusConfig.color === 'gray' && 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                statusConfig.color === 'red' && 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                            ]"
                        >
                            {{ statusConfig.en }}
                        </span>
                        <span
                            v-if="alert.is_snoozed"
                            class="px-2 py-0.5 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 rounded-full"
                        >
                            Snoozed
                        </span>
                    </div>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ typeLabel }} • {{ triggerLabel }}
                    </p>

                    <!-- Parameters summary -->
                    <div class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        <template v-if="alert.trigger_type === 'target_price'">
                            Target: {{ alert.parameters.target_price }} EGP
                            <span class="text-gray-400">({{ alert.parameters.direction }})</span>
                        </template>
                        <template v-else-if="alert.trigger_type === 'daily_change'">
                            {{ alert.parameters.threshold_percent }}% change
                        </template>
                        <template v-else-if="alert.trigger_type === 'zone'">
                            Zone: {{ alert.parameters.zone_low }} - {{ alert.parameters.zone_high }} EGP
                        </template>
                        <template v-else>
                            {{ alert.scope !== 'single_asset' ? `Scope: ${alert.scope}` : '' }}
                        </template>
                    </div>

                    <!-- Triggered info -->
                    <div v-if="alert.triggered_count > 0" class="mt-1 text-xs text-gray-400">
                        Triggered {{ alert.triggered_count }} time(s)
                        <span v-if="alert.last_triggered_at">
                            • Last: {{ new Date(alert.last_triggered_at).toLocaleString() }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Actions -->
            <div class="flex items-center space-x-2">
                <!-- Priority badge -->
                <span
                    :class="[
                        'px-2 py-1 text-xs font-medium rounded',
                        priorityConfig.color === 'red' && 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        priorityConfig.color === 'orange' && 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                        priorityConfig.color === 'yellow' && 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                        priorityConfig.color === 'gray' && 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                    ]"
                >
                    {{ priorityConfig.en }}
                </span>

                <!-- Snooze button -->
                <div class="relative">
                    <button
                        @click="showSnoozeMenu = !showSnoozeMenu"
                        class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                        title="Snooze"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>

                    <!-- Snooze dropdown -->
                    <div
                        v-if="showSnoozeMenu"
                        class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-10"
                    >
                        <button
                            v-for="preset in snoozePresets"
                            :key="preset.value"
                            @click="handleSnooze(preset.value)"
                            class="block w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                </div>

                <!-- Edit -->
                <Link
                    :href="`/alerts/${alert.id}/edit`"
                    class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="Edit"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </Link>

                <!-- Delete -->
                <button
                    @click="$emit('delete')"
                    class="p-2 text-gray-400 hover:text-red-600 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
                    title="Delete"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
```

---

## NotificationBell Component

Create `resources/js/components/notifications/NotificationBell.vue`:

```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useNotifications } from '@/composables/useNotifications';
import NotificationDropdown from './NotificationDropdown.vue';

const page = usePage();
const userId = page.props.auth?.user?.id;

const { notifications, unreadCount, markAsRead, markAllAsRead } = useNotifications(userId);

const showDropdown = ref(false);

const toggleDropdown = () => {
    showDropdown.value = !showDropdown.value;
};

const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.notification-bell')) {
        showDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative notification-bell">
        <button
            @click="toggleDropdown"
            class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition"
        >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>

            <!-- Unread badge -->
            <span
                v-if="unreadCount > 0"
                class="absolute top-0 right-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full transform translate-x-1 -translate-y-1"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <NotificationDropdown
            v-if="showDropdown"
            :notifications="notifications"
            @mark-read="markAsRead"
            @mark-all-read="markAllAsRead"
            @close="showDropdown = false"
        />
    </div>
</template>
```

---

## NotificationDropdown Component

Create `resources/js/components/notifications/NotificationDropdown.vue`:

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { AlertNotification } from '@/types/alerts';

interface Props {
    notifications: AlertNotification[];
}

const props = defineProps<Props>();

const emit = defineEmits<{
    markRead: [id: string];
    markAllRead: [];
    close: [];
}>();

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now.getTime() - date.getTime();

    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    return `${days}d ago`;
};

const getPriorityColor = (priority: string) => {
    switch (priority) {
        case 'critical': return 'border-l-red-500';
        case 'high': return 'border-l-orange-500';
        case 'medium': return 'border-l-yellow-500';
        default: return 'border-l-gray-300';
    }
};
</script>

<template>
    <div class="absolute right-0 mt-2 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-50">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white">Notifications</h3>
            <button
                v-if="notifications.some(n => !n.read_at)"
                @click="$emit('markAllRead')"
                class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400"
            >
                Mark all as read
            </button>
        </div>

        <!-- Notifications list -->
        <div class="max-h-96 overflow-y-auto">
            <template v-if="notifications.length > 0">
                <div
                    v-for="notification in notifications.slice(0, 10)"
                    :key="notification.id"
                    :class="[
                        'px-4 py-3 border-l-4 cursor-pointer transition',
                        getPriorityColor(notification.priority),
                        notification.read_at
                            ? 'bg-white dark:bg-gray-800'
                            : 'bg-blue-50 dark:bg-gray-700',
                        'hover:bg-gray-50 dark:hover:bg-gray-700'
                    ]"
                    @click="$emit('markRead', notification.id)"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ notification.title }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                {{ notification.body }}
                            </p>
                        </div>
                        <span class="text-xs text-gray-400 ml-2 whitespace-nowrap">
                            {{ formatTime(notification.created_at) }}
                        </span>
                    </div>
                </div>
            </template>

            <div v-else class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                <svg class="mx-auto h-8 w-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <p class="text-sm">No notifications yet</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
            <Link
                href="/alerts/history"
                class="block text-center text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400"
                @click="$emit('close')"
            >
                View all notifications
            </Link>
        </div>
    </div>
</template>
```

---

## NotificationToast Component

Create `resources/js/components/notifications/NotificationToast.vue`:

```vue
<script setup lang="ts">
import { ref, onMounted } from 'vue';
import type { AlertNotification } from '@/types/alerts';

interface Props {
    notification: AlertNotification;
    duration?: number;
}

const props = withDefaults(defineProps<Props>(), {
    duration: 5000,
});

const emit = defineEmits<{
    dismiss: [];
}>();

const isVisible = ref(true);

const dismiss = () => {
    isVisible.value = false;
    setTimeout(() => emit('dismiss'), 300);
};

onMounted(() => {
    setTimeout(dismiss, props.duration);
});

const getPriorityIcon = () => {
    switch (props.notification.priority) {
        case 'critical': return '🚨';
        case 'high': return '⚠️';
        case 'medium': return '🔔';
        default: return 'ℹ️';
    }
};

const getPriorityColor = () => {
    switch (props.notification.priority) {
        case 'critical': return 'border-red-500 bg-red-50 dark:bg-red-900/20';
        case 'high': return 'border-orange-500 bg-orange-50 dark:bg-orange-900/20';
        case 'medium': return 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20';
        default: return 'border-gray-300 bg-gray-50 dark:bg-gray-800';
    }
};
</script>

<template>
    <Transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="isVisible"
            :class="[
                'max-w-sm w-full shadow-lg rounded-lg pointer-events-auto border-l-4',
                getPriorityColor()
            ]"
        >
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0 text-xl">
                        {{ getPriorityIcon() }}
                    </div>
                    <div class="ml-3 w-0 flex-1">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ notification.title }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ notification.body }}
                        </p>
                    </div>
                    <div class="ml-4 flex-shrink-0 flex">
                        <button
                            @click="dismiss"
                            class="rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none"
                        >
                            <span class="sr-only">Close</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>
```

---

## Echo Configuration

Update `resources/js/bootstrap.ts`:

```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo;
    }
}

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

---

## Installation

```bash
# Install dependencies
npm install laravel-echo pusher-js

# Add to .env
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

---

## Next Task

Proceed to [Task 08: Advanced Features](./08-advanced-features.md)
