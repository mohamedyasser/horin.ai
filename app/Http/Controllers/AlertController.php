<?php

namespace App\Http\Controllers;

use App\Http\Requests\BacktestAlertRequest;
use App\Http\Requests\SnoozeAlertRequest;
use App\Http\Requests\StoreAlertRequest;
use App\Http\Requests\UpdateAlertRequest;
use App\Http\Resources\AlertResource;
use App\Jobs\Alerts\RunAlertBacktest;
use App\Models\Alert;
use App\Models\AlertBacktestResult;
use App\Models\AlertTemplate;
use App\Models\Asset;
use App\Models\Market;
use App\Models\Sector;
use App\Services\AlertCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function __construct(
        private readonly AlertCacheService $cacheService
    ) {}

    /**
     * Display a listing of alerts.
     */
    public function index(Request $request): Response
    {
        $alerts = Alert::where('user_id', $request->user()->id)
            ->with(['asset:id,symbol,name,name_ar,last_price'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('asset_id'), fn ($q) => $q->where('asset_id', $request->asset_id))
            ->latest()
            ->paginate(20);

        return Inertia::render('Alerts/Index', [
            'alerts' => AlertResource::collection($alerts),
            'filters' => $request->only(['type', 'status', 'asset_id']),
            'stats' => [
                'active' => Alert::where('user_id', $request->user()->id)->where('status', 'active')->count(),
                'triggered_today' => Alert::where('user_id', $request->user()->id)
                    ->where('status', 'triggered')
                    ->whereDate('last_triggered_at', today())
                    ->count(),
                'total' => Alert::where('user_id', $request->user()->id)->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new alert.
     */
    public function create(Request $request): Response
    {
        $asset = $request->filled('asset_id')
            ? Asset::find($request->asset_id)
            : null;

        return Inertia::render('Alerts/Create', [
            'asset' => $asset ? [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'name_ar' => $asset->name_ar,
                'last_price' => $asset->last_price,
            ] : null,
            'templates' => AlertTemplate::where(function ($q) use ($request) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $request->user()->id);
            })->get(),
            'userAssets' => $this->getUserAssets($request->user()),
            'alertTypes' => $this->getAlertTypeOptions(),
            'markets' => Market::select('id', 'name_en', 'name_ar')->get()->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'name_ar' => $m->name_ar,
            ]),
            'sectors' => Sector::select('id', 'name_en', 'name_ar')->get()->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'name_ar' => $s->name_ar,
            ]),
        ]);
    }

    /**
     * Store a newly created alert.
     */
    public function store(StoreAlertRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        if (! isset($data['priority'])) {
            $data['priority'] = $this->inferPriority($data);
        }

        $alert = Alert::create($data);

        $this->cacheService->invalidateAsset($alert->asset_id);

        return redirect()
            ->route('alerts.index')
            ->with('success', __('alerts.created'));
    }

    /**
     * Display the specified alert.
     */
    public function show(Alert $alert): Response
    {
        Gate::authorize('view', $alert);

        return Inertia::render('Alerts/Show', [
            'alert' => new AlertResource($alert->load(['asset', 'template', 'history' => fn ($q) => $q->latest()->limit(10)])),
            'backtestResult' => AlertBacktestResult::where('alert_id', $alert->id)->latest()->first(),
        ]);
    }

    /**
     * Show the form for editing the specified alert.
     */
    public function edit(Alert $alert): Response
    {
        Gate::authorize('update', $alert);

        return Inertia::render('Alerts/Edit', [
            'alert' => new AlertResource($alert->load('asset')),
            'templates' => AlertTemplate::where(function ($q) use ($alert) {
                $q->whereNull('user_id')
                    ->orWhere('user_id', $alert->user_id);
            })->get(),
        ]);
    }

    /**
     * Update the specified alert.
     */
    public function update(UpdateAlertRequest $request, Alert $alert): RedirectResponse
    {
        Gate::authorize('update', $alert);

        $oldAssetId = $alert->asset_id;
        $alert->update($request->validated());

        $this->cacheService->invalidateAsset($oldAssetId);
        if ($alert->asset_id !== $oldAssetId) {
            $this->cacheService->invalidateAsset($alert->asset_id);
        }

        return redirect()
            ->route('alerts.index')
            ->with('success', __('alerts.updated'));
    }

    /**
     * Remove the specified alert.
     */
    public function destroy(Alert $alert): RedirectResponse
    {
        Gate::authorize('delete', $alert);

        $assetId = $alert->asset_id;
        $alert->delete();

        $this->cacheService->invalidateAsset($assetId);

        return redirect()
            ->route('alerts.index')
            ->with('success', __('alerts.deleted'));
    }

    /**
     * Snooze an alert.
     */
    public function snooze(SnoozeAlertRequest $request, Alert $alert): RedirectResponse
    {
        Gate::authorize('update', $alert);

        $snoozedUntil = $this->resolveSnoozeTime($request);

        $alert->update([
            'snoozed_until' => $snoozedUntil,
        ]);

        $this->cacheService->invalidateAsset($alert->asset_id);

        return back()->with('success', __('alerts.snoozed', ['until' => $snoozedUntil->format('M d, H:i')]));
    }

    /**
     * Unsnooze an alert.
     */
    public function unsnooze(Alert $alert): RedirectResponse
    {
        Gate::authorize('update', $alert);

        $alert->update(['snoozed_until' => null]);

        $this->cacheService->invalidateAsset($alert->asset_id);

        return back()->with('success', __('alerts.unsnoozed'));
    }

    /**
     * Request alert backtest.
     */
    public function backtest(BacktestAlertRequest $request, Alert $alert): RedirectResponse
    {
        Gate::authorize('view', $alert);

        RunAlertBacktest::dispatch(
            $alert,
            $request->validated('lookback_days', 90),
            $request->validated('include_ml_signals', false)
        );

        return back()->with('success', __('alerts.backtest_started'));
    }

    /**
     * Get backtest results.
     */
    public function backtestResults(Alert $alert): JsonResponse
    {
        Gate::authorize('view', $alert);

        $result = AlertBacktestResult::where('alert_id', $alert->id)
            ->latest()
            ->first();

        if (! $result) {
            return response()->json(['status' => 'pending']);
        }

        return response()->json([
            'status' => 'completed',
            'result' => $result,
        ]);
    }

    /**
     * Duplicate an alert.
     */
    public function duplicate(Alert $alert): RedirectResponse
    {
        Gate::authorize('view', $alert);

        $newAlert = $alert->replicate([
            'status',
            'triggered_count',
            'last_triggered_at',
            'snoozed_until',
        ]);

        $newAlert->status = 'active';
        $newAlert->triggered_count = 0;
        $newAlert->save();

        return redirect()
            ->route('alerts.edit', $newAlert)
            ->with('success', __('alerts.duplicated'));
    }

    /**
     * Get active alert count (API).
     */
    public function activeCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => Alert::where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->count(),
        ]);
    }

    /**
     * Search assets for alert creation.
     */
    public function searchAssets(Request $request): JsonResponse
    {
        $query = Asset::query()
            ->select('id', 'symbol', 'name_en', 'name_ar', 'market_id', 'sector_id');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('symbol', 'ilike', "%{$search}%")
                    ->orWhere('name_en', 'ilike', "%{$search}%")
                    ->orWhere('name_ar', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('market_id')) {
            $query->where('market_id', $request->market_id);
        }

        if ($request->filled('sector_id')) {
            $query->where('sector_id', $request->sector_id);
        }

        $assets = $query->orderBy('symbol')
            ->limit(50)
            ->get()
            ->map(fn ($asset) => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name_en,
                'name_ar' => $asset->name_ar,
            ]);

        return response()->json(['assets' => $assets]);
    }

    private function resolveSnoozeTime(SnoozeAlertRequest $request): \DateTime
    {
        $timezone = new \DateTimeZone('Africa/Cairo');

        if ($request->filled('duration_minutes')) {
            return now()->addMinutes($request->duration_minutes);
        }

        return match ($request->preset) {
            '1h' => now()->addHour(),
            '4h' => now()->addHours(4),
            '1d' => now()->addDay(),
            'until_market_close' => $this->getNextMarketClose($timezone),
            'until_market_open' => $this->getNextMarketOpen($timezone),
            default => now()->addHour(),
        };
    }

    private function getNextMarketClose(\DateTimeZone $timezone): \DateTime
    {
        $now = now()->setTimezone($timezone);
        $close = $now->copy()->setTime(14, 30);

        if ($now > $close || in_array($now->dayOfWeek, [5, 6])) {
            $close = $now->copy()->nextWeekday()->setTime(14, 30);
            while (in_array($close->dayOfWeek, [5, 6])) {
                $close->addDay();
            }
        }

        return $close;
    }

    private function getNextMarketOpen(\DateTimeZone $timezone): \DateTime
    {
        $now = now()->setTimezone($timezone);
        $open = $now->copy()->addDay()->setTime(10, 0);

        while (in_array($open->dayOfWeek, [5, 6])) {
            $open->addDay();
        }

        return $open;
    }

    private function getAlertTypeOptions(): array
    {
        return [
            'price' => [
                'label' => __('alerts.types.price'),
                'triggers' => [
                    'target_price' => __('alerts.triggers.target_price'),
                    'breakout' => __('alerts.triggers.breakout'),
                    'zone' => __('alerts.triggers.zone'),
                    'gap' => __('alerts.triggers.gap'),
                    '52week' => __('alerts.triggers.52week'),
                    'daily_change' => __('alerts.triggers.daily_change'),
                    'entry_return' => __('alerts.triggers.entry_return'),
                ],
            ],
            'prediction' => [
                'label' => __('alerts.types.prediction'),
                'triggers' => [
                    'prediction' => __('alerts.triggers.prediction'),
                ],
            ],
            'signal' => [
                'label' => __('alerts.types.signal'),
                'triggers' => [
                    'signal' => __('alerts.triggers.signal'),
                ],
            ],
            'anomaly' => [
                'label' => __('alerts.types.anomaly'),
                'triggers' => [
                    'anomaly' => __('alerts.triggers.anomaly'),
                ],
            ],
            'pattern' => [
                'label' => __('alerts.types.pattern'),
                'triggers' => [
                    'pattern' => __('alerts.triggers.pattern'),
                ],
            ],
            'recommendation' => [
                'label' => __('alerts.types.recommendation'),
                'triggers' => [
                    'recommendation' => __('alerts.triggers.recommendation'),
                ],
            ],
        ];
    }

    private function inferPriority(array $data): string
    {
        $type = $data['type'];
        $triggerType = $data['trigger_type'];

        if ($type === 'anomaly') {
            return 'high';
        }

        if ($triggerType === 'target_price') {
            $targetPrice = $data['parameters']['target_price'] ?? 0;
            $currentPrice = Asset::find($data['asset_id'])?->last_price ?? 0;

            if ($currentPrice > 0) {
                $percentDiff = abs(($targetPrice - $currentPrice) / $currentPrice) * 100;
                if ($percentDiff <= 2) {
                    return 'high';
                }
            }
        }

        return 'medium';
    }

    /**
     * Get user assets from wishlists, handling missing table gracefully.
     */
    private function getUserAssets($user): \Illuminate\Support\Collection
    {
        try {
            return $user->userWishlists()->with('asset:id,symbol,name,name_ar')->get();
        } catch (\Illuminate\Database\QueryException) {
            return collect();
        }
    }
}
