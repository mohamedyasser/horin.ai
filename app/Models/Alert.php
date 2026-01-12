<?php

namespace App\Models;

use App\Services\AlertCacheService;
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

    protected static function booted(): void
    {
        // Ensure boolean values are properly cast for PostgreSQL
        static::saving(function (Alert $alert) {
            $alert->is_recurring = (bool) $alert->is_recurring;
            $alert->market_hours_only = (bool) $alert->market_hours_only;
        });

        static::created(function (Alert $alert) {
            if ($alert->asset_id) {
                app(AlertCacheService::class)->invalidateAsset($alert->asset_id);
            }
            app(AlertCacheService::class)->invalidateType($alert->type);
        });

        static::updated(function (Alert $alert) {
            if ($alert->asset_id) {
                app(AlertCacheService::class)->invalidateAsset($alert->asset_id);
            }
            app(AlertCacheService::class)->invalidateType($alert->type);
        });

        static::deleted(function (Alert $alert) {
            if ($alert->asset_id) {
                app(AlertCacheService::class)->invalidateAsset($alert->asset_id);
            }
            app(AlertCacheService::class)->invalidateType($alert->type);
        });
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
        if (! $this->isActive()) {
            return false;
        }
        if ($this->isSnoozed()) {
            return false;
        }
        if ($this->isExpired()) {
            return false;
        }
        if ($this->isInCooldown()) {
            return false;
        }
        if ($this->hasReachedMaxTriggers()) {
            return false;
        }

        return true;
    }

    public function isInCooldown(): bool
    {
        if (! $this->last_triggered_at) {
            return false;
        }

        return $this->last_triggered_at
            ->addMinutes($this->cooldown_minutes)
            ->isFuture();
    }

    public function hasReachedMaxTriggers(): bool
    {
        if (! $this->max_triggers) {
            return false;
        }

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
