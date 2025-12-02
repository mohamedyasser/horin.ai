<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstantDetectedSignal extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instant_detected_signals';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pid',
        'timestamp',
        'indicator',
        'signal_type',
        'value',
        'strength',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'integer',
        'value' => 'array',
        'strength' => 'decimal:10',
        'created_at' => 'datetime',
    ];

    /**
     * Get the asset that owns the instant detected signal.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }
}
