<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstantIndicator extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instant_indicators';

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
        'rsi',
        'macd_line',
        'macd_signal',
        'macd_histogram',
        'bb_middle',
        'bb_upper',
        'bb_lower',
        'ema',
        'sma',
        'adx',
        'stoch_k',
        'stoch_d',
        'cci',
        'williams_r',
        'roc',
        'momentum',
        'atr',
        'obv',
        'volume_ma',
        'vwap',
        'supertrend',
        'psar',
    ];

    /**
     * Get the asset that owns the instant indicator.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }
}
