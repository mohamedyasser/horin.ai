<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TradingProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'experience_level' => ['sometimes', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'risk_level' => ['sometimes', 'string', Rule::in(['conservative', 'moderate', 'aggressive'])],
            'investment_goal' => ['sometimes', 'string', Rule::in(['capital_growth', 'fixed_income', 'risk_reduction', 'short_term_speculation', 'retirement_planning', 'wealth_preservation', 'passive_income', 'education_savings', 'home_purchase', 'emergency_fund'])],
            'trading_style' => ['sometimes', 'string', Rule::in(['day_trading', 'swing_trading', 'position_trading', 'scalping_trading'])],
        ];
    }
}
