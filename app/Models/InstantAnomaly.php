<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstantAnomaly extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instant_anomalies';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'symbol',
        'anomaly_type',
        'confidence_score',
        'detected_at',
        'window',
        'price',
        'volume',
        'extra',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'confidence_score' => 'float',
        'detected_at' => 'datetime',
        'price' => 'decimal:8',
        'volume' => 'integer',
        'extra' => 'array',
    ];

    /**
     * Get the asset that owns the anomaly.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'symbol', 'symbol');
    }
}
