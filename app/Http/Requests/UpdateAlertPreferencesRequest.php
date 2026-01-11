<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_channels' => ['nullable', 'array'],
            'default_channels.*' => [Rule::in(['telegram', 'push', 'email', 'in_app'])],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_start'],
            'timezone' => ['nullable', 'timezone'],
            'max_alerts_per_hour' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_alerts_per_day' => ['nullable', 'integer', 'min:1', 'max:500'],
            'digest_enabled' => ['nullable', 'boolean'],
            'digest_time' => ['nullable', 'date_format:H:i'],
            'smart_defaults_enabled' => ['nullable', 'boolean'],
        ];
    }
}
