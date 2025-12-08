<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestAnomaly extends Model
{
    /**
     * The table associated with the model (database view).
     */
    protected $table = 'latest_anomalies';

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
            'confidence_score' => 'float',
            'detected_at' => 'datetime',
            'price' => 'float',
            'volume' => 'integer',
            'extra' => 'array',
        ];
    }

    /**
     * Get the asset by symbol.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'symbol', 'symbol');
    }
}
