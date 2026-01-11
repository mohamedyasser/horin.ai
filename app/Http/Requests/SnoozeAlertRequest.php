<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SnoozeAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080', 'required_without:preset'],
            'preset' => [
                'nullable',
                'required_without:duration_minutes',
                Rule::in(['1h', '4h', '1d', 'until_market_close', 'until_market_open']),
            ],
        ];
    }
}
