<?php

namespace App\Http\Controllers;

use App\Http\Requests\OnboardingRequest;
use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Display the onboarding wizard.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        $step = (int) $request->query('step', 1);
        $step = max(1, min(4, $step));

        return Inertia::render('Onboarding', [
            'step' => $step,
            'totalSteps' => 4,
            'countries' => fn () => $step === 3 ? Country::orderBy('name')->get(['id', 'name', 'code']) : [],
            'markets' => fn () => $step === 3 ? Market::orderBy('name')->get(['id', 'name', 'code']) : [],
            'sectors' => fn () => $step === 4 ? Sector::orderBy('name')->get(['id', 'name']) : [],
            'user' => [
                'experience_level' => $request->user()->experience_level,
                'risk_level' => $request->user()->risk_level,
                'investment_goal' => $request->user()->investment_goal,
                'trading_style' => $request->user()->trading_style,
                'country_id' => $request->user()->country_id,
                'markets' => $request->user()->markets->pluck('id'),
                'sectors' => $request->user()->sectors->pluck('id'),
            ],
        ]);
    }

    /**
     * Store onboarding step data.
     */
    public function store(OnboardingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $step = (int) $request->input('step');

        match ($step) {
            1 => $user->update([
                'experience_level' => $request->experience_level,
                'risk_level' => $request->risk_level,
            ]),
            2 => $user->update([
                'investment_goal' => $request->investment_goal,
                'trading_style' => $request->trading_style,
            ]),
            3 => $this->saveStep3($user, $request),
            4 => $this->saveStep4($user, $request),
        };

        if ($step >= 4) {
            $user->markOnboardingAsComplete();

            return redirect()->route('dashboard')->with('status', 'onboarding-complete');
        }

        return redirect()->route('onboarding', ['step' => $step + 1]);
    }

    /**
     * Save step 3 data (country and markets).
     */
    private function saveStep3($user, OnboardingRequest $request): void
    {
        $user->update(['country_id' => $request->country_id]);
        $user->markets()->sync($request->markets);
    }

    /**
     * Save step 4 data (sectors).
     */
    private function saveStep4($user, OnboardingRequest $request): void
    {
        $user->sectors()->sync($request->sectors);
    }
}
