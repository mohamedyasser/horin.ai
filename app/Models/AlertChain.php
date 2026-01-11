<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertChain extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'trigger_alert_id',
        'activate_alert_id',
        'delay_minutes',
        'expires_after_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function triggerAlert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'trigger_alert_id');
    }

    public function activateAlert(): BelongsTo
    {
        return $this->belongsTo(Alert::class, 'activate_alert_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function hasDelay(): bool
    {
        return $this->delay_minutes > 0;
    }

    public function hasExpiration(): bool
    {
        return $this->expires_after_minutes !== null;
    }
}
