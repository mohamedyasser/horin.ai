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
    }
}
