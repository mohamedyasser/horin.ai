<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestDetectedSignal extends Model
{
    /**
     * The table associated with the model (database view).
     */
    protected $table = 'v_latest_detected_signals';

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
            'timestamp' => 'integer',
            'value' => 'array',
            'strength' => 'float',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the asset that owns this signal.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }
}
