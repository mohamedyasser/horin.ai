# Alert System Completion Plan

**Date:** 2026-01-12
**Status:** Ready for Implementation
**Estimated Effort:** 40-50 hours
**Priority:** High

---

## Executive Summary

This plan addresses all gaps identified in the comprehensive audit of the Kira Alert System. The system is currently at **78% completion**. This plan will bring it to **100% production-ready** status.

### Current State
- **Backend**: 90% complete - minor fixes needed
- **Frontend**: 70% complete - missing advanced features
- **Telegram Bot**: 75% complete - incomplete alert types
- **Redis Integration**: 100% complete - all 23 channels covered

### Goals
1. Fix all critical bugs blocking functionality
2. Complete all alert type implementations
3. Deliver full-featured frontend UI
4. Complete Telegram bot capabilities
5. Ensure production reliability

---

## Phase 1: Critical Backend Fixes

**Priority:** CRITICAL
**Effort:** 4-6 hours
**Dependencies:** None

### 1.1 Fix ProcessIntelligenceAlerts Signature Mismatch

**File:** `app/Jobs/Alerts/ProcessIntelligenceAlerts.php`

**Problem:** The `evaluatePriceAlert` call passes wrong parameters when processing `price_updates` channel.

**Current Code (Line 85-95):**
```php
if ($alert->type === 'price' && $this->channel === 'price_updates') {
    $currentPrice = $this->signalData['last'] ?? $this->signalData['price'] ?? null;
    $previousPrice = $this->signalData['prevClose'] ?? $this->signalData['prev_close'] ?? null;

    if (! $currentPrice) {
        return false;
    }

    $result = $matcher->evaluatePriceAlert($alert, $currentPrice, $previousPrice, $this->signalData);
}
```

**Fix:** Create a DTO or use the correct method signature:

```php
if ($alert->type === 'price' && $this->channel === 'price_updates') {
    $currentPrice = (float) ($this->signalData['last'] ?? $this->signalData['price'] ?? 0);
    $previousPrice = (float) ($this->signalData['prevClose'] ?? $this->signalData['prev_close'] ?? $currentPrice);

    if ($currentPrice <= 0) {
        return false;
    }

    // Create price context object for evaluation
    $priceContext = (object) [
        'price' => $currentPrice,
        'prev_close' => $previousPrice,
        'open' => $this->signalData['open'] ?? $currentPrice,
        'high' => $this->signalData['high'] ?? $currentPrice,
        'low' => $this->signalData['low'] ?? $currentPrice,
        'high_52w' => $this->signalData['high_52w'] ?? null,
        'low_52w' => $this->signalData['low_52w'] ?? null,
        'change_percent' => $this->signalData['pcp'] ?? (($currentPrice - $previousPrice) / $previousPrice * 100),
    ];

    $result = $matcher->evaluatePriceAlertFromSignal($alert, $priceContext);
}
```

**New Method in AlertMatcher:**
```php
public function evaluatePriceAlertFromSignal(Alert $alert, object $priceContext): object
{
    return match ($alert->trigger_type) {
        'target_price' => $this->evaluateTargetPrice($alert, $priceContext->price, $priceContext->prev_close),
        'breakout' => $this->evaluateBreakout($alert, $priceContext->price, $priceContext->prev_close),
        'zone' => $this->evaluateZone($alert, $priceContext->price, $priceContext->prev_close),
        'gap' => $this->evaluateGap($alert, $priceContext->price, $priceContext->open, $priceContext->prev_close),
        '52week' => $this->evaluate52Week($alert, $priceContext->price, $priceContext->high_52w, $priceContext->low_52w),
        'daily_change' => $this->evaluateDailyChange($alert, $priceContext->change_percent),
        'entry_return' => $this->evaluateEntryReturn($alert, $priceContext->price),
        default => $this->noTrigger(),
    };
}
```

**Test Cases:**
- [ ] Price update with `last` field triggers target_price alert
- [ ] Price update with `price` field (fallback) triggers alert
- [ ] Missing price data returns false without error
- [ ] All 7 price trigger types evaluate correctly

---

### 1.2 Fix Zone Trigger State Tracking

**File:** `app/Services/AlertMatcher.php`

**Problem:** Zone alerts trigger on every tick inside the zone instead of once on entry/exit.

**Current Behavior:**
- `enter` triggers every time price is in zone
- `exit` triggers every time price is outside zone

**Required Behavior:**
- `enter` triggers only when price CROSSES INTO zone
- `exit` triggers only when price CROSSES OUT OF zone

**Solution:** Track previous zone state in alert history or use price direction.

**Implementation:**

```php
private function evaluateZone(Alert $alert, float $currentPrice, ?float $previousPrice): object
{
    $params = $alert->parameters;
    $zoneLow = $params['zone_low'];
    $zoneHigh = $params['zone_high'];
    $triggerOn = $params['trigger_on'] ?? 'enter';

    $currentlyInZone = $currentPrice >= $zoneLow && $currentPrice <= $zoneHigh;

    // If no previous price, we can't detect crossing
    if ($previousPrice === null) {
        return $this->noTrigger();
    }

    $wasInZone = $previousPrice >= $zoneLow && $previousPrice <= $zoneHigh;

    // Detect zone transitions
    $enteredZone = $currentlyInZone && !$wasInZone;
    $exitedZone = !$currentlyInZone && $wasInZone;

    $triggered = match ($triggerOn) {
        'enter' => $enteredZone,
        'exit' => $exitedZone,
        'both' => $enteredZone || $exitedZone,
        default => false,
    };

    if (!$triggered) {
        return $this->noTrigger();
    }

    return (object) [
        'triggered' => true,
        'triggerValue' => $currentPrice,
        'context' => [
            'zone_low' => $zoneLow,
            'zone_high' => $zoneHigh,
            'trigger_on' => $triggerOn,
            'event' => $enteredZone ? 'entered_zone' : 'exited_zone',
            'previous_price' => $previousPrice,
            'was_in_zone' => $wasInZone,
        ],
    ];
}
```

**Test Cases:**
- [ ] Price moving from below zone to inside triggers `enter`
- [ ] Price moving from inside zone to below triggers `exit`
- [ ] Price moving within zone does NOT trigger `enter`
- [ ] Price moving outside zone does NOT trigger `exit` repeatedly
- [ ] `both` triggers on either entry or exit

---

### 1.3 Implement Compound Intelligence Alerts

**File:** `app/Services/AlertMatcher.php`

**Problem:** `evaluateCompound` returns `noTrigger()` with TODO comment.

**Required:** Support AND/OR logic combining multiple conditions.

**Implementation:**

```php
private function evaluateCompound(Alert $alert, array $signalData): object
{
    $params = $alert->parameters;
    $conditions = $params['conditions'] ?? [];
    $logic = $alert->condition_logic ?? 'and'; // 'and' or 'or'

    if (empty($conditions)) {
        return $this->noTrigger();
    }

    $results = [];
    $triggeredConditions = [];
    $allContext = [];

    foreach ($conditions as $index => $condition) {
        $conditionType = $condition['type'] ?? 'signal';

        // Create a temporary alert-like object for evaluation
        $conditionAlert = new \stdClass();
        $conditionAlert->trigger_type = $conditionType;
        $conditionAlert->parameters = $condition;

        $result = match ($conditionType) {
            'signal' => $this->evaluateSignal($alert, $signalData),
            'anomaly' => $this->evaluateAnomaly($alert, $signalData),
            'pattern' => $this->evaluatePattern($alert, $signalData),
            'prediction' => $this->evaluatePrediction($alert, $signalData),
            'recommendation' => $this->evaluateRecommendation($alert, $signalData),
            default => $this->noTrigger(),
        };

        $results[] = $result->triggered;
        $allContext["condition_{$index}"] = [
            'type' => $conditionType,
            'triggered' => $result->triggered,
            'context' => $result->context ?? [],
        ];

        if ($result->triggered) {
            $triggeredConditions[] = $index;
        }
    }

    // Evaluate based on logic
    $triggered = match ($logic) {
        'and' => !in_array(false, $results, true), // All must be true
        'or' => in_array(true, $results, true),     // At least one true
        default => false,
    };

    if (!$triggered) {
        return $this->noTrigger();
    }

    return (object) [
        'triggered' => true,
        'triggerValue' => count($triggeredConditions),
        'context' => [
            'logic' => $logic,
            'total_conditions' => count($conditions),
            'triggered_conditions' => $triggeredConditions,
            'condition_results' => $allContext,
        ],
    ];
}
```

**Test Cases:**
- [ ] AND logic: All 3 conditions true → triggers
- [ ] AND logic: 2 of 3 conditions true → no trigger
- [ ] OR logic: 1 of 3 conditions true → triggers
- [ ] OR logic: 0 of 3 conditions true → no trigger
- [ ] Empty conditions → no trigger
- [ ] Mixed condition types (signal + pattern + prediction)

---

## Phase 2: Frontend Completion

**Priority:** HIGH
**Effort:** 16-20 hours
**Dependencies:** Phase 1 backend fixes

### 2.1 Create NotificationToast Component

**File:** `resources/js/components/notifications/NotificationToast.vue`

**Purpose:** Display immediate toast notifications for critical/high priority alerts.

**Features:**
- Auto-dismiss after 8 seconds (configurable)
- Click to dismiss
- Action buttons (View Alert, Snooze)
- Priority-based styling (red for critical, orange for high)
- Sound indicator
- Stack multiple toasts
- Animation on enter/exit

**Implementation:**

```vue
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import type { AlertNotification } from '@/types/alerts';

interface Props {
    notification: AlertNotification;
    autoDismiss?: boolean;
    duration?: number;
}

const props = withDefaults(defineProps<Props>(), {
    autoDismiss: true,
    duration: 8000,
});

const emit = defineEmits<{
    dismiss: [id: string];
    action: [id: string, action: string];
}>();

const isVisible = ref(false);
const timeoutId = ref<ReturnType<typeof setTimeout> | null>(null);

const priorityStyles = computed(() => {
    const styles: Record<string, string> = {
        critical: 'border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20',
        high: 'border-l-4 border-orange-500 bg-orange-50 dark:bg-orange-900/20',
        medium: 'border-l-4 border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20',
        low: 'border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20',
    };
    return styles[props.notification.priority] || styles.medium;
});

const priorityIcon = computed(() => {
    const icons: Record<string, string> = {
        critical: '🚨',
        high: '⚠️',
        medium: '🔔',
        low: 'ℹ️',
    };
    return icons[props.notification.priority] || '🔔';
});

const dismiss = () => {
    isVisible.value = false;
    setTimeout(() => {
        emit('dismiss', props.notification.id);
    }, 300); // Wait for animation
};

const handleAction = (action: string) => {
    emit('action', props.notification.id, action);
    dismiss();
};

onMounted(() => {
    // Animate in
    requestAnimationFrame(() => {
        isVisible.value = true;
    });

    // Auto-dismiss
    if (props.autoDismiss) {
        timeoutId.value = setTimeout(dismiss, props.duration);
    }
});

onUnmounted(() => {
    if (timeoutId.value) {
        clearTimeout(timeoutId.value);
    }
});
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
                'pointer-events-auto w-full max-w-sm overflow-hidden rounded-lg shadow-lg ring-1 ring-black ring-opacity-5',
                priorityStyles,
            ]"
        >
            <div class="p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0 text-2xl">
                        {{ priorityIcon }}
                    </div>
                    <div class="ml-3 w-0 flex-1 pt-0.5">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ notification.title }}
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ notification.body }}
                        </p>
                        <div class="mt-3 flex space-x-3">
                            <button
                                type="button"
                                class="rounded-md bg-white dark:bg-gray-700 px-2.5 py-1.5 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="handleAction('view')"
                            >
                                View Alert
                            </button>
                            <button
                                type="button"
                                class="rounded-md bg-white dark:bg-gray-700 px-2.5 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="handleAction('snooze')"
                            >
                                Snooze
                            </button>
                        </div>
                    </div>
                    <div class="ml-4 flex flex-shrink-0">
                        <button
                            type="button"
                            class="inline-flex rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            @click="dismiss"
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

**Container Component:** `NotificationToastContainer.vue`

```vue
<script setup lang="ts">
import { computed } from 'vue';
import NotificationToast from './NotificationToast.vue';
import { useNotifications } from '@/composables/useNotifications';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const userId = computed(() => page.props.auth?.user?.id);
const { toastQueue, dismissToast } = useNotifications(userId.value);

const handleAction = (id: string, action: string) => {
    if (action === 'view') {
        // Navigate to alert
        const notification = toastQueue.value.find(n => n.id === id);
        if (notification?.alert_id) {
            window.location.href = `/alerts/${notification.alert_id}`;
        }
    } else if (action === 'snooze') {
        // Snooze for 1 hour
        const notification = toastQueue.value.find(n => n.id === id);
        if (notification?.alert_id) {
            fetch(`/alerts/${notification.alert_id}/snooze`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({ preset: '1h' }),
            });
        }
    }
    dismissToast(id);
};
</script>

<template>
    <div
        aria-live="assertive"
        class="pointer-events-none fixed inset-0 z-50 flex items-end px-4 py-6 sm:items-start sm:p-6"
    >
        <div class="flex w-full flex-col items-center space-y-4 sm:items-end">
            <NotificationToast
                v-for="notification in toastQueue"
                :key="notification.id"
                :notification="notification"
                @dismiss="dismissToast"
                @action="handleAction"
            />
        </div>
    </div>
</template>
```

**Integration in Layout:**
Add to `resources/js/layouts/AppLayout.vue`:

```vue
<template>
    <!-- ... existing layout ... -->
    <NotificationToastContainer />
</template>

<script setup>
import NotificationToastContainer from '@/components/notifications/NotificationToastContainer.vue';
</script>
```

---

### 2.2 Integrate DeliveryConfig into Alert Create/Edit

**Files to modify:**
- `resources/js/pages/Alerts/Create.vue`
- `resources/js/pages/Alerts/Edit.vue`

**Step 3 Enhancement in Create.vue:**

Add delivery configuration as part of step 3 (after parameters):

```vue
<!-- In Create.vue template, after parameter configuration -->
<div v-if="currentStep === 3" class="space-y-6">
    <!-- Existing parameter config... -->

    <!-- Add Delivery Configuration Section -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
            {{ $t('alerts.delivery_settings') }}
        </h3>

        <DeliveryConfig
            v-model:priority="form.priority"
            v-model:delivery-config="form.delivery_config"
            v-model:escalation-config="form.escalation_config"
        />
    </div>
</div>
```

**Form Data Extension:**

```typescript
const form = useForm({
    // ... existing fields ...

    // Add delivery configuration
    delivery_config: {
        channels: {
            telegram: { enabled: true, priority: 1 },
            push: { enabled: true, priority: 2 },
            email: { enabled: false, priority: 3 },
            in_app: { enabled: true, priority: 4 },
        },
    },
    escalation_config: {
        enabled: false,
        levels: [],
    },
});
```

---

### 2.3 Add Missing Price Alert Parameter UIs

**File:** `resources/js/components/alerts/PriceAlertConfig.vue`

**Add Gap Alert Configuration:**

```vue
<!-- Gap Alert Parameters -->
<div v-if="triggerType === 'gap'" class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('alerts.gap_threshold') }}
        </label>
        <div class="mt-1 relative rounded-md shadow-sm">
            <input
                v-model.number="localParams.gap_threshold_percent"
                type="number"
                step="0.5"
                min="0.5"
                max="20"
                class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 pr-12 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                placeholder="3.0"
            />
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                <span class="text-gray-500 sm:text-sm">%</span>
            </div>
        </div>
        <p class="mt-1 text-xs text-gray-500">
            {{ $t('alerts.gap_threshold_help') }}
        </p>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('alerts.gap_direction') }}
        </label>
        <select
            v-model="localParams.direction"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
        >
            <option value="both">{{ $t('alerts.direction_both') }}</option>
            <option value="above">{{ $t('alerts.gap_up') }}</option>
            <option value="below">{{ $t('alerts.gap_down') }}</option>
        </select>
    </div>

    <div class="flex items-center">
        <input
            v-model="localParams.check_at_open"
            type="checkbox"
            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
        />
        <label class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
            {{ $t('alerts.check_at_open') }}
        </label>
    </div>
</div>

<!-- 52-Week Alert Parameters -->
<div v-if="triggerType === '52week'" class="space-y-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('alerts.52week_type') }}
        </label>
        <div class="mt-2 space-y-2">
            <label class="inline-flex items-center">
                <input
                    v-model="localParams.type"
                    type="radio"
                    value="high"
                    class="form-radio h-4 w-4 text-indigo-600"
                />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $t('alerts.52week_high') }}
                </span>
            </label>
            <label class="inline-flex items-center ml-6">
                <input
                    v-model="localParams.type"
                    type="radio"
                    value="low"
                    class="form-radio h-4 w-4 text-indigo-600"
                />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $t('alerts.52week_low') }}
                </span>
            </label>
            <label class="inline-flex items-center ml-6">
                <input
                    v-model="localParams.type"
                    type="radio"
                    value="both"
                    class="form-radio h-4 w-4 text-indigo-600"
                />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $t('alerts.52week_both') }}
                </span>
            </label>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('alerts.cooldown_hours') }}
        </label>
        <input
            v-model.number="localParams.cooldown_hours"
            type="number"
            min="1"
            max="168"
            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
            placeholder="24"
        />
        <p class="mt-1 text-xs text-gray-500">
            {{ $t('alerts.cooldown_hours_help') }}
        </p>
    </div>
</div>
```

---

### 2.4 Create CompoundAlertBuilder Component

**File:** `resources/js/components/alerts/CompoundAlertBuilder.vue`

```vue
<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import type { AlertParameters, TriggerType } from '@/types/alerts';

interface Condition {
    id: string;
    type: 'signal' | 'anomaly' | 'pattern' | 'prediction' | 'recommendation';
    parameters: Record<string, any>;
}

interface Props {
    modelValue: {
        logic: 'and' | 'or';
        conditions: Condition[];
    };
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: Props['modelValue']];
}>();

const logic = ref<'and' | 'or'>(props.modelValue?.logic || 'and');
const conditions = ref<Condition[]>(props.modelValue?.conditions || []);

const conditionTypes = [
    { value: 'signal', label: 'Technical Signal', icon: '📊' },
    { value: 'anomaly', label: 'Anomaly Detection', icon: '⚡' },
    { value: 'pattern', label: 'Chart Pattern', icon: '📈' },
    { value: 'prediction', label: 'AI Prediction', icon: '🤖' },
    { value: 'recommendation', label: 'Recommendation', icon: '💡' },
];

const generateId = () => Math.random().toString(36).substring(2, 9);

const addCondition = () => {
    if (conditions.value.length >= 5) return;

    conditions.value.push({
        id: generateId(),
        type: 'signal',
        parameters: {
            indicators: [],
            signal_types: [],
            min_strength: 0.7,
        },
    });
    emitUpdate();
};

const removeCondition = (id: string) => {
    conditions.value = conditions.value.filter(c => c.id !== id);
    emitUpdate();
};

const updateConditionType = (id: string, type: Condition['type']) => {
    const condition = conditions.value.find(c => c.id === id);
    if (condition) {
        condition.type = type;
        condition.parameters = getDefaultParameters(type);
        emitUpdate();
    }
};

const getDefaultParameters = (type: Condition['type']): Record<string, any> => {
    switch (type) {
        case 'signal':
            return { indicators: [], signal_types: [], min_strength: 0.7 };
        case 'anomaly':
            return { anomaly_types: [], min_confidence: 0.8, severity: ['high', 'critical'] };
        case 'pattern':
            return { patterns: [], min_confidence: 0.7, pattern_status: 'confirmed' };
        case 'prediction':
            return { direction: 'up', min_confidence: 0.75, horizon: '1day' };
        case 'recommendation':
            return { recommendations: ['strong_buy', 'buy'], min_score: 0.75 };
        default:
            return {};
    }
};

const emitUpdate = () => {
    emit('update:modelValue', {
        logic: logic.value,
        conditions: conditions.value,
    });
};

watch(logic, emitUpdate);
</script>

<template>
    <div class="space-y-6">
        <!-- Logic Selector -->
        <div class="flex items-center space-x-4">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Trigger when:
            </span>
            <div class="flex rounded-md shadow-sm">
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 text-sm font-medium rounded-l-md border',
                        logic === 'and'
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600',
                    ]"
                    @click="logic = 'and'"
                >
                    ALL conditions match (AND)
                </button>
                <button
                    type="button"
                    :class="[
                        'px-4 py-2 text-sm font-medium rounded-r-md border-t border-r border-b',
                        logic === 'or'
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600',
                    ]"
                    @click="logic = 'or'"
                >
                    ANY condition matches (OR)
                </button>
            </div>
        </div>

        <!-- Conditions List -->
        <div class="space-y-4">
            <TransitionGroup name="list">
                <div
                    v-for="(condition, index) in conditions"
                    :key="condition.id"
                    class="relative bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700"
                >
                    <!-- Condition Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 text-xs font-medium">
                                {{ index + 1 }}
                            </span>
                            <select
                                :value="condition.type"
                                class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                @change="updateConditionType(condition.id, ($event.target as HTMLSelectElement).value as Condition['type'])"
                            >
                                <option v-for="ct in conditionTypes" :key="ct.value" :value="ct.value">
                                    {{ ct.icon }} {{ ct.label }}
                                </option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-red-500"
                            @click="removeCondition(condition.id)"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Condition Parameters (simplified for brevity) -->
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Signal Parameters -->
                        <template v-if="condition.type === 'signal'">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Min Strength</label>
                                <input
                                    v-model.number="condition.parameters.min_strength"
                                    type="range"
                                    min="0"
                                    max="1"
                                    step="0.05"
                                    class="w-full"
                                    @change="emitUpdate"
                                />
                                <span class="text-xs text-gray-500">{{ (condition.parameters.min_strength * 100).toFixed(0) }}%</span>
                            </div>
                        </template>

                        <!-- Prediction Parameters -->
                        <template v-if="condition.type === 'prediction'">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Direction</label>
                                <select
                                    v-model="condition.parameters.direction"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm"
                                    @change="emitUpdate"
                                >
                                    <option value="up">Up</option>
                                    <option value="down">Down</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Min Confidence</label>
                                <input
                                    v-model.number="condition.parameters.min_confidence"
                                    type="range"
                                    min="0.5"
                                    max="1"
                                    step="0.05"
                                    class="w-full"
                                    @change="emitUpdate"
                                />
                                <span class="text-xs text-gray-500">{{ (condition.parameters.min_confidence * 100).toFixed(0) }}%</span>
                            </div>
                        </template>

                        <!-- Add other condition type parameters... -->
                    </div>

                    <!-- Logic Connector -->
                    <div
                        v-if="index < conditions.length - 1"
                        class="absolute -bottom-3 left-1/2 transform -translate-x-1/2 px-2 py-0.5 bg-white dark:bg-gray-900 text-xs font-medium text-gray-500 dark:text-gray-400 rounded border border-gray-200 dark:border-gray-700"
                    >
                        {{ logic === 'and' ? 'AND' : 'OR' }}
                    </div>
                </div>
            </TransitionGroup>
        </div>

        <!-- Add Condition Button -->
        <button
            v-if="conditions.length < 5"
            type="button"
            class="w-full py-2 px-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 hover:border-indigo-500 hover:text-indigo-500 transition-colors"
            @click="addCondition"
        >
            + Add Condition ({{ conditions.length }}/5)
        </button>

        <!-- Summary -->
        <div v-if="conditions.length >= 2" class="p-3 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
            <p class="text-sm text-indigo-700 dark:text-indigo-300">
                <strong>Summary:</strong> This alert will trigger when
                <span v-if="logic === 'and'">ALL {{ conditions.length }} conditions</span>
                <span v-else>ANY of the {{ conditions.length }} conditions</span>
                are met.
            </p>
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
    transform: translateY(-10px);
}
</style>
```

---

### 2.5 Create Alert Chain Manager

**File:** `resources/js/pages/Alerts/Chains.vue`

**Purpose:** Manage sequential alert chains (Alert A triggers → Alert B activates)

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Alert, AlertChain } from '@/types/alerts';

interface Props {
    chains: AlertChain[];
    alerts: Alert[];
}

const props = defineProps<Props>();

const showCreateModal = ref(false);
const selectedChain = ref<AlertChain | null>(null);

const newChain = ref({
    name: '',
    trigger_alert_id: '',
    activate_alert_id: '',
    delay_minutes: 0,
    expires_after_minutes: null as number | null,
});

const availableTriggerAlerts = computed(() => {
    return props.alerts.filter(a => a.status === 'active');
});

const availableActivateAlerts = computed(() => {
    return props.alerts.filter(a =>
        a.status === 'active' || a.status === 'chained'
    ).filter(a => a.id !== newChain.value.trigger_alert_id);
});

const createChain = () => {
    router.post('/alerts/chains', newChain.value, {
        onSuccess: () => {
            showCreateModal.value = false;
            resetForm();
        },
    });
};

const deleteChain = (chainId: string) => {
    if (confirm('Are you sure you want to delete this chain?')) {
        router.delete(`/alerts/chains/${chainId}`);
    }
};

const toggleChain = (chain: AlertChain) => {
    router.patch(`/alerts/chains/${chain.id}`, {
        is_active: !chain.is_active,
    });
};

const resetForm = () => {
    newChain.value = {
        name: '',
        trigger_alert_id: '',
        activate_alert_id: '',
        delay_minutes: 0,
        expires_after_minutes: null,
    };
};

const getAlertLabel = (alertId: string) => {
    const alert = props.alerts.find(a => a.id === alertId);
    if (!alert) return 'Unknown Alert';
    return `${alert.asset?.symbol || 'N/A'} - ${alert.trigger_type}`;
};
</script>

<template>
    <AppLayout>
        <Head title="Alert Chains" />

        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        Alert Chains
                    </h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Create sequential alerts that trigger each other
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    @click="showCreateModal = true"
                >
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Chain
                </button>
            </div>

            <!-- Chains List -->
            <div class="space-y-4">
                <div
                    v-for="chain in chains"
                    :key="chain.id"
                    class="bg-white dark:bg-gray-800 rounded-lg shadow p-6"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <!-- Trigger Alert -->
                                <div class="px-3 py-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                    <span class="text-xs text-blue-600 dark:text-blue-400 font-medium">WHEN</span>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ getAlertLabel(chain.trigger_alert_id) }}
                                    </p>
                                </div>

                                <!-- Arrow with delay -->
                                <div class="flex flex-col items-center">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                    <span v-if="chain.delay_minutes > 0" class="text-xs text-gray-500">
                                        {{ chain.delay_minutes }}min
                                    </span>
                                </div>

                                <!-- Activate Alert -->
                                <div class="px-3 py-2 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                    <span class="text-xs text-green-600 dark:text-green-400 font-medium">THEN</span>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ getAlertLabel(chain.activate_alert_id) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    chain.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                ]"
                            >
                                {{ chain.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center space-x-2">
                            <button
                                type="button"
                                class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                @click="toggleChain(chain)"
                            >
                                {{ chain.is_active ? 'Disable' : 'Enable' }}
                            </button>
                            <button
                                type="button"
                                class="text-sm text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                @click="deleteChain(chain.id)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Chain Info -->
                    <div class="mt-4 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                        <span v-if="chain.expires_after_minutes">
                            Expires {{ chain.expires_after_minutes }} minutes after activation
                        </span>
                        <span>
                            Executed {{ chain.execution_count || 0 }} times
                        </span>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="chains.length === 0"
                    class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg"
                >
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No chains</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Create a chain to link alerts together
                    </p>
                </div>
            </div>
        </div>

        <!-- Create Modal (simplified) -->
        <Teleport to="body">
            <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Modal content... -->
            </div>
        </Teleport>
    </AppLayout>
</template>
```

---

## Phase 3: Telegram Bot Completion

**Priority:** HIGH
**Effort:** 8-10 hours
**Dependencies:** Phase 1 backend fixes

### 3.1 Complete Signal Alert Implementation

**File:** `app/Telegram/Handlers/Alerts/AlertCreateHandler.php`

**Current State:** Signal alert type shows in menu but has placeholder implementation.

**Implementation:**

```php
// In handleTriggerTypeSelection method (around line 219)
case 'signal':
    $draft['step'] = 'signal_indicators';
    $draft['trigger_type'] = 'signal';
    $draft['parameters'] = [
        'indicators' => [],
        'signal_types' => [],
        'min_strength' => 0.7,
        'any_or_all' => 'any',
    ];

    $this->bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $this->isArabic($user)
            ? "📊 *اختر المؤشرات الفنية*\n\nاختر المؤشرات التي تريد تتبعها:"
            : "📊 *Select Technical Indicators*\n\nChoose the indicators you want to track:",
        'parse_mode' => 'Markdown',
        'reply_markup' => $this->buildSignalIndicatorKeyboard($user),
    ]);
    break;

// Add new method
private function buildSignalIndicatorKeyboard(User $user): array
{
    $isArabic = $this->isArabic($user);

    $indicators = [
        ['RSI', 'rsi', $isArabic ? 'مؤشر القوة النسبية' : 'RSI'],
        ['MACD', 'macd', $isArabic ? 'الماكد' : 'MACD'],
        ['Bollinger', 'bollinger', $isArabic ? 'بولينجر' : 'Bollinger Bands'],
        ['EMA', 'ema', $isArabic ? 'المتوسط الأسي' : 'EMA Cross'],
        ['Stochastic', 'stochastic', $isArabic ? 'ستوكاستيك' : 'Stochastic'],
    ];

    $buttons = [];
    foreach (array_chunk($indicators, 2) as $row) {
        $buttonRow = [];
        foreach ($row as $indicator) {
            $buttonRow[] = [
                'text' => $indicator[2],
                'callback_data' => "alert:create:indicator:{$indicator[1]}",
            ];
        }
        $buttons[] = $buttonRow;
    }

    $buttons[] = [
        ['text' => $isArabic ? '✅ متابعة' : '✅ Continue', 'callback_data' => 'alert:create:signal:continue'],
    ];
    $buttons[] = [
        ['text' => $isArabic ? '❌ إلغاء' : '❌ Cancel', 'callback_data' => 'alert:menu'],
    ];

    return ['inline_keyboard' => $buttons];
}

// Handle indicator selection
private function handleIndicatorSelection(User $user, string $indicator): void
{
    $draft = $user->telegram_alert_draft ?? [];

    $indicators = $draft['parameters']['indicators'] ?? [];

    if (in_array($indicator, $indicators)) {
        $indicators = array_diff($indicators, [$indicator]);
    } else {
        $indicators[] = $indicator;
    }

    $draft['parameters']['indicators'] = array_values($indicators);
    $user->update(['telegram_alert_draft' => $draft]);

    // Update keyboard to show selection state
    $this->updateIndicatorKeyboard($user, $indicators);
}

// Handle signal type selection
private function handleSignalTypeSelection(User $user, int $chatId): void
{
    $isArabic = $this->isArabic($user);

    $signalTypes = [
        ['oversold', $isArabic ? 'تشبع بيعي' : 'Oversold'],
        ['overbought', $isArabic ? 'تشبع شرائي' : 'Overbought'],
        ['bullish_cross', $isArabic ? 'تقاطع صعودي' : 'Bullish Cross'],
        ['bearish_cross', $isArabic ? 'تقاطع هبوطي' : 'Bearish Cross'],
        ['breakout', $isArabic ? 'اختراق' : 'Breakout'],
        ['reversal', $isArabic ? 'انعكاس' : 'Reversal'],
    ];

    $buttons = [];
    foreach (array_chunk($signalTypes, 2) as $row) {
        $buttonRow = [];
        foreach ($row as $type) {
            $buttonRow[] = [
                'text' => $type[1],
                'callback_data' => "alert:create:signaltype:{$type[0]}",
            ];
        }
        $buttons[] = $buttonRow;
    }

    $buttons[] = [
        ['text' => $isArabic ? '✅ متابعة' : '✅ Continue', 'callback_data' => 'alert:create:signal:strength'],
    ];

    $this->bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $isArabic
            ? "📈 *اختر نوع الإشارة*\n\nاختر أنواع الإشارات التي تريد تلقي تنبيهات عنها:"
            : "📈 *Select Signal Types*\n\nChoose the signal types you want to be alerted about:",
        'parse_mode' => 'Markdown',
        'reply_markup' => ['inline_keyboard' => $buttons],
    ]);
}
```

### 3.2 Complete Prediction Alert Implementation

**File:** `app/Telegram/Handlers/Alerts/AlertCreateHandler.php`

```php
case 'prediction':
    $draft['step'] = 'prediction_direction';
    $draft['trigger_type'] = 'prediction';
    $draft['parameters'] = [
        'prediction_type' => 'price_direction',
        'horizon' => '1day',
        'direction' => 'up',
        'min_confidence' => 0.75,
        'min_predicted_change_percent' => 2.0,
    ];

    $this->showPredictionDirectionSelection($user, $chatId);
    break;

private function showPredictionDirectionSelection(User $user, int $chatId): void
{
    $isArabic = $this->isArabic($user);

    $buttons = [
        [
            ['text' => $isArabic ? '📈 صعود' : '📈 Up', 'callback_data' => 'alert:create:pred:direction:up'],
            ['text' => $isArabic ? '📉 هبوط' : '📉 Down', 'callback_data' => 'alert:create:pred:direction:down'],
        ],
        [
            ['text' => $isArabic ? '↕️ أي اتجاه' : '↕️ Either', 'callback_data' => 'alert:create:pred:direction:both'],
        ],
        [
            ['text' => $isArabic ? '❌ إلغاء' : '❌ Cancel', 'callback_data' => 'alert:menu'],
        ],
    ];

    $this->bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $isArabic
            ? "🤖 *تنبيه التوقع*\n\nاختر اتجاه التوقع الذي تريد تلقي تنبيهات عنه:"
            : "🤖 *Prediction Alert*\n\nSelect the prediction direction you want to be alerted about:",
        'parse_mode' => 'Markdown',
        'reply_markup' => ['inline_keyboard' => $buttons],
    ]);
}

private function handlePredictionDirection(User $user, int $chatId, string $direction): void
{
    $draft = $user->telegram_alert_draft ?? [];
    $draft['parameters']['direction'] = $direction;
    $draft['step'] = 'prediction_horizon';
    $user->update(['telegram_alert_draft' => $draft]);

    $this->showPredictionHorizonSelection($user, $chatId);
}

private function showPredictionHorizonSelection(User $user, int $chatId): void
{
    $isArabic = $this->isArabic($user);

    $buttons = [
        [
            ['text' => $isArabic ? '1 ساعة' : '1 Hour', 'callback_data' => 'alert:create:pred:horizon:1hour'],
            ['text' => $isArabic ? '4 ساعات' : '4 Hours', 'callback_data' => 'alert:create:pred:horizon:4hours'],
        ],
        [
            ['text' => $isArabic ? '1 يوم' : '1 Day', 'callback_data' => 'alert:create:pred:horizon:1day'],
            ['text' => $isArabic ? '1 أسبوع' : '1 Week', 'callback_data' => 'alert:create:pred:horizon:1week'],
        ],
        [
            ['text' => $isArabic ? '❌ إلغاء' : '❌ Cancel', 'callback_data' => 'alert:menu'],
        ],
    ];

    $this->bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $isArabic
            ? "⏱ *الأفق الزمني*\n\nاختر الفترة الزمنية للتوقع:"
            : "⏱ *Time Horizon*\n\nSelect the prediction time horizon:",
        'parse_mode' => 'Markdown',
        'reply_markup' => ['inline_keyboard' => $buttons],
    ]);
}

private function handlePredictionHorizon(User $user, int $chatId, string $horizon): void
{
    $draft = $user->telegram_alert_draft ?? [];
    $draft['parameters']['horizon'] = $horizon;
    $draft['step'] = 'prediction_confidence';
    $user->update(['telegram_alert_draft' => $draft]);

    $this->showPredictionConfidenceSelection($user, $chatId);
}

private function showPredictionConfidenceSelection(User $user, int $chatId): void
{
    $isArabic = $this->isArabic($user);

    $buttons = [
        [
            ['text' => '60%', 'callback_data' => 'alert:create:pred:confidence:0.6'],
            ['text' => '70%', 'callback_data' => 'alert:create:pred:confidence:0.7'],
        ],
        [
            ['text' => '80%', 'callback_data' => 'alert:create:pred:confidence:0.8'],
            ['text' => '90%', 'callback_data' => 'alert:create:pred:confidence:0.9'],
        ],
        [
            ['text' => $isArabic ? '❌ إلغاء' : '❌ Cancel', 'callback_data' => 'alert:menu'],
        ],
    ];

    $this->bot->sendMessage([
        'chat_id' => $chatId,
        'text' => $isArabic
            ? "📊 *الحد الأدنى للثقة*\n\nاختر الحد الأدنى لمستوى الثقة في التوقع:"
            : "📊 *Minimum Confidence*\n\nSelect the minimum confidence level for predictions:",
        'parse_mode' => 'Markdown',
        'reply_markup' => ['inline_keyboard' => $buttons],
    ]);
}
```

### 3.3 Localize Acknowledge Button

**File:** `app/Services/TelegramMessageBuilder.php`

**Find and replace hardcoded "Acknowledge":**

```php
// Current (around line 690):
['text' => 'Acknowledge', 'callback_data' => "ack:{$historyId}"]

// Replace with:
['text' => $this->isArabic($user) ? '✓ تأكيد' : '✓ Acknowledge', 'callback_data' => "ack:{$historyId}"]
```

**Also update snooze button texts:**

```php
private function buildAlertActionKeyboard(Alert $alert, ?AlertHistory $history, User $user): array
{
    $isArabic = $this->isArabic($user);

    $buttons = [];

    // Snooze options
    $snoozeButtons = [
        ['text' => $isArabic ? '1 ساعة' : '1h', 'callback_data' => "snooze:{$alert->id}:60"],
        ['text' => $isArabic ? '4 ساعات' : '4h', 'callback_data' => "snooze:{$alert->id}:240"],
        ['text' => $isArabic ? '1 يوم' : '1d', 'callback_data' => "snooze:{$alert->id}:1440"],
    ];
    $buttons[] = $snoozeButtons;

    // Acknowledge button
    if ($history && !$history->isAcknowledged()) {
        $buttons[] = [
            ['text' => $isArabic ? '✓ تأكيد الاستلام' : '✓ Acknowledge', 'callback_data' => "ack:{$history->id}"],
        ];
    }

    // View alert button
    $buttons[] = [
        ['text' => $isArabic ? '👁 عرض التنبيه' : '👁 View Alert', 'callback_data' => "alert:view:{$alert->id}"],
    ];

    return ['inline_keyboard' => $buttons];
}
```

### 3.4 Fix Watchlist Integration

**File:** `app/Telegram/Handlers/Alerts/AlertCreateHandler.php`

**Current State (line 521-522):**
```php
private function getWatchlistAssets(User $user): Collection
{
    return collect(); // Empty collection
}
```

**Fix:**
```php
private function getWatchlistAssets(User $user): Collection
{
    return $user->wishlistAssets()
        ->with('asset:id,symbol,name_en,name_ar')
        ->get()
        ->map(function ($wishlistAsset) {
            return $wishlistAsset->asset;
        })
        ->filter()
        ->take(10); // Limit to 10 for Telegram keyboard
}
```

---

## Phase 4: Testing & Verification

**Priority:** MEDIUM
**Effort:** 8-10 hours
**Dependencies:** Phases 1-3

### 4.1 Backend Unit Tests

**File:** `tests/Unit/Services/AlertMatcherTest.php`

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Alert;
use App\Services\AlertMatcher;
use Tests\TestCase;

class AlertMatcherTest extends TestCase
{
    private AlertMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new AlertMatcher();
    }

    /** @test */
    public function it_triggers_target_price_alert_when_price_crosses_above(): void
    {
        $alert = Alert::factory()->make([
            'trigger_type' => 'target_price',
            'parameters' => [
                'target_price' => 50.00,
                'direction' => 'above',
            ],
        ]);

        $result = $this->matcher->evaluateTargetPrice($alert, 51.00, 49.00);

        $this->assertTrue($result->triggered);
        $this->assertEquals(51.00, $result->triggerValue);
    }

    /** @test */
    public function it_does_not_trigger_when_price_does_not_cross(): void
    {
        $alert = Alert::factory()->make([
            'trigger_type' => 'target_price',
            'parameters' => [
                'target_price' => 50.00,
                'direction' => 'above',
            ],
        ]);

        $result = $this->matcher->evaluateTargetPrice($alert, 49.00, 48.00);

        $this->assertFalse($result->triggered);
    }

    /** @test */
    public function it_triggers_zone_alert_only_on_entry(): void
    {
        $alert = Alert::factory()->make([
            'trigger_type' => 'zone',
            'parameters' => [
                'zone_low' => 48.00,
                'zone_high' => 52.00,
                'trigger_on' => 'enter',
            ],
        ]);

        // Price moves from outside (47) to inside (50) zone
        $result = $this->matcher->evaluateZone($alert, 50.00, 47.00);
        $this->assertTrue($result->triggered);
        $this->assertEquals('entered_zone', $result->context['event']);

        // Price stays inside zone
        $result = $this->matcher->evaluateZone($alert, 51.00, 50.00);
        $this->assertFalse($result->triggered);
    }

    /** @test */
    public function it_evaluates_compound_alerts_with_and_logic(): void
    {
        $alert = Alert::factory()->make([
            'trigger_type' => 'compound_intelligence',
            'condition_logic' => 'and',
            'parameters' => [
                'conditions' => [
                    ['type' => 'signal', 'min_strength' => 0.5],
                    ['type' => 'prediction', 'direction' => 'up', 'min_confidence' => 0.5],
                ],
            ],
        ]);

        $signalData = [
            'pid' => 'TEST',
            'strength' => 0.8,
            'confidence' => 0.9,
            'direction' => 'up',
        ];

        $result = $this->matcher->evaluateCompound($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertEquals(2, count($result->context['triggered_conditions']));
    }

    /** @test */
    public function it_evaluates_compound_alerts_with_or_logic(): void
    {
        $alert = Alert::factory()->make([
            'trigger_type' => 'compound_intelligence',
            'condition_logic' => 'or',
            'parameters' => [
                'conditions' => [
                    ['type' => 'signal', 'min_strength' => 0.9], // Won't match
                    ['type' => 'prediction', 'direction' => 'up', 'min_confidence' => 0.5], // Will match
                ],
            ],
        ]);

        $signalData = [
            'pid' => 'TEST',
            'strength' => 0.6, // Below 0.9 threshold
            'confidence' => 0.9,
            'direction' => 'up',
        ];

        $result = $this->matcher->evaluateCompound($alert, $signalData);

        $this->assertTrue($result->triggered);
        $this->assertEquals(1, count($result->context['triggered_conditions']));
    }
}
```

### 4.2 Integration Tests

**File:** `tests/Feature/AlertProcessingTest.php`

```php
<?php

namespace Tests\Feature;

use App\Jobs\Alerts\ProcessIntelligenceAlerts;
use App\Jobs\Alerts\SendAlertNotification;
use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AlertProcessingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_dispatches_notification_when_price_alert_triggers(): void
    {
        Queue::fake();

        $user = User::factory()->create(['telegram_id' => '123456']);
        $asset = Asset::factory()->create(['inv_id' => 'TEST123']);
        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'status' => 'active',
            'parameters' => [
                'target_price' => 50.00,
                'direction' => 'above',
            ],
        ]);

        // Simulate price update
        $signalData = [
            'pid' => 'TEST123',
            'last' => 51.00,
            'prevClose' => 49.00,
        ];

        ProcessIntelligenceAlerts::dispatch($signalData, 'price_updates');

        Queue::assertPushed(SendAlertNotification::class, function ($job) use ($alert) {
            return $job->alert->id === $alert->id;
        });
    }

    /** @test */
    public function it_respects_cooldown_period(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $asset = Asset::factory()->create(['inv_id' => 'TEST123']);
        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'status' => 'active',
            'cooldown_minutes' => 60,
            'last_triggered_at' => now()->subMinutes(30), // 30 minutes ago
            'parameters' => [
                'target_price' => 50.00,
                'direction' => 'above',
            ],
        ]);

        $signalData = [
            'pid' => 'TEST123',
            'last' => 51.00,
            'prevClose' => 49.00,
        ];

        ProcessIntelligenceAlerts::dispatch($signalData, 'price_updates');

        Queue::assertNotPushed(SendAlertNotification::class);
    }
}
```

### 4.3 Telegram Bot Tests

**File:** `tests/Feature/Telegram/AlertCreateHandlerTest.php`

```php
<?php

namespace Tests\Feature\Telegram;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use App\Telegram\Handlers\Alerts\AlertCreateHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertCreateHandlerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_creates_target_price_alert_via_telegram(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '123456',
        ]);
        $asset = Asset::factory()->create([
            'symbol' => 'COMI',
        ]);

        // Simulate full creation flow
        $user->update([
            'telegram_alert_draft' => [
                'step' => 'confirm',
                'type' => 'price',
                'asset_id' => $asset->id,
                'trigger_type' => 'target_price',
                'direction' => 'above',
                'parameters' => [
                    'target_price' => 55.00,
                ],
                'priority' => 'high',
            ],
        ]);

        $handler = app(AlertCreateHandler::class);
        $handler->handleConfirmation($user, 123456);

        $this->assertDatabaseHas('alerts', [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'status' => 'active',
        ]);

        $alert = Alert::where('user_id', $user->id)->first();
        $this->assertEquals(55.00, $alert->parameters['target_price']);
    }

    /** @test */
    public function it_creates_signal_alert_via_telegram(): void
    {
        $user = User::factory()->create([
            'telegram_id' => '123456',
        ]);
        $asset = Asset::factory()->create([
            'symbol' => 'COMI',
        ]);

        $user->update([
            'telegram_alert_draft' => [
                'step' => 'confirm',
                'type' => 'signal',
                'asset_id' => $asset->id,
                'trigger_type' => 'signal',
                'parameters' => [
                    'indicators' => ['rsi', 'macd'],
                    'signal_types' => ['oversold', 'bullish_cross'],
                    'min_strength' => 0.7,
                ],
                'priority' => 'medium',
            ],
        ]);

        $handler = app(AlertCreateHandler::class);
        $handler->handleConfirmation($user, 123456);

        $alert = Alert::where('user_id', $user->id)->first();
        $this->assertNotNull($alert);
        $this->assertEquals('signal', $alert->type);
        $this->assertContains('rsi', $alert->parameters['indicators']);
    }
}
```

---

## Phase 5: Documentation & Cleanup

**Priority:** LOW
**Effort:** 4-6 hours
**Dependencies:** Phases 1-4

### 5.1 API Documentation

Create OpenAPI/Swagger documentation for all alert endpoints.

### 5.2 User Guide

Create user-facing documentation for:
- How to create each alert type
- Understanding trigger conditions
- Notification settings
- Telegram bot commands

### 5.3 Code Cleanup

- Remove TODO comments that are completed
- Add PHPDoc blocks to new methods
- Update TypeScript types if needed
- Remove unused imports

---

## Implementation Schedule

| Phase | Duration | Dependencies |
|-------|----------|--------------|
| Phase 1: Critical Fixes | Day 1 | None |
| Phase 2: Frontend | Days 2-4 | Phase 1 |
| Phase 3: Telegram | Days 3-4 | Phase 1 |
| Phase 4: Testing | Day 5 | Phases 1-3 |
| Phase 5: Documentation | Day 6 | Phases 1-4 |

**Total Estimated Time:** 6 working days

---

## Success Criteria

- [ ] All 7 price trigger types working correctly
- [ ] All 6 intelligence trigger types working correctly
- [ ] Compound alerts with AND/OR logic functional
- [ ] Zone alerts trigger only on entry/exit (not every tick)
- [ ] Real-time price_updates channel triggers alerts
- [ ] Frontend can create all alert types
- [ ] Telegram bot can create signal and prediction alerts
- [ ] Toast notifications appear for critical alerts
- [ ] Delivery config is configurable per alert
- [ ] All tests passing
- [ ] No critical bugs in production

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking existing alerts | Run all tests before deployment |
| Performance degradation | Monitor query times after changes |
| User confusion with new features | Add tooltips and help text |
| Telegram rate limiting | Implement backoff in notification sending |

---

## Rollback Plan

If critical issues are discovered after deployment:

1. Revert to previous AlertMatcher implementation
2. Disable new trigger types via feature flag
3. Keep existing alert data intact
4. Notify users of temporary feature unavailability

---

## Post-Implementation Monitoring

After deployment, monitor:
- Alert trigger rates
- Notification delivery success rate
- Queue depth for alert jobs
- Error logs for new code paths
- User feedback via Telegram/support

---

*Document created: 2026-01-12*
*Last updated: 2026-01-12*
*Author: Claude Code Assistant*
