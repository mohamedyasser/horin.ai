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
