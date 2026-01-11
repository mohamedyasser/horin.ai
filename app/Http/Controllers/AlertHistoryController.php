<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertHistoryResource;
use App\Models\AlertHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AlertHistoryController extends Controller
{
    /**
     * Display alert history.
     */
    public function index(Request $request): Response
    {
        $history = AlertHistory::where('user_id', $request->user()->id)
            ->with(['alert:id,type,trigger_type,parameters', 'asset:id,symbol,name,name_ar'])
            ->when($request->filled('alert_id'), fn ($q) => $q->where('alert_id', $request->alert_id))
            ->when($request->filled('asset_id'), fn ($q) => $q->where('asset_id', $request->asset_id))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('triggered_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('triggered_at', '<=', $request->date_to))
            ->latest('triggered_at')
            ->paginate(30);

        return Inertia::render('Alerts/History', [
            'history' => AlertHistoryResource::collection($history),
            'filters' => $request->only(['alert_id', 'asset_id', 'date_from', 'date_to']),
            'stats' => [
                'today' => AlertHistory::where('user_id', $request->user()->id)
                    ->whereDate('triggered_at', today())
                    ->count(),
                'this_week' => AlertHistory::where('user_id', $request->user()->id)
                    ->where('triggered_at', '>=', now()->startOfWeek())
                    ->count(),
                'unacknowledged' => AlertHistory::where('user_id', $request->user()->id)
                    ->whereNull('acknowledged_at')
                    ->count(),
            ],
        ]);
    }

    /**
     * Acknowledge an alert trigger.
     */
    public function acknowledge(AlertHistory $history): RedirectResponse
    {
        Gate::authorize('acknowledge', $history);

        $history->update([
            'acknowledged_at' => now(),
        ]);

        return back()->with('success', __('alerts.acknowledged'));
    }
}
