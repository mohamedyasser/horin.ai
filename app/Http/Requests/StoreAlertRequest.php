<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'template_id' => ['nullable', 'uuid', 'exists:alert_templates,id'],
            'type' => ['required', Rule::in(['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation'])],
            'trigger_type' => ['required', 'string', 'max:50'],
            'scope' => ['required', Rule::in(['single_asset', 'watchlist', 'portfolio', 'sector', 'market'])],
            'direction' => ['nullable', Rule::in(['above', 'below', 'both', 'cross_up', 'cross_down'])],
            'condition_logic' => ['required', Rule::in(['single', 'and', 'or'])],
            'parameters' => ['required', 'array'],
            'parameters.target_price' => ['required_if:trigger_type,target_price', 'numeric', 'min:0.01'],
            'parameters.threshold_percent' => ['required_if:trigger_type,daily_change', 'numeric', 'min:0.1', 'max:100'],
            'parameters.zone_low' => ['required_if:trigger_type,zone', 'numeric', 'min:0'],
            'parameters.zone_high' => ['required_if:trigger_type,zone', 'numeric', 'gt:parameters.zone_low'],
            'priority' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'is_recurring' => ['boolean'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'max_triggers' => ['nullable', 'integer', 'min:1', 'max:100'],
            'delivery_config' => ['nullable', 'array'],
            'delivery_config.channels' => ['nullable', 'array'],
            'delivery_config.channels.*' => [Rule::in(['telegram', 'push', 'email', 'in_app'])],
            'escalation_config' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'market_hours_only' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_id.required' => __('validation.alerts.asset_required'),
            'parameters.target_price.required_if' => __('validation.alerts.target_price_required'),
            'parameters.zone_high.gt' => __('validation.alerts.zone_high_must_be_greater'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->scope === 'single_asset' && ! $this->asset_id) {
            $this->merge(['asset_id' => null]);
        }

        // Ensure parameters has default values based on alert type
        $this->ensureDefaultParameters();
    }

    /**
     * Ensure default parameters are set for each alert type.
     */
    private function ensureDefaultParameters(): void
    {
        $type = $this->type;
        $triggerType = $this->trigger_type;
        $parameters = $this->parameters ?? [];

        // Set default parameters based on alert type
        $defaults = match ($type) {
            'prediction' => [
                'prediction_type' => $parameters['prediction_type'] ?? 'any',
                'min_confidence' => $parameters['min_confidence'] ?? 70,
                'horizon' => $parameters['horizon'] ?? 'short',
            ],
            'signal' => [
                'signal_types' => $parameters['signal_types'] ?? ['buy', 'sell'],
                'min_strength' => $parameters['min_strength'] ?? 60,
                'indicators' => $parameters['indicators'] ?? [],
            ],
            'anomaly' => [
                'anomaly_types' => $parameters['anomaly_types'] ?? ['volume', 'price', 'volatility'],
                'severity' => $parameters['severity'] ?? 'medium',
            ],
            'pattern' => [
                'patterns' => $parameters['patterns'] ?? [],
                'pattern_status' => $parameters['pattern_status'] ?? 'confirmed',
                'min_confidence' => $parameters['min_confidence'] ?? 70,
            ],
            'recommendation' => [
                'recommendations' => $parameters['recommendations'] ?? ['buy', 'sell', 'hold'],
                'trigger_on' => $parameters['trigger_on'] ?? 'any',
            ],
            'price' => match ($triggerType) {
                'target_price' => [
                    'target_price' => $parameters['target_price'] ?? 0,
                ],
                'zone' => [
                    'zone_low' => $parameters['zone_low'] ?? 0,
                    'zone_high' => $parameters['zone_high'] ?? 0,
                ],
                'daily_change' => [
                    'threshold_percent' => $parameters['threshold_percent'] ?? 5,
                ],
                'breakout' => [
                    'level' => $parameters['level'] ?? 0,
                    'tolerance_percent' => $parameters['tolerance_percent'] ?? 0.5,
                ],
                'gap' => [
                    'gap_threshold' => $parameters['gap_threshold'] ?? 2,
                    'from_reference' => $parameters['from_reference'] ?? 'previous_close',
                ],
                '52week' => [
                    '52week_type' => $parameters['52week_type'] ?? 'both',
                ],
                'entry_return' => [
                    'entry_price' => $parameters['entry_price'] ?? 0,
                    'threshold_percent' => $parameters['threshold_percent'] ?? 5,
                ],
                default => $parameters,
            },
            default => $parameters,
        };

        // Merge defaults with provided parameters, keeping user values
        $this->merge([
            'parameters' => array_merge($defaults, $parameters),
        ]);
    }
}
