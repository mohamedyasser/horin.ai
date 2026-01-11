<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'type' => ['sometimes', Rule::in(['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation'])],
            'trigger_type' => ['sometimes', 'string', 'max:50'],
            'scope' => ['sometimes', Rule::in(['single_asset', 'watchlist', 'portfolio', 'sector', 'market'])],
            'direction' => ['nullable', Rule::in(['above', 'below', 'both', 'cross_up', 'cross_down'])],
            'condition_logic' => ['sometimes', Rule::in(['single', 'and', 'or'])],
            'parameters' => ['sometimes', 'array'],
            'status' => ['sometimes', Rule::in(['active', 'paused'])],
            'priority' => ['sometimes', Rule::in(['critical', 'high', 'medium', 'low'])],
            'is_recurring' => ['sometimes', 'boolean'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'max_triggers' => ['nullable', 'integer', 'min:1', 'max:100'],
            'delivery_config' => ['nullable', 'array'],
            'escalation_config' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'market_hours_only' => ['sometimes', 'boolean'],
        ];
    }
}
