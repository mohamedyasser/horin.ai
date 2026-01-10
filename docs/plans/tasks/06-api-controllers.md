# Task 06: API & Controllers

**Priority:** P1
**Effort:** 2 days
**Dependencies:** Task 02

---

## Objective

Create all API endpoints and controllers for alert CRUD operations, history, preferences, and notifications.

---

## Checklist

- [ ] Create `AlertController` (CRUD + snooze + backtest)
- [ ] Create `AlertHistoryController`
- [ ] Create `AlertPreferencesController`
- [ ] Create `NotificationController`
- [ ] Create Form Requests for validation
- [ ] Create API Resources for responses
- [ ] Set up routes (web + API)
- [ ] Add controller tests

---

## Route Structure

### Web Routes (Inertia)

```php
// routes/web.php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AlertHistoryController;
use App\Http\Controllers\AlertPreferencesController;

Route::middleware(['auth'])->group(function () {
    // Alerts CRUD
    Route::resource('alerts', AlertController::class);
    Route::post('alerts/{alert}/snooze', [AlertController::class, 'snooze'])->name('alerts.snooze');
    Route::delete('alerts/{alert}/snooze', [AlertController::class, 'unsnooze'])->name('alerts.unsnooze');
    Route::post('alerts/{alert}/backtest', [AlertController::class, 'backtest'])->name('alerts.backtest');
    Route::get('alerts/{alert}/backtest/results', [AlertController::class, 'backtestResults'])->name('alerts.backtest.results');
    Route::post('alerts/{alert}/duplicate', [AlertController::class, 'duplicate'])->name('alerts.duplicate');

    // Alert History
    Route::get('alerts/history', [AlertHistoryController::class, 'index'])->name('alerts.history');
    Route::post('alerts/history/{history}/acknowledge', [AlertHistoryController::class, 'acknowledge'])->name('alerts.history.acknowledge');

    // Alert Preferences
    Route::get('settings/alerts', [AlertPreferencesController::class, 'edit'])->name('settings.alerts');
    Route::patch('settings/alerts', [AlertPreferencesController::class, 'update'])->name('settings.alerts.update');
});
```

### API Routes

```php
// routes/api.php

use App\Http\Controllers\Api\NotificationController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Quick alert check for mobile
    Route::get('alerts/active-count', [AlertController::class, 'activeCount']);
});
```

---

## AlertController

```bash
php artisan make:controller AlertController
```

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlertRequest;
use App\Http\Requests\UpdateAlertRequest;
use App\Http\Requests\SnoozeAlertRequest;
use App\Http\Requests\BacktestAlertRequest;
use App\Http\Resources\AlertResource;
use App\Jobs\Alerts\RunAlertBacktest;
use App\Models\Alert;
use App\Models\AlertBacktestResult;
use App\Models\AlertTemplate;
use App\Models\Asset;
use App\Services\AlertCacheService;
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
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('asset_id'), fn($q) => $q->where('asset_id', $request->asset_id))
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
        // Pre-fill asset if provided
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
                $q->whereNull('user_id') // System templates
                  ->orWhere('user_id', $request->user()->id); // User templates
            })->get(),
            'userAssets' => $request->user()->watchlist()->with('asset:id,symbol,name,name_ar')->get(),
            'alertTypes' => $this->getAlertTypeOptions(),
        ]);
    }

    /**
     * Store a newly created alert.
     */
    public function store(StoreAlertRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // Set smart defaults
        if (!isset($data['priority'])) {
            $data['priority'] = $this->inferPriority($data);
        }

        $alert = Alert::create($data);

        // Invalidate cache
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
            'alert' => new AlertResource($alert->load(['asset', 'template', 'history' => fn($q) => $q->latest()->limit(10)])),
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

        // Invalidate cache for both old and new asset
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
        $alert->delete(); // Soft delete

        // Invalidate cache
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

        // Invalidate cache
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

        // Invalidate cache
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
    public function backtestResults(Alert $alert)
    {
        Gate::authorize('view', $alert);

        $result = AlertBacktestResult::where('alert_id', $alert->id)
            ->latest()
            ->first();

        if (!$result) {
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
    public function activeCount(Request $request)
    {
        return response()->json([
            'count' => Alert::where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->count(),
        ]);
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

        if ($now > $close || $now->isWeekend()) {
            // Already past close or weekend, get next trading day
            $close = $now->copy()->nextWeekday()->setTime(14, 30);
        }

        return $close;
    }

    private function getNextMarketOpen(\DateTimeZone $timezone): \DateTime
    {
        $now = now()->setTimezone($timezone);
        $open = $now->copy()->nextWeekday()->setTime(10, 0);

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

        // Higher priority for anomaly and critical thresholds
        if ($type === 'anomaly') {
            return 'high';
        }

        if ($triggerType === 'target_price') {
            $targetPrice = $data['parameters']['target_price'] ?? 0;
            $currentPrice = Asset::find($data['asset_id'])?->last_price ?? 0;

            if ($currentPrice > 0) {
                $percentDiff = abs(($targetPrice - $currentPrice) / $currentPrice) * 100;
                if ($percentDiff <= 2) {
                    return 'high'; // Close to target
                }
            }
        }

        return 'medium';
    }
}
```

---

## AlertHistoryController

```bash
php artisan make:controller AlertHistoryController
```

```php
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
            ->when($request->filled('alert_id'), fn($q) => $q->where('alert_id', $request->alert_id))
            ->when($request->filled('asset_id'), fn($q) => $q->where('asset_id', $request->asset_id))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('triggered_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('triggered_at', '<=', $request->date_to))
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
```

---

## AlertPreferencesController

```bash
php artisan make:controller AlertPreferencesController
```

```php
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
        $preferences = UserAlertPreference::firstOrCreate(
            ['user_id' => $request->user()->id],
            UserAlertPreference::getDefaults()
        );

        return Inertia::render('Settings/Alerts', [
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
        UserAlertPreference::updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->validated()
        );

        return back()->with('success', __('settings.alerts_updated'));
    }
}
```

---

## NotificationController (API)

```bash
php artisan make:controller Api/NotificationController
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\AlertNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get user notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = AlertNotification::where('user_id', $request->user()->id)
            ->when($request->filled('since'), fn($q) => $q->where('created_at', '>', $request->since))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get unread notification count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = AlertNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(AlertNotification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            abort(403);
        }

        $notification->update([
            'status' => 'read',
            'read_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        AlertNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }
}
```

---

## Form Requests

### StoreAlertRequest

```bash
php artisan make:request StoreAlertRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'template_id' => ['nullable', 'uuid', 'exists:alert_templates,id'],
            'type' => ['required', Rule::in(['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation'])],
            'trigger_type' => ['required', 'string', 'max:50'],
            'scope' => ['required', Rule::in(['single_asset', 'watchlist', 'portfolio', 'sector', 'market'])],
            'direction' => ['nullable', Rule::in(['above', 'below', 'both', 'cross_up', 'cross_down'])],
            'condition_logic' => ['required', Rule::in(['single', 'and', 'or'])],
            'parameters' => ['required', 'array'],
            'parameters.target_price' => ['required_if:trigger_type,target_price', 'numeric', 'min:0.01'],
            'parameters.threshold_percent' => ['required_if:trigger_type,daily_change', 'numeric', 'min:0.1', 'max:100'],
            'parameters.zone_low' => ['required_if:trigger_type,zone', 'numeric', 'min:0'],
            'parameters.zone_high' => ['required_if:trigger_type,zone', 'numeric', 'gt:parameters.zone_low'],
            'priority' => ['nullable', Rule::in(['critical', 'high', 'medium', 'low'])],
            'is_recurring' => ['boolean'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'], // Max 1 week
            'max_triggers' => ['nullable', 'integer', 'min:1', 'max:100'],
            'delivery_config' => ['nullable', 'array'],
            'delivery_config.channels' => ['nullable', 'array'],
            'delivery_config.channels.*' => [Rule::in(['telegram', 'push', 'email', 'in_app'])],
            'escalation_config' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'market_hours_only' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'asset_id.required' => __('validation.alerts.asset_required'),
            'parameters.target_price.required_if' => __('validation.alerts.target_price_required'),
            'parameters.zone_high.gt' => __('validation.alerts.zone_high_must_be_greater'),
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set asset_id as required for single_asset scope
        if ($this->scope === 'single_asset' && !$this->asset_id) {
            $this->merge(['asset_id' => null]); // Will fail validation
        }
    }
}
```

### UpdateAlertRequest

```bash
php artisan make:request UpdateAlertRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['nullable', 'uuid', 'exists:assets,id'],
            'type' => ['sometimes', Rule::in(['price', 'prediction', 'signal', 'anomaly', 'pattern', 'recommendation'])],
            'trigger_type' => ['sometimes', 'string', 'max:50'],
            'scope' => ['sometimes', Rule::in(['single_asset', 'watchlist', 'portfolio', 'sector', 'market'])],
            'direction' => ['nullable', Rule::in(['above', 'below', 'both', 'cross_up', 'cross_down'])],
            'condition_logic' => ['sometimes', Rule::in(['single', 'and', 'or'])],
            'parameters' => ['sometimes', 'array'],
            'status' => ['sometimes', Rule::in(['active', 'paused'])],
            'priority' => ['sometimes', Rule::in(['critical', 'high', 'medium', 'low'])],
            'is_recurring' => ['sometimes', 'boolean'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'max_triggers' => ['nullable', 'integer', 'min:1', 'max:100'],
            'delivery_config' => ['nullable', 'array'],
            'escalation_config' => ['nullable', 'array'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'market_hours_only' => ['sometimes', 'boolean'],
        ];
    }
}
```

### SnoozeAlertRequest

```bash
php artisan make:request SnoozeAlertRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SnoozeAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:10080', 'required_without:preset'],
            'preset' => [
                'nullable',
                'required_without:duration_minutes',
                Rule::in(['1h', '4h', '1d', 'until_market_close', 'until_market_open']),
            ],
        ];
    }
}
```

### BacktestAlertRequest

```bash
php artisan make:request BacktestAlertRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BacktestAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lookback_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'include_ml_signals' => ['nullable', 'boolean'],
        ];
    }
}
```

### UpdateAlertPreferencesRequest

```bash
php artisan make:request UpdateAlertPreferencesRequest
```

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_channels' => ['nullable', 'array'],
            'default_channels.*' => [Rule::in(['telegram', 'push', 'email', 'in_app'])],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_start'],
            'timezone' => ['nullable', 'timezone'],
            'max_alerts_per_hour' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_alerts_per_day' => ['nullable', 'integer', 'min:1', 'max:500'],
            'digest_enabled' => ['nullable', 'boolean'],
            'digest_time' => ['nullable', 'date_format:H:i'],
            'smart_defaults_enabled' => ['nullable', 'boolean'],
        ];
    }
}
```

---

## API Resources

### AlertResource

```bash
php artisan make:resource AlertResource
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'asset' => $this->whenLoaded('asset', fn() => [
                'id' => $this->asset->id,
                'symbol' => $this->asset->symbol,
                'name' => $this->asset->name,
                'name_ar' => $this->asset->name_ar,
                'last_price' => $this->asset->last_price,
            ]),
            'template' => $this->whenLoaded('template', fn() => [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ]),
            'type' => $this->type,
            'trigger_type' => $this->trigger_type,
            'scope' => $this->scope,
            'direction' => $this->direction,
            'condition_logic' => $this->condition_logic,
            'parameters' => $this->parameters,
            'status' => $this->status,
            'priority' => $this->priority,
            'is_recurring' => $this->is_recurring,
            'cooldown_minutes' => $this->cooldown_minutes,
            'max_triggers' => $this->max_triggers,
            'triggered_count' => $this->triggered_count,
            'delivery_config' => $this->delivery_config,
            'escalation_config' => $this->escalation_config,
            'snoozed_until' => $this->snoozed_until?->toISOString(),
            'is_snoozed' => $this->isSnoozed(),
            'last_triggered_at' => $this->last_triggered_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'market_hours_only' => $this->market_hours_only,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'history' => AlertHistoryResource::collection($this->whenLoaded('history')),
        ];
    }
}
```

### AlertHistoryResource

```bash
php artisan make:resource AlertHistoryResource
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alert_id' => $this->alert_id,
            'alert' => $this->whenLoaded('alert', fn() => [
                'type' => $this->alert->type,
                'trigger_type' => $this->alert->trigger_type,
                'parameters' => $this->alert->parameters,
            ]),
            'asset' => $this->whenLoaded('asset', fn() => [
                'id' => $this->asset->id,
                'symbol' => $this->asset->symbol,
                'name' => $this->asset->name,
                'name_ar' => $this->asset->name_ar,
            ]),
            'triggered_at' => $this->triggered_at->toISOString(),
            'trigger_value' => $this->trigger_value,
            'trigger_context' => $this->trigger_context,
            'notification_sent' => $this->notification_sent,
            'acknowledged_at' => $this->acknowledged_at?->toISOString(),
            'escalation_level' => $this->escalation_level,
        ];
    }
}
```

### NotificationResource

```bash
php artisan make:resource NotificationResource
```

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->user()?->locale ?? 'en';

        return [
            'id' => $this->id,
            'type' => $this->type,
            'channel' => $this->channel,
            'priority' => $this->priority,
            'title' => $locale === 'ar' ? $this->title_ar : $this->title,
            'body' => $locale === 'ar' ? $this->body_ar : $this->body,
            'data' => $this->data,
            'status' => $this->status,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

---

## Testing

### AlertController Test

```php
<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_view_their_alerts(): void
    {
        $user = User::factory()->create();
        Alert::factory()->count(3)->create(['user_id' => $user->id]);
        Alert::factory()->count(2)->create(); // Other user's alerts

        $response = $this->actingAs($user)->get(route('alerts.index'));

        $response->assertOk();
        $response->assertInertia(fn($page) => $page
            ->component('Alerts/Index')
            ->has('alerts.data', 3)
        );
    }

    /** @test */
    public function user_can_create_price_alert(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $response = $this->actingAs($user)->post(route('alerts.store'), [
            'asset_id' => $asset->id,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'scope' => 'single_asset',
            'direction' => 'above',
            'condition_logic' => 'single',
            'parameters' => ['target_price' => 50.00],
            'is_recurring' => false,
            'market_hours_only' => true,
        ]);

        $response->assertRedirect(route('alerts.index'));
        $this->assertDatabaseHas('alerts', [
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'trigger_type' => 'target_price',
        ]);
    }

    /** @test */
    public function user_cannot_view_others_alerts(): void
    {
        $user = User::factory()->create();
        $otherAlert = Alert::factory()->create();

        $response = $this->actingAs($user)->get(route('alerts.show', $otherAlert));

        $response->assertForbidden();
    }

    /** @test */
    public function user_can_snooze_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('alerts.snooze', $alert), [
            'preset' => '1h',
        ]);

        $response->assertRedirect();
        $alert->refresh();
        $this->assertNotNull($alert->snoozed_until);
        $this->assertTrue($alert->snoozed_until->gt(now()));
    }

    /** @test */
    public function user_can_delete_alert(): void
    {
        $user = User::factory()->create();
        $alert = Alert::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('alerts.destroy', $alert));

        $response->assertRedirect();
        $this->assertSoftDeleted('alerts', ['id' => $alert->id]);
    }

    /** @test */
    public function validation_requires_target_price_for_target_price_alerts(): void
    {
        $user = User::factory()->create();
        $asset = Asset::factory()->create();

        $response = $this->actingAs($user)->post(route('alerts.store'), [
            'asset_id' => $asset->id,
            'type' => 'price',
            'trigger_type' => 'target_price',
            'scope' => 'single_asset',
            'direction' => 'above',
            'condition_logic' => 'single',
            'parameters' => [], // Missing target_price
        ]);

        $response->assertSessionHasErrors('parameters.target_price');
    }
}
```

### NotificationController Test

```php
<?php

namespace Tests\Feature\Api;

use App\Models\AlertNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_get_notifications(): void
    {
        $user = User::factory()->create();
        AlertNotification::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function user_can_get_unread_count(): void
    {
        $user = User::factory()->create();
        AlertNotification::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);
        AlertNotification::factory()->count(2)->create([
            'user_id' => $user->id,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread-count');

        $response->assertOk()
            ->assertJson(['count' => 3]);
    }

    /** @test */
    public function user_can_mark_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = AlertNotification::factory()->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $response->assertOk();
        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /** @test */
    public function user_can_mark_all_as_read(): void
    {
        $user = User::factory()->create();
        AlertNotification::factory()->count(5)->create([
            'user_id' => $user->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read-all');

        $response->assertOk();
        $this->assertEquals(0, AlertNotification::where('user_id', $user->id)->whereNull('read_at')->count());
    }
}
```

---

## Verification

After implementation:

```bash
# Check routes are registered
php artisan route:list --name=alerts

# Test in browser
# Navigate to /alerts

# Run tests
php artisan test --filter=AlertController
php artisan test --filter=NotificationController
```

---

## Next Task

Proceed to [Task 07: Frontend Implementation](./07-frontend.md)
