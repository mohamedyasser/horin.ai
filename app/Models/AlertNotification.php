<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertNotification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'alert_id',
        'alert_history_id',
        'idempotency_key',
        'type',
        'channel',
        'priority',
        'title',
        'title_ar',
        'body',
        'body_ar',
        'data',
        'status',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_reason',
        'retry_count',
        'escalation_level',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    public function alertHistory(): BelongsTo
    {
        return $this->belongsTo(AlertHistory::class);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    // Status helpers

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    // Actions

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failed_reason' => $reason,
        ]);
    }

    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }

    public function escalate(): void
    {
        $this->update([
            'escalation_level' => $this->escalation_level + 1,
            'escalated_at' => now(),
        ]);
    }

    public function getLocalizedTitle(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'ar' ? $this->title_ar : $this->title;
    }

    public function getLocalizedBody(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();

        return $locale === 'ar' ? $this->body_ar : $this->body;
    }
}
