<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MarketPreferencesUpdateRequest;
use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class MarketPreferencesController extends Controller
{
    /**
     * Show the market preferences settings page.
     */
    public function edit(Request $request): Response
    {
        $locale = App::getLocale();
        $nameColumn = $locale === 'ar' ? 'name_ar' : 'name_en';

        return Inertia::render('settings/MarketPreferences', [
            'countries' => Country::query()
                ->select(['id', 'code', "{$nameColumn} as name"])
                ->orderBy($nameColumn)
                ->get(),
            'markets' => Market::query()
                ->select(['id', 'code', "{$nameColumn} as name"])
                ->orderBy($nameColumn)
                ->get(),
            'sectors' => Sector::query()
                ->select(['id', "{$nameColumn} as name"])
                ->orderBy($nameColumn)
                ->get(),
            'user' => [
                'country_id' => $request->user()->country_id,
                'markets' => $request->user()->markets->pluck('id')->toArray(),
                'sectors' => $request->user()->sectors->pluck('id')->toArray(),
            ],
        ]);
    }

    /**
     * Update the user's market preferences.
     */
    public function update(MarketPreferencesUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (isset($validated['country_id'])) {
            $user->country_id = $validated['country_id'];
            $user->save();
        }

        if (isset($validated['markets'])) {
            $user->markets()->sync($validated['markets']);
        }

        if (isset($validated['sectors'])) {
            $user->sectors()->sync($validated['sectors']);
        }

        return back();
    }
}
