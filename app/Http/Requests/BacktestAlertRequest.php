<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BacktestAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lookback_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'include_ml_signals' => ['nullable', 'boolean'],
        ];
    }
}
