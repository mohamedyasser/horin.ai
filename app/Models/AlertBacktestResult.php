<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertBacktestResult extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'alert_id',
        'lookback_days',
        'trigger_count',
        'triggers',
        'avg_return_1d',
        'avg_return_1w',
        'avg_return_1m',
        'win_rate',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'triggers' => 'array',
            'avg_return_1d' => 'decimal:4',
            'avg_return_1w' => 'decimal:4',
            'avg_return_1m' => 'decimal:4',
            'win_rate' => 'decimal:4',
            'completed_at' => 'datetime',
        ];
    }

    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    // Helpers

    public function getTriggerDates(): array
    {
        return collect($this->triggers)->pluck('date')->toArray();
    }

    public function getAverageReturn(string $period): ?float
    {
        return match ($period) {
            '1d' => $this->avg_return_1d,
            '1w' => $this->avg_return_1w,
            '1m' => $this->avg_return_1m,
            default => null,
        };
    }

    public function isPositiveReturn(string $period): bool
    {
        $return = $this->getAverageReturn($period);

        return $return !== null && $return > 0;
    }

    public function getWinRatePercentage(): ?float
    {
        return $this->win_rate !== null ? $this->win_rate * 100 : null;
    }

    public function getRecommendation(): string
    {
        if ($this->trigger_count === 0) {
            return 'no_historical_triggers';
        }

        if ($this->win_rate >= 0.6 && $this->avg_return_1w > 0) {
            return 'good_follow_through';
        }

        if ($this->win_rate < 0.4) {
            return 'poor_performance';
        }

        return 'moderate_results';
    }
}
