<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingRequest extends FormRequest
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
        $step = $this->input('step', 1);

        return match ((int) $step) {
            1 => [
                'step' => ['required', 'integer', 'in:1'],
                'experience_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
                'risk_level' => ['required', Rule::in(['conservative', 'moderate', 'aggressive'])],
            ],
            2 => [
                'step' => ['required', 'integer', 'in:2'],
                'investment_goal' => ['required', Rule::in([
                    'capital_growth', 'fixed_income', 'risk_reduction', 'short_term_speculation',
                    'retirement_planning', 'wealth_preservation', 'passive_income',
                    'education_savings', 'home_purchase', 'emergency_fund',
                ])],
                'trading_style' => ['required', Rule::in(['day_trading', 'swing_trading', 'position_trading', 'scalping_trading'])],
            ],
            3 => [
                'step' => ['required', 'integer', 'in:3'],
                'country_id' => ['required', 'uuid', 'exists:countries,id'],
                'markets' => ['required', 'array', 'min:1'],
                'markets.*' => ['uuid', 'exists:markets,id'],
            ],
            4 => [
                'step' => ['required', 'integer', 'in:4'],
                'sectors' => ['required', 'array', 'min:1'],
                'sectors.*' => ['uuid', 'exists:sectors,id'],
            ],
            default => [],
        };
    }
}
