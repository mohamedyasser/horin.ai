<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class MarketPreferencesUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'country_id' => ['sometimes', 'uuid', 'exists:countries,id'],
            'markets' => ['sometimes', 'array', 'min:1'],
            'markets.*' => ['uuid', 'exists:markets,id'],
            'sectors' => ['sometimes', 'array', 'min:1'],
            'sectors.*' => ['uuid', 'exists:sectors,id'],
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'markets.min' => 'Please select at least one market.',
            'sectors.min' => 'Please select at least one sector.',
        ];
    }
}
