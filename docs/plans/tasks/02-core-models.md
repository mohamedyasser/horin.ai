# Task 02: Core Models & Services

**Priority:** P0 (Critical Path)
**Effort:** 2 days
**Dependencies:** Task 01 (Database Schema)

---

## Objective

Create Eloquent models, factories, policies, and core services for the alert system.

---

## Checklist

- [ ] Create Alert model with relationships
- [ ] Create AlertHistory model
- [ ] Create AlertTemplate model
- [ ] Create AlertChain model
- [ ] Create UserAlertPreference model
- [ ] Create AlertNotification model
- [ ] Create FailedNotification model
- [ ] Create AlertBacktestResult model
- [ ] Create factories for all models
- [ ] Create AlertPolicy
- [ ] Create AlertScopeResolver service
- [ ] Create AlertCacheService
- [ ] Write unit tests for models

---

## Model 1: Alert

```bash
php artisan make:model Alert
```

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Alert extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'asset_id',
        'template_id',
        'parent_alert_id',
        'chain_from_id',
        'type',
        'trigger_type',
        'scope',
        'direction',
        'condition_logic',
        'parameters',
        'status',
        'priority',
        'is_recurring',
        'cooldown_minutes',
        'max_triggers',
        'triggered_count',
        'delivery_config',
        'escalation_config',
        'snoozed_until',
        'last_triggered_at',
        'expires_at',
        'market_hours_only',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'delivery_config' => 'array',
            'escalation_config' => 'array',
            'is_recurring' => 'boolean',
            'market_hours_only' => 'boolean',
            'snoozed_until' => 'datetime',
            'last_triggered_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AlertTemplate::class);
    }

    public function parentAlert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'parent_alert_id');
    }

    public function childAlerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'parent_alert_id');
    }

    public function chainedFrom(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'chain_from_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(AlertHistory::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AlertNotification::class);
    }

    public function backtestResults(): HasMany
    {
        return $this->hasMany(AlertBacktestResult::class);
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeNotSnoozed($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('snoozed_until')
              ->orWhere('snoozed_until', '<', now());
        });
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeForAsset($query, string $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Helpers

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSnoozed(): bool
    {
        return $this->snoozed_until && $this->snoozed_until->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function canTrigger(): bool
    {
        if (!$this->isActive()) return false;
        if ($this->isSnoozed()) return false;
        if ($this->isExpired()) return false;
        if ($this->isInCooldown()) return false;
        if ($this->hasReachedMaxTriggers()) return false;

        return true;
    }

    public function isInCooldown(): bool
    {
        if (!$this->last_triggered_at) return false;

        return $this->last_triggered_at
            ->addMinutes($this->cooldown_minutes)
            ->isFuture();
    }

    public function hasReachedMaxTriggers(): bool
    {
        if (!$this->max_triggers) return false;

        return $this->triggered_count >= $this->max_triggers;
    }

    public function markAsTriggered(): void
    {
        $this->update([
            'triggered_count' => $this->triggered_count + 1,
            'last_triggered_at' => now(),
            'status' => $this->is_recurring ? 'active' : 'triggered',
        ]);
    }

    public function snooze(int $minutes): void
    {
        $this->update([
            'snoozed_until' => now()->addMinutes($minutes),
        ]);
    }

    public function unsnooze(): void
    {
        $this->update(['snoozed_until' => null]);
    }
}
```

---

## Model 2: AlertHistory

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'alert_history';

    protected $fillable = [
        'alert_id',
        'user_id',
        'asset_id',
        'triggered_at',
        'trigger_value',
        'trigger_context',
        'notification_sent',
        'acknowledged_at',
        'escalation_level',
    ];

    protected function casts(): array
    {
        return [
            'trigger_context' => 'array',
            'notification_sent' => 'boolean',
            'triggered_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function acknowledge(): void
    {
        $this->update(['acknowledged_at' => now()]);
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }
}
```

---

## Model 3: AlertTemplate

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'type',
        'trigger_type',
        'default_parameters',
        'default_delivery_config',
        'is_public',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'default_parameters' => 'array',
            'default_delivery_config' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'template_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeSystem($query)
    {
        return $query->whereNull('user_id');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
```

---

## Model 4: UserAlertPreference

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAlertPreference extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'default_channels',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'max_alerts_per_hour',
        'max_alerts_per_day',
        'digest_enabled',
        'digest_time',
        'smart_defaults_enabled',
    ];

    protected function casts(): array
    {
        return [
            'default_channels' => 'array',
            'digest_enabled' => 'boolean',
            'smart_defaults_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInQuietHours(): bool
    {
        if (!$this->quiet_hours_start || !$this->quiet_hours_end) {
            return false;
        }

        $now = now()->setTimezone($this->timezone);
        $start = $now->copy()->setTimeFromTimeString($this->quiet_hours_start);
        $end = $now->copy()->setTimeFromTimeString($this->quiet_hours_end);

        // Handle overnight quiet hours (e.g., 23:00 - 07:00)
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }

        return $now >= $start && $now <= $end;
    }
}
```

---

## Service: AlertScopeResolver

```php
<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Asset;
use Illuminate\Support\Collection;

class AlertScopeResolver
{
    /**
     * Resolve which assets an alert applies to.
     */
    public function resolveAssets(Alert $alert): Collection
    {
        return match ($alert->scope) {
            'single_asset' => collect([$alert->asset_id])->filter(),
            'watchlist' => $this->getWatchlistAssets($alert),
            'portfolio' => $this->getPortfolioAssets($alert),
            'sector' => $this->getSectorAssets($alert),
            'market' => $this->getMarketAssets(),
            default => collect(),
        };
    }

    /**
     * Check if an event matches an alert's scope.
     */
    public function matchesScope(Alert $alert, string $eventAssetId): bool
    {
        if ($alert->scope === 'single_asset') {
            return $alert->asset_id === $eventAssetId;
        }

        return $this->resolveAssets($alert)->contains($eventAssetId);
    }

    /**
     * Get entry price for portfolio alerts.
     */
    public function getEntryPrice(Alert $alert, string $assetId): ?float
    {
        if ($alert->scope !== 'portfolio') {
            return $alert->parameters['entry_price'] ?? null;
        }

        $holding = $alert->user
            ->portfolioHoldings()
            ->where('asset_id', $assetId)
            ->first();

        return $holding?->average_cost;
    }

    private function getWatchlistAssets(Alert $alert): Collection
    {
        return $alert->user->watchlist()->pluck('asset_id');
    }

    private function getPortfolioAssets(Alert $alert): Collection
    {
        return $alert->user
            ->portfolioHoldings()
            ->where('quantity', '>', 0)
            ->pluck('asset_id');
    }

    private function getSectorAssets(Alert $alert): Collection
    {
        $sectorId = $alert->parameters['sector_id'] ?? null;

        if (!$sectorId) {
            return collect();
        }

        return Asset::where('sector_id', $sectorId)
            ->where('is_active', true)
            ->pluck('id');
    }

    private function getMarketAssets(): Collection
    {
        return Asset::where('is_active', true)->pluck('id');
    }
}
```

---

## Service: AlertCacheService

```php
<?php

namespace App\Services;

use App\Models\Alert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AlertCacheService
{
    private const CACHE_TTL = 60; // seconds

    /**
     * Cache active alerts by asset for fast lookup.
     */
    public function cacheActiveAlerts(): void
    {
        $alerts = Alert::active()
            ->notSnoozed()
            ->notExpired()
            ->get();

        // Group by asset_id
        $byAsset = $alerts->groupBy('asset_id');
        foreach ($byAsset as $assetId => $assetAlerts) {
            if ($assetId) {
                Cache::put(
                    "active_alerts:asset:{$assetId}",
                    $assetAlerts->toArray(),
                    self::CACHE_TTL
                );
            }
        }

        // Group by type for intelligence alerts
        $byType = $alerts->groupBy('type');
        foreach ($byType as $type => $typeAlerts) {
            Cache::put(
                "active_alerts:type:{$type}",
                $typeAlerts->toArray(),
                self::CACHE_TTL
            );
        }

        // Store asset IDs with active alerts in a Redis set
        $assetIds = $byAsset->keys()->filter()->toArray();
        if (count($assetIds) > 0) {
            Redis::del('active_alert_assets');
            Redis::sadd('active_alert_assets', ...$assetIds);
        }
    }

    /**
     * Get alerts for a specific asset.
     */
    public function getAlertsForAsset(string $assetId): Collection
    {
        $cached = Cache::get("active_alerts:asset:{$assetId}");

        if ($cached) {
            return collect($cached)->map(fn($data) => new Alert($data));
        }

        $alerts = Alert::active()
            ->notSnoozed()
            ->notExpired()
            ->forAsset($assetId)
            ->get();

        Cache::put("active_alerts:asset:{$assetId}", $alerts->toArray(), self::CACHE_TTL);

        return $alerts;
    }

    /**
     * Get alerts by type.
     */
    public function getAlertsByType(string $type): Collection
    {
        $cached = Cache::get("active_alerts:type:{$type}");

        if ($cached) {
            return collect($cached)->map(fn($data) => new Alert($data));
        }

        $alerts = Alert::active()
            ->notSnoozed()
            ->notExpired()
            ->ofType($type)
            ->get();

        Cache::put("active_alerts:type:{$type}", $alerts->toArray(), self::CACHE_TTL);

        return $alerts;
    }

    /**
     * Check if asset has any active alerts (O(1) lookup).
     */
    public function hasActiveAlerts(string $assetId): bool
    {
        return Redis::sismember('active_alert_assets', $assetId);
    }

    /**
     * Invalidate cache for an asset.
     */
    public function invalidateAsset(string $assetId): void
    {
        Cache::forget("active_alerts:asset:{$assetId}");
        $this->refreshAssetSet();
    }

    /**
     * Invalidate cache for a type.
     */
    public function invalidateType(string $type): void
    {
        Cache::forget("active_alerts:type:{$type}");
    }

    /**
     * Refresh the Redis set of assets with active alerts.
     */
    private function refreshAssetSet(): void
    {
        $assetIds = Alert::active()
            ->notSnoozed()
            ->notExpired()
            ->whereNotNull('asset_id')
            ->distinct()
            ->pluck('asset_id')
            ->toArray();

        Redis::del('active_alert_assets');
        if (count($assetIds) > 0) {
            Redis::sadd('active_alert_assets', ...$assetIds);
        }
    }
}
```

---

## Policy: AlertPolicy

```bash
php artisan make:policy AlertPolicy --model=Alert
```

```php
<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    public function create(User $user): bool
    {
        // Check alert limit
        $activeCount = Alert::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        return $activeCount < 50; // Max 50 active alerts
    }

    public function update(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    public function delete(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    public function snooze(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id && $alert->isActive();
    }

    public function backtest(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }
}
```

---

## Factory: AlertFactory

```bash
php artisan make:factory AlertFactory --model=Alert
```

```php
<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'asset_id' => Asset::factory(),
            'type' => 'price',
            'trigger_type' => 'target_price',
            'scope' => 'single_asset',
            'direction' => 'above',
            'condition_logic' => 'single',
            'parameters' => [
                'target_price' => $this->faker->randomFloat(2, 10, 100),
            ],
            'status' => 'active',
            'priority' => 'medium',
            'is_recurring' => false,
            'cooldown_minutes' => 60,
            'market_hours_only' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function triggered(): static
    {
        return $this->state([
            'status' => 'triggered',
            'last_triggered_at' => now(),
            'triggered_count' => 1,
        ]);
    }

    public function paused(): static
    {
        return $this->state(['status' => 'paused']);
    }

    public function snoozed(): static
    {
        return $this->state([
            'snoozed_until' => now()->addHours(2),
        ]);
    }

    public function recurring(): static
    {
        return $this->state(['is_recurring' => true]);
    }

    public function targetPrice(float $price): static
    {
        return $this->state([
            'type' => 'price',
            'trigger_type' => 'target_price',
            'parameters' => ['target_price' => $price, 'direction' => 'above'],
        ]);
    }

    public function signal(): static
    {
        return $this->state([
            'type' => 'signal',
            'trigger_type' => 'signal',
            'parameters' => [
                'indicators' => ['RSI', 'MACD'],
                'signal_types' => ['oversold', 'bullish_cross'],
                'min_strength' => 0.7,
            ],
        ]);
    }

    public function watchlistScope(): static
    {
        return $this->state([
            'asset_id' => null,
            'scope' => 'watchlist',
        ]);
    }
}
```

---

## Register Services & Policies

```php
// app/Providers/AppServiceProvider.php

public function register(): void
{
    $this->app->singleton(AlertCacheService::class);
    $this->app->singleton(AlertScopeResolver::class);
}

// bootstrap/app.php (or AuthServiceProvider)
Gate::policy(Alert::class, AlertPolicy::class);
```

---

## Model Events for Cache Invalidation

```php
// app/Models/Alert.php - add to boot method or use Observer

protected static function booted(): void
{
    static::created(function (Alert $alert) {
        app(AlertCacheService::class)->invalidateAsset($alert->asset_id);
        app(AlertCacheService::class)->invalidateType($alert->type);
    });

    static::updated(function (Alert $alert) {
        app(AlertCacheService::class)->invalidateAsset($alert->asset_id);
        app(AlertCacheService::class)->invalidateType($alert->type);
    });

    static::deleted(function (Alert $alert) {
        app(AlertCacheService::class)->invalidateAsset($alert->asset_id);
        app(AlertCacheService::class)->invalidateType($alert->type);
    });
}
```

---

## Verification

```bash
# Run tests
php artisan test --filter=AlertTest

# Test in tinker
php artisan tinker
>>> $user = User::first();
>>> $alert = Alert::factory()->for($user)->create();
>>> $alert->isActive()
>>> $alert->canTrigger()
```

---

## Next Task

Proceed to [Task 03: Alert Processing Engine](./03-alert-processing.md)
