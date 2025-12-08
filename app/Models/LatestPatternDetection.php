<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatestPatternDetection extends Model
{
    /**
     * The table associated with the model (database view).
     */
    protected $table = 'latest_pattern_detections';

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
            'timestamp' => 'integer',
            'patterns' => 'array',
            'has_head_shoulder' => 'boolean',
            'has_multiple_tops_bottoms' => 'boolean',
            'has_triangle' => 'boolean',
            'has_wedge' => 'boolean',
            'has_channel' => 'boolean',
            'has_double_top_bottom' => 'boolean',
            'has_trendline' => 'boolean',
            'has_support_resistance' => 'boolean',
            'has_pivots' => 'boolean',
            'pattern_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the asset that owns this pattern detection.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    /**
     * Get a list of detected pattern names.
     *
     * @return array<string>
     */
    public function getDetectedPatternNames(): array
    {
        $patterns = [];

        if ($this->has_head_shoulder) {
            $patterns[] = 'head_shoulder';
        }
        if ($this->has_multiple_tops_bottoms) {
            $patterns[] = 'multiple_tops_bottoms';
        }
        if ($this->has_triangle) {
            $patterns[] = 'triangle';
        }
        if ($this->has_wedge) {
            $patterns[] = 'wedge';
        }
        if ($this->has_channel) {
            $patterns[] = 'channel';
        }
        if ($this->has_double_top_bottom) {
            $patterns[] = 'double_top_bottom';
        }
        if ($this->has_trendline) {
            $patterns[] = 'trendline';
        }
        if ($this->has_support_resistance) {
            $patterns[] = 'support_resistance';
        }
        if ($this->has_pivots) {
            $patterns[] = 'pivots';
        }

        return $patterns;
    }
}
