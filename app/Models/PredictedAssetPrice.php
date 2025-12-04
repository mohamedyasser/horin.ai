<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PredictedAssetPrice extends Model
{
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
        'symbol',
        'model_name',
        'timestamp',
        'prediction_time',
        'price_prediction',
        'confidence',
        'horizon',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'price_prediction' => 'float',
            'confidence' => 'float',
            'horizon' => 'string',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Get the asset that owns the predicted price.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }
}
