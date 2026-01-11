<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FailedNotification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'notification_id',
        'alert_id',
        'user_id',
        'error',
        'payload',
        'failed_at',
        'reviewed_at',
        'should_retry',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'failed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'should_retry' => 'boolean',
        ];
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AlertNotification::class, 'notification_id');
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopeUnreviewed($query)
    {
        return $query->whereNull('reviewed_at');
    }

    public function scopeRetryable($query)
    {
        return $query->where('should_retry', true);
    }

    // Actions

    public function markAsReviewed(): void
    {
        $this->update(['reviewed_at' => now()]);
    }

    public function markForRetry(): void
    {
        $this->update(['should_retry' => true]);
    }

    public function isReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }
}
