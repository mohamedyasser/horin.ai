# Task 08: Advanced Features

**Priority:** P2
**Effort:** 4 days
**Dependencies:** Tasks 01-07

---

## Objective

Implement advanced alert features including compound alerts (AND/OR logic), alert chains, backtest functionality, alert templates, and smart defaults.

---

## Checklist

- [ ] Implement compound alerts (AND/OR conditions)
- [ ] Implement alert chains (triggered sequences)
- [ ] Complete backtest functionality
- [ ] Create alert templates system
- [ ] Implement smart defaults based on user profile
- [ ] Add proximity alerts (near target)
- [ ] Implement digest notifications
- [ ] Add rate limiting with token bucket

---

## Compound Alerts

Compound alerts allow users to combine multiple conditions with AND/OR logic.

### Database Structure

Already covered in `alerts` table:
- `condition_logic`: enum('single', 'and', 'or')
- `parameters.conditions`: array of condition objects

### Compound Alert Parameters Schema

```json
{
    "trigger_type": "compound_intelligence",
    "condition_logic": "and",
    "parameters": {
        "conditions": [
            {
                "id": "cond_1",
                "type": "signal",
                "indicators": ["RSI"],
                "signal_types": ["oversold"],
                "min_strength": 0.7
            },
            {
                "id": "cond_2",
                "type": "prediction",
                "direction": "up",
                "min_confidence": 0.7
            },
            {
                "id": "cond_3",
                "type": "pattern",
                "patterns": ["double_bottom"],
                "pattern_status": "confirmed"
            }
        ],
        "require_all": true
    }
}
```

### CompoundAlertEvaluator Service

```php
<?php

namespace App\Services\Alerts\Evaluators;

use App\Models\Alert;
use Illuminate\Support\Collection;

class CompoundAlertEvaluator
{
    private array $evaluators = [];

    public function __construct(
        private readonly SignalAlertEvaluator $signalEvaluator,
        private readonly PredictionAlertEvaluator $predictionEvaluator,
        private readonly PatternAlertEvaluator $patternEvaluator,
        private readonly AnomalyAlertEvaluator $anomalyEvaluator
    ) {
        $this->evaluators = [
            'signal' => $this->signalEvaluator,
            'prediction' => $this->predictionEvaluator,
            'pattern' => $this->patternEvaluator,
            'anomaly' => $this->anomalyEvaluator,
        ];
    }

    public function evaluate(Alert $alert, array $currentData): EvaluationResult
    {
        $conditions = $alert->parameters['conditions'] ?? [];
        $logic = $alert->condition_logic;

        if (empty($conditions)) {
            return EvaluationResult::notTriggered('No conditions defined');
        }

        $results = $this->evaluateConditions($conditions, $currentData);

        return match ($logic) {
            'and' => $this->evaluateAnd($results),
            'or' => $this->evaluateOr($results),
            default => EvaluationResult::notTriggered('Unknown logic'),
        };
    }

    private function evaluateConditions(array $conditions, array $currentData): Collection
    {
        return collect($conditions)->map(function ($condition) use ($currentData) {
            $type = $condition['type'];
            $evaluator = $this->evaluators[$type] ?? null;

            if (!$evaluator) {
                return [
                    'condition_id' => $condition['id'] ?? null,
                    'type' => $type,
                    'triggered' => false,
                    'reason' => 'Unknown condition type',
                ];
            }

            $result = $evaluator->evaluateCondition($condition, $currentData);

            return [
                'condition_id' => $condition['id'] ?? null,
                'type' => $type,
                'triggered' => $result->triggered,
                'value' => $result->value,
                'reason' => $result->reason,
            ];
        });
    }

    private function evaluateAnd(Collection $results): EvaluationResult
    {
        $allTriggered = $results->every('triggered');

        if ($allTriggered) {
            return EvaluationResult::triggered(
                value: null,
                context: ['conditions' => $results->toArray()],
                reason: 'All conditions met'
            );
        }

        $failed = $results->firstWhere('triggered', false);
        return EvaluationResult::notTriggered(
            "Condition {$failed['type']} not met: {$failed['reason']}"
        );
    }

    private function evaluateOr(Collection $results): EvaluationResult
    {
        $anyTriggered = $results->contains('triggered', true);

        if ($anyTriggered) {
            $triggered = $results->filter(fn($r) => $r['triggered']);
            return EvaluationResult::triggered(
                value: null,
                context: ['triggered_conditions' => $triggered->toArray()],
                reason: 'One or more conditions met'
            );
        }

        return EvaluationResult::notTriggered('No conditions met');
    }
}
```

### EvaluationResult Class

```php
<?php

namespace App\Services\Alerts\Evaluators;

class EvaluationResult
{
    public function __construct(
        public readonly bool $triggered,
        public readonly mixed $value = null,
        public readonly array $context = [],
        public readonly ?string $reason = null
    ) {}

    public static function triggered(
        mixed $value = null,
        array $context = [],
        ?string $reason = null
    ): self {
        return new self(true, $value, $context, $reason);
    }

    public static function notTriggered(?string $reason = null): self
    {
        return new self(false, null, [], $reason);
    }
}
```

---

## Alert Chains

Alert chains allow one alert's trigger to activate another alert.

### ProcessAlertChains Job

```php
<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\AlertChain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAlertChains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Alert $triggeredAlert
    ) {
        $this->queue = 'alerts';
    }

    public function handle(): void
    {
        // Find chains where this alert is the trigger
        $chains = AlertChain::where('trigger_alert_id', $this->triggeredAlert->id)
            ->where('is_active', true)
            ->with('activateAlert')
            ->get();

        foreach ($chains as $chain) {
            $this->processChain($chain);
        }
    }

    private function processChain(AlertChain $chain): void
    {
        $alertToActivate = $chain->activateAlert;

        if (!$alertToActivate || $alertToActivate->status !== 'chained') {
            return;
        }

        // Check delay
        if ($chain->delay_minutes > 0) {
            ActivateChainedAlert::dispatch($chain)
                ->delay(now()->addMinutes($chain->delay_minutes));

            Log::info('Chained alert activation scheduled', [
                'chain_id' => $chain->id,
                'activate_alert_id' => $alertToActivate->id,
                'delay_minutes' => $chain->delay_minutes,
            ]);

            return;
        }

        // Activate immediately
        $this->activateAlert($chain);
    }

    private function activateAlert(AlertChain $chain): void
    {
        $alert = $chain->activateAlert;

        $updateData = [
            'status' => 'active',
            'chain_from_id' => $this->triggeredAlert->id,
        ];

        // Set expiration if configured
        if ($chain->expires_after_minutes) {
            $updateData['expires_at'] = now()->addMinutes($chain->expires_after_minutes);
        }

        $alert->update($updateData);

        Log::info('Chained alert activated', [
            'chain_id' => $chain->id,
            'trigger_alert_id' => $this->triggeredAlert->id,
            'activated_alert_id' => $alert->id,
        ]);
    }
}
```

### ActivateChainedAlert Job (Delayed)

```php
<?php

namespace App\Jobs\Alerts;

use App\Models\AlertChain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivateChainedAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public AlertChain $chain
    ) {
        $this->queue = 'alerts';
    }

    public function handle(): void
    {
        // Verify chain is still active
        if (!$this->chain->is_active) {
            Log::info('Chain deactivated before delayed execution', [
                'chain_id' => $this->chain->id,
            ]);
            return;
        }

        $alert = $this->chain->activateAlert;

        if ($alert->status !== 'chained') {
            Log::info('Alert already activated or changed status', [
                'alert_id' => $alert->id,
                'status' => $alert->status,
            ]);
            return;
        }

        $updateData = [
            'status' => 'active',
        ];

        if ($this->chain->expires_after_minutes) {
            $updateData['expires_at'] = now()->addMinutes($this->chain->expires_after_minutes);
        }

        $alert->update($updateData);

        Log::info('Delayed chained alert activated', [
            'chain_id' => $this->chain->id,
            'alert_id' => $alert->id,
        ]);
    }
}
```

### Alert Chain UI Component

```vue
<script setup lang="ts">
import { ref, computed } from 'vue';
import type { Alert } from '@/types/alerts';

interface Props {
    alerts: Alert[];
    existingChain?: {
        id: string;
        name: string;
        trigger_alert_id: string;
        activate_alert_id: string;
        delay_minutes: number;
        expires_after_minutes?: number;
    };
}

const props = defineProps<Props>();
const emit = defineEmits<{
    save: [chain: {
        name: string;
        trigger_alert_id: string;
        activate_alert_id: string;
        delay_minutes: number;
        expires_after_minutes?: number;
    }];
}>();

const chainName = ref(props.existingChain?.name || '');
const triggerAlertId = ref(props.existingChain?.trigger_alert_id || '');
const activateAlertId = ref(props.existingChain?.activate_alert_id || '');
const delayMinutes = ref(props.existingChain?.delay_minutes || 0);
const expiresAfterMinutes = ref(props.existingChain?.expires_after_minutes || null);

const activeAlerts = computed(() =>
    props.alerts.filter(a => a.status === 'active' || a.status === 'chained')
);

const chainableAlerts = computed(() =>
    props.alerts.filter(a => a.status === 'chained' && a.id !== triggerAlertId.value)
);

const canSave = computed(() =>
    chainName.value && triggerAlertId.value && activateAlertId.value
);

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
    <div class="space-y-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                Chain Name
            </label>
            <input
                v-model="chainName"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                placeholder="e.g., Breakout then Target"
            />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    When this alert triggers...
                </label>
                <select
                    v-model="triggerAlertId"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                >
                    <option value="">Select alert</option>
                    <option v-for="alert in activeAlerts" :key="alert.id" :value="alert.id">
                        {{ alert.asset?.symbol }} - {{ alert.trigger_type }}
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    ...activate this alert
                </label>
                <select
                    v-model="activateAlertId"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                >
                    <option value="">Select alert</option>
                    <option v-for="alert in chainableAlerts" :key="alert.id" :value="alert.id">
                        {{ alert.asset?.symbol }} - {{ alert.trigger_type }}
                    </option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Delay before activation (minutes)
                </label>
                <input
                    v-model.number="delayMinutes"
                    type="number"
                    min="0"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Expires after (minutes, optional)
                </label>
                <input
                    v-model.number="expiresAfterMinutes"
                    type="number"
                    min="1"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                    placeholder="No expiration"
                />
            </div>
        </div>

        <!-- Visual representation -->
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <div class="flex items-center justify-center space-x-4">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mb-2">
                        <span class="text-2xl">🎯</span>
                    </div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Trigger</span>
                </div>

                <div class="flex items-center">
                    <div class="h-0.5 w-8 bg-gray-300 dark:bg-gray-600"></div>
                    <span v-if="delayMinutes > 0" class="text-xs text-gray-500 mx-1">
                        {{ delayMinutes }}m
                    </span>
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <div class="h-0.5 w-8 bg-gray-300 dark:bg-gray-600"></div>
                </div>

                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mb-2">
                        <span class="text-2xl">🔔</span>
                    </div>
                    <span class="text-sm text-gray-600 dark:text-gray-400">Activate</span>
                </div>
            </div>
        </div>

        <button
            @click="handleSave"
            :disabled="!canSave"
            class="w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:bg-gray-300 text-white rounded-lg transition"
        >
            {{ existingChain ? 'Update Chain' : 'Create Chain' }}
        </button>
    </div>
</template>
```

---

## Alert Templates System

### AlertTemplateService

```php
<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AlertTemplate;
use App\Models\User;

class AlertTemplateService
{
    /**
     * Create an alert from a template
     */
    public function createFromTemplate(AlertTemplate $template, User $user, array $overrides = []): Alert
    {
        $data = [
            'user_id' => $user->id,
            'template_id' => $template->id,
            'type' => $template->type,
            'trigger_type' => $template->trigger_type,
            'parameters' => array_merge($template->default_parameters, $overrides['parameters'] ?? []),
            'delivery_config' => $overrides['delivery_config'] ?? $template->default_delivery_config,
            'scope' => $overrides['scope'] ?? 'single_asset',
            'asset_id' => $overrides['asset_id'] ?? null,
            'condition_logic' => 'single',
            'status' => 'active',
        ];

        $alert = Alert::create($data);

        // Increment usage count
        $template->increment('usage_count');

        return $alert;
    }

    /**
     * Create a template from an existing alert
     */
    public function createFromAlert(Alert $alert, string $name, bool $isPublic = false): AlertTemplate
    {
        return AlertTemplate::create([
            'user_id' => $alert->user_id,
            'name' => $name,
            'name_ar' => $name, // User can update later
            'type' => $alert->type,
            'trigger_type' => $alert->trigger_type,
            'default_parameters' => $this->sanitizeParameters($alert->parameters),
            'default_delivery_config' => $alert->delivery_config,
            'is_public' => $isPublic,
        ]);
    }

    /**
     * Get recommended templates for a user
     */
    public function getRecommendedTemplates(User $user): array
    {
        // System templates
        $systemTemplates = AlertTemplate::whereNull('user_id')
            ->orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();

        // User's most used templates
        $userTemplates = AlertTemplate::where('user_id', $user->id)
            ->orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();

        // Popular public templates
        $publicTemplates = AlertTemplate::where('is_public', true)
            ->where('user_id', '!=', $user->id)
            ->orderBy('usage_count', 'desc')
            ->limit(5)
            ->get();

        return [
            'system' => $systemTemplates,
            'user' => $userTemplates,
            'popular' => $publicTemplates,
        ];
    }

    /**
     * Remove asset-specific values from parameters
     */
    private function sanitizeParameters(array $parameters): array
    {
        // Remove values that are asset-specific
        unset($parameters['target_price']);
        unset($parameters['entry_price']);
        unset($parameters['zone_low']);
        unset($parameters['zone_high']);
        unset($parameters['level']);

        return $parameters;
    }
}
```

### System Templates Seeder

```php
<?php

namespace Database\Seeders;

use App\Models\AlertTemplate;
use Illuminate\Database\Seeder;

class AlertTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // Price alerts
            [
                'name' => 'Target Price Alert',
                'name_ar' => 'تنبيه السعر المستهدف',
                'description' => 'Alert when price reaches a specific target',
                'description_ar' => 'تنبيه عندما يصل السعر إلى هدف محدد',
                'type' => 'price',
                'trigger_type' => 'target_price',
                'default_parameters' => [
                    'direction' => 'above',
                    'auto_direction' => true,
                ],
            ],
            [
                'name' => 'Daily 5% Move',
                'name_ar' => 'تحرك 5% يومي',
                'description' => 'Alert when stock moves 5% in a day',
                'description_ar' => 'تنبيه عندما يتحرك السهم 5% في اليوم',
                'type' => 'price',
                'trigger_type' => 'daily_change',
                'default_parameters' => [
                    'threshold_percent' => 5.0,
                    'direction' => 'both',
                    'from_reference' => 'open',
                ],
            ],
            [
                'name' => '52-Week High Alert',
                'name_ar' => 'تنبيه أعلى 52 أسبوع',
                'description' => 'Alert when stock makes new 52-week high',
                'description_ar' => 'تنبيه عندما يسجل السهم أعلى مستوى في 52 أسبوع',
                'type' => 'price',
                'trigger_type' => '52week',
                'default_parameters' => [
                    'type' => 'high',
                    'cooldown_hours' => 24,
                ],
            ],

            // Intelligence alerts
            [
                'name' => 'RSI Oversold',
                'name_ar' => 'RSI في منطقة البيع المفرط',
                'description' => 'Alert when RSI indicates oversold conditions',
                'description_ar' => 'تنبيه عندما يشير RSI إلى ظروف بيع مفرط',
                'type' => 'signal',
                'trigger_type' => 'signal',
                'default_parameters' => [
                    'indicators' => ['RSI'],
                    'signal_types' => ['oversold'],
                    'min_strength' => 0.7,
                    'any_or_all' => 'any',
                ],
            ],
            [
                'name' => 'Strong Buy Recommendation',
                'name_ar' => 'توصية شراء قوية',
                'description' => 'Alert when stock receives strong buy recommendation',
                'description_ar' => 'تنبيه عندما يحصل السهم على توصية شراء قوية',
                'type' => 'recommendation',
                'trigger_type' => 'recommendation',
                'default_parameters' => [
                    'trigger_on' => 'change',
                    'recommendations' => ['strong_buy'],
                    'min_score' => 0.8,
                ],
            ],
            [
                'name' => 'Anomaly Detection',
                'name_ar' => 'اكتشاف الشذوذ',
                'description' => 'Alert when unusual price or volume activity detected',
                'description_ar' => 'تنبيه عند اكتشاف نشاط غير عادي في السعر أو الحجم',
                'type' => 'anomaly',
                'trigger_type' => 'anomaly',
                'default_parameters' => [
                    'anomaly_types' => ['price_spike', 'volume_surge'],
                    'min_confidence' => 0.8,
                    'severity' => ['high', 'critical'],
                ],
            ],
            [
                'name' => 'Bullish Pattern',
                'name_ar' => 'نمط صعودي',
                'description' => 'Alert when bullish chart pattern confirmed',
                'description_ar' => 'تنبيه عند تأكيد نمط صعودي على الرسم البياني',
                'type' => 'pattern',
                'trigger_type' => 'pattern',
                'default_parameters' => [
                    'patterns' => ['double_bottom', 'inverse_head_shoulders', 'ascending_triangle'],
                    'pattern_status' => 'confirmed',
                    'min_confidence' => 0.7,
                    'direction_bias' => 'bullish',
                ],
            ],
        ];

        foreach ($templates as $template) {
            AlertTemplate::firstOrCreate(
                ['name' => $template['name'], 'user_id' => null],
                array_merge($template, [
                    'is_public' => false,
                    'usage_count' => 0,
                ])
            );
        }
    }
}
```

---

## Smart Defaults

### SmartDefaultsService

```php
<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;

class SmartDefaultsService
{
    /**
     * Suggest alert parameters based on user profile and asset
     */
    public function suggestParameters(User $user, Asset $asset, string $triggerType): array
    {
        $suggestions = [];

        switch ($triggerType) {
            case 'target_price':
                $suggestions = $this->suggestTargetPrice($user, $asset);
                break;

            case 'daily_change':
                $suggestions = $this->suggestDailyChange($asset);
                break;

            case 'zone':
                $suggestions = $this->suggestZone($asset);
                break;

            case 'signal':
                $suggestions = $this->suggestSignalParameters($user, $asset);
                break;
        }

        return $suggestions;
    }

    private function suggestTargetPrice(User $user, Asset $asset): array
    {
        $currentPrice = $asset->last_price;
        $holding = $user->portfolioHoldings()
            ->where('asset_id', $asset->id)
            ->first();

        // If user owns the stock, suggest based on entry price
        if ($holding) {
            $entryPrice = $holding->average_cost;
            $profitTarget = $entryPrice * 1.10; // 10% profit
            $stopLoss = $entryPrice * 0.95; // 5% stop loss

            return [
                'suggested_targets' => [
                    [
                        'label' => '10% Profit Target',
                        'label_ar' => 'هدف ربح 10%',
                        'value' => round($profitTarget, 2),
                        'direction' => 'above',
                    ],
                    [
                        'label' => '5% Stop Loss',
                        'label_ar' => 'وقف خسارة 5%',
                        'value' => round($stopLoss, 2),
                        'direction' => 'below',
                    ],
                ],
                'entry_price' => $entryPrice,
            ];
        }

        // For watchlist stocks, suggest based on technical levels
        $recentHigh = $this->getRecentHigh($asset, 20);
        $recentLow = $this->getRecentLow($asset, 20);

        return [
            'suggested_targets' => [
                [
                    'label' => '20-Day High Breakout',
                    'label_ar' => 'اختراق أعلى 20 يوم',
                    'value' => round($recentHigh, 2),
                    'direction' => 'above',
                ],
                [
                    'label' => '20-Day Low Breakdown',
                    'label_ar' => 'كسر أدنى 20 يوم',
                    'value' => round($recentLow, 2),
                    'direction' => 'below',
                ],
            ],
        ];
    }

    private function suggestDailyChange(Asset $asset): array
    {
        // Calculate average daily volatility
        $avgVolatility = $this->calculateAverageVolatility($asset, 20);

        // Suggest threshold based on volatility
        $suggestedThreshold = max(round($avgVolatility * 1.5, 1), 2.0);

        return [
            'average_volatility' => round($avgVolatility, 2),
            'suggested_threshold' => $suggestedThreshold,
            'recommendation' => $avgVolatility > 3
                ? 'This stock is highly volatile. Consider a higher threshold.'
                : 'This stock has moderate volatility.',
        ];
    }

    private function suggestZone(Asset $asset): array
    {
        $currentPrice = $asset->last_price;

        // Get support/resistance from technical data
        $technicals = $this->getTechnicalLevels($asset);

        return [
            'suggested_zones' => [
                [
                    'label' => 'Nearest Support Zone',
                    'zone_low' => $technicals['support_1'] * 0.99,
                    'zone_high' => $technicals['support_1'] * 1.01,
                ],
                [
                    'label' => 'Nearest Resistance Zone',
                    'zone_low' => $technicals['resistance_1'] * 0.99,
                    'zone_high' => $technicals['resistance_1'] * 1.01,
                ],
            ],
            'pivot_point' => $technicals['pivot'],
        ];
    }

    private function suggestSignalParameters(User $user, Asset $asset): array
    {
        // Get user's successful alert history
        $successfulSignals = Alert::where('user_id', $user->id)
            ->where('type', 'signal')
            ->where('triggered_count', '>', 0)
            ->get();

        // Find most commonly used indicators
        $indicatorCounts = [];
        foreach ($successfulSignals as $alert) {
            $indicators = $alert->parameters['indicators'] ?? [];
            foreach ($indicators as $indicator) {
                $indicatorCounts[$indicator] = ($indicatorCounts[$indicator] ?? 0) + 1;
            }
        }

        arsort($indicatorCounts);
        $topIndicators = array_slice(array_keys($indicatorCounts), 0, 3);

        if (empty($topIndicators)) {
            $topIndicators = ['RSI', 'MACD'];
        }

        return [
            'suggested_indicators' => $topIndicators,
            'recommended_min_strength' => 0.7,
            'note' => 'Based on your alert history',
        ];
    }

    private function getRecentHigh(Asset $asset, int $days): float
    {
        return $asset->prices()
            ->where('date', '>=', now()->subDays($days))
            ->max('high') ?? $asset->last_price;
    }

    private function getRecentLow(Asset $asset, int $days): float
    {
        return $asset->prices()
            ->where('date', '>=', now()->subDays($days))
            ->min('low') ?? $asset->last_price;
    }

    private function calculateAverageVolatility(Asset $asset, int $days): float
    {
        $prices = $asset->prices()
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date')
            ->get();

        if ($prices->count() < 2) {
            return 2.0; // Default
        }

        $dailyChanges = [];
        for ($i = 1; $i < $prices->count(); $i++) {
            $change = abs(($prices[$i]->close - $prices[$i - 1]->close) / $prices[$i - 1]->close * 100);
            $dailyChanges[] = $change;
        }

        return array_sum($dailyChanges) / count($dailyChanges);
    }

    private function getTechnicalLevels(Asset $asset): array
    {
        // Simplified pivot point calculation
        $latestPrice = $asset->prices()->latest('date')->first();

        if (!$latestPrice) {
            return [
                'pivot' => $asset->last_price,
                'support_1' => $asset->last_price * 0.98,
                'resistance_1' => $asset->last_price * 1.02,
            ];
        }

        $pivot = ($latestPrice->high + $latestPrice->low + $latestPrice->close) / 3;
        $support1 = 2 * $pivot - $latestPrice->high;
        $resistance1 = 2 * $pivot - $latestPrice->low;

        return [
            'pivot' => round($pivot, 2),
            'support_1' => round($support1, 2),
            'resistance_1' => round($resistance1, 2),
        ];
    }
}
```

---

## Proximity Alerts

### ProximityAlertHandler

```php
<?php

namespace App\Services\Alerts;

use App\Models\Alert;
use App\Jobs\Alerts\SendAlertNotification;
use Illuminate\Support\Facades\Cache;

class ProximityAlertHandler
{
    /**
     * Check if price is approaching a target alert
     */
    public function checkProximity(Alert $targetAlert, float $currentPrice): void
    {
        $targetPrice = $targetAlert->parameters['target_price'] ?? null;

        if (!$targetPrice) {
            return;
        }

        $proximityPercent = $targetAlert->parameters['proximity_percent'] ?? 2.0;
        $proximityRange = $targetPrice * ($proximityPercent / 100);

        $lowerBound = $targetPrice - $proximityRange;
        $upperBound = $targetPrice + $proximityRange;

        // Check if price entered proximity zone
        $inProximity = $currentPrice >= $lowerBound && $currentPrice <= $upperBound;

        if (!$inProximity) {
            return;
        }

        // Check if we already notified for this approach
        $cacheKey = "proximity_alert:{$targetAlert->id}";
        if (Cache::has($cacheKey)) {
            return;
        }

        // Send proximity notification
        $this->sendProximityNotification($targetAlert, $currentPrice, $targetPrice);

        // Cache to prevent repeated notifications
        // Reset cache if price moves away significantly (5%+)
        Cache::put($cacheKey, true, now()->addHours(4));
    }

    private function sendProximityNotification(Alert $alert, float $currentPrice, float $targetPrice): void
    {
        $distance = abs($currentPrice - $targetPrice);
        $distancePercent = ($distance / $targetPrice) * 100;

        // Create a special proximity history entry
        $history = $alert->history()->create([
            'user_id' => $alert->user_id,
            'asset_id' => $alert->asset_id,
            'triggered_at' => now(),
            'trigger_value' => $currentPrice,
            'trigger_context' => [
                'type' => 'proximity',
                'target_price' => $targetPrice,
                'distance_percent' => round($distancePercent, 2),
            ],
        ]);

        // Dispatch notification with proximity flag
        SendAlertNotification::dispatch($alert, $history, [
            'is_proximity' => true,
            'current_price' => $currentPrice,
            'target_price' => $targetPrice,
            'distance_percent' => round($distancePercent, 2),
        ]);
    }
}
```

---

## Rate Limiting with Token Bucket

### TokenBucketRateLimiter

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class TokenBucketRateLimiter
{
    private const KEY_PREFIX = 'rate_limit:';

    public function __construct(
        private readonly int $capacity,
        private readonly int $refillRate, // tokens per second
    ) {}

    /**
     * Attempt to consume tokens
     */
    public function attempt(string $key, int $tokens = 1): bool
    {
        $fullKey = self::KEY_PREFIX . $key;
        $now = microtime(true);

        // Lua script for atomic token bucket operation
        $script = <<<'LUA'
            local key = KEYS[1]
            local capacity = tonumber(ARGV[1])
            local refill_rate = tonumber(ARGV[2])
            local now = tonumber(ARGV[3])
            local tokens_needed = tonumber(ARGV[4])

            local bucket = redis.call('HMGET', key, 'tokens', 'last_update')
            local current_tokens = tonumber(bucket[1]) or capacity
            local last_update = tonumber(bucket[2]) or now

            -- Calculate tokens to add based on time elapsed
            local elapsed = now - last_update
            local tokens_to_add = elapsed * refill_rate
            current_tokens = math.min(capacity, current_tokens + tokens_to_add)

            -- Check if enough tokens
            if current_tokens >= tokens_needed then
                current_tokens = current_tokens - tokens_needed
                redis.call('HMSET', key, 'tokens', current_tokens, 'last_update', now)
                redis.call('EXPIRE', key, 86400)
                return 1
            else
                redis.call('HMSET', key, 'tokens', current_tokens, 'last_update', now)
                redis.call('EXPIRE', key, 86400)
                return 0
            end
        LUA;

        $result = Redis::eval(
            $script,
            1, // number of keys
            $fullKey,
            $this->capacity,
            $this->refillRate,
            $now,
            $tokens
        );

        return (bool) $result;
    }

    /**
     * Get remaining tokens
     */
    public function remaining(string $key): int
    {
        $fullKey = self::KEY_PREFIX . $key;
        $tokens = Redis::hget($fullKey, 'tokens');

        return $tokens !== null ? (int) $tokens : $this->capacity;
    }

    /**
     * Reset the bucket
     */
    public function reset(string $key): void
    {
        $fullKey = self::KEY_PREFIX . $key;
        Redis::del($fullKey);
    }
}
```

### Usage in Notification Service

```php
// In SendAlertNotification job

public function handle(): void
{
    $limiter = new TokenBucketRateLimiter(
        capacity: 10,      // Max 10 tokens
        refillRate: 0.167  // ~10 per hour (10/60 per minute)
    );

    $key = "user:{$this->alert->user_id}:notifications";

    if (!$limiter->attempt($key)) {
        // Rate limited - schedule for later or skip
        Log::info('Notification rate limited', [
            'user_id' => $this->alert->user_id,
            'remaining' => $limiter->remaining($key),
        ]);

        // Reschedule for 5 minutes later
        $this->release(300);
        return;
    }

    // Continue with notification...
}
```

---

## Testing Advanced Features

### Compound Alert Test

```php
<?php

namespace Tests\Feature\Alerts;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use App\Services\Alerts\Evaluators\CompoundAlertEvaluator;
use Tests\TestCase;

class CompoundAlertTest extends TestCase
{
    /** @test */
    public function compound_alert_triggers_when_all_and_conditions_met(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'type' => 'signal',
            'trigger_type' => 'compound_intelligence',
            'condition_logic' => 'and',
            'parameters' => [
                'conditions' => [
                    ['type' => 'signal', 'indicators' => ['RSI'], 'min_strength' => 0.7],
                    ['type' => 'prediction', 'direction' => 'up', 'min_confidence' => 0.7],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'signals' => [
                ['indicator' => 'RSI', 'strength' => 0.8, 'signal_type' => 'oversold'],
            ],
            'predictions' => [
                ['direction' => 'up', 'confidence' => 0.85],
            ],
        ]);

        $this->assertTrue($result->triggered);
    }

    /** @test */
    public function compound_alert_does_not_trigger_when_and_condition_fails(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create([
            'user_id' => $user->id,
            'condition_logic' => 'and',
            'parameters' => [
                'conditions' => [
                    ['type' => 'signal', 'indicators' => ['RSI'], 'min_strength' => 0.7],
                    ['type' => 'prediction', 'direction' => 'up', 'min_confidence' => 0.7],
                ],
            ],
        ]);

        $evaluator = app(CompoundAlertEvaluator::class);

        $result = $evaluator->evaluate($alert, [
            'signals' => [
                ['indicator' => 'RSI', 'strength' => 0.8],
            ],
            'predictions' => [
                ['direction' => 'down', 'confidence' => 0.85], // Wrong direction
            ],
        ]);

        $this->assertFalse($result->triggered);
    }
}
```

---

## Next Task

Proceed to [Task 09: Operations & Monitoring](./09-operations.md)
