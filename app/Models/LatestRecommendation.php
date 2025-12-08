<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestRecommendation extends Model
{
    /**
     * The table associated with the model (database view).
     */
    protected $table = 'latest_recommendations';

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
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the asset that owns this recommendation.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    /**
     * Check if the recommendation is stale (older than 30 minutes).
     */
    public function isStale(): bool
    {
        return $this->created_at?->diffInMinutes(now()) > 30;
    }
}
