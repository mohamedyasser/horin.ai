<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAlertPreferencesRequest;
use App\Models\UserAlertPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertPreferencesController extends Controller
{
    /**
     * Show the preferences form.
     */
    public function edit(Request $request): Response
    {
        $preferences = UserAlertPreference::where('user_id', $request->user()->id)->first();

        if (! $preferences) {
            $preferences = new UserAlertPreference;
            $preferences->user_id = $request->user()->id;
            $preferences->fill(UserAlertPreference::getDefaults());
            $preferences->save();
        }

        return Inertia::render('settings/Alerts', [
            'preferences' => $preferences,
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::AFRICA),
            'channels' => [
                ['id' => 'telegram', 'name' => 'Telegram', 'available' => (bool) $request->user()->telegram_id],
                ['id' => 'push', 'name' => 'Push Notifications', 'available' => (bool) $request->user()->push_token],
                ['id' => 'email', 'name' => 'Email', 'available' => true],
                ['id' => 'in_app', 'name' => 'In-App', 'available' => true],
            ],
        ]);
    }

    /**
     * Update preferences.
     */
    public function update(UpdateAlertPreferencesRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        \Log::info('Alert preferences update', [
            'user_id' => $request->user()->id,
            'validated' => $validated,
        ]);

        $preference = UserAlertPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );

        \Log::info('Alert preferences saved', [
            'wasRecentlyCreated' => $preference->wasRecentlyCreated,
            'wasChanged' => $preference->wasChanged(),
            'preference' => $preference->toArray(),
        ]);

        return back()->with('success', __('settings.alerts_updated'));
    }
}
