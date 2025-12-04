<?php

namespace App\Models;

use App\Support\Horizon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestPrediction extends Model
{
    /**
     * The table associated with the model (materialized view).
     */
    protected $table = 'mv_latest_predictions';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'pid';

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the primary key.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'price_prediction' => 'float',
            'confidence' => 'float',
            'lower_bound' => 'float',
            'upper_bound' => 'float',
            'horizon' => 'string',
            'horizon_minutes' => 'integer',
            'timestamp' => 'integer',
            'target_timestamp' => 'integer',
            'prediction_time' => 'datetime',
            'created_at' => 'datetime',
            'age_minutes' => 'integer',
            'minutes_to_target' => 'integer',
        ];
    }

    /**
     * Get the asset that owns this prediction.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    /**
     * Get the human-readable horizon label.
     */
    public function getHorizonLabelAttribute(): string
    {
        return Horizon::label($this->horizon);
    }

    /**
     * Check if the prediction is fresh (within last hour).
     */
    public function isFresh(): bool
    {
        return $this->freshness === 'fresh';
    }

    /**
     * Check if the prediction is from today.
     */
    public function isToday(): bool
    {
        return $this->freshness === 'today' || $this->freshness === 'fresh';
    }

    /**
     * Check if the prediction is stale.
     */
    public function isStale(): bool
    {
        return ! $this->isFresh();
    }
}
