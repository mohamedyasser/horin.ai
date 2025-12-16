<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\TradingProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TradingProfileController extends Controller
{
    /**
     * Show the trading profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/TradingProfile', [
            'user' => [
                'experience_level' => $request->user()->experience_level,
                'risk_level' => $request->user()->risk_level,
                'investment_goal' => $request->user()->investment_goal,
                'trading_style' => $request->user()->trading_style,
            ],
        ]);
    }

    /**
     * Update the user's trading profile.
     */
    public function update(TradingProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
