<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestAssetPrice extends Model
{
    /**
     * The table associated with the model (materialized view).
     */
    protected $table = 'mv_latest_asset_prices';

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
            'price' => 'float',
            'high' => 'float',
            'low' => 'float',
            'last_close' => 'float',
            'volume' => 'float',
            'hours_ago' => 'integer',
            'timestamp' => 'integer',
            'price_time' => 'datetime',
        ];
    }

    /**
     * Get the asset that owns this price.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    /**
     * Check if the price is live (within last hour).
     */
    public function isLive(): bool
    {
        return $this->freshness === 'live';
    }

    /**
     * Check if the price is from today.
     */
    public function isToday(): bool
    {
        return in_array($this->freshness, ['live', 'today']);
    }

    /**
     * Check if the price is stale (older than today).
     */
    public function isStale(): bool
    {
        return in_array($this->freshness, ['yesterday', 'older']);
    }
}
