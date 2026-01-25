<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NewsFilterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'sentiment' => ['nullable', Rule::in(['positive', 'negative', 'neutral'])],
            'category' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(['buy', 'sell', 'watch'])],
            'market_id' => ['nullable', 'uuid', 'exists:markets,id'],
            'sector_id' => ['nullable', 'uuid', 'exists:sectors,id'],
            'country_id' => ['nullable', 'uuid', 'exists:countries,id'],
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'score_min' => ['nullable', 'integer', 'min:1', 'max:10'],
            'score_max' => ['nullable', 'integer', 'min:1', 'max:10', 'gte:score_min'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
