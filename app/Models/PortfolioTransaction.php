<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioTransaction extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'portfolio_id',
        'asset_id',
        'portfolio_asset_id',
        'type',
        'quantity',
        'price',
        'total_amount',
        'currency',
        'fx_rate',
        'transaction_date',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'decimal:8',
        'price' => 'decimal:8',
        'total_amount' => 'decimal:8',
        'fx_rate' => 'decimal:10',
        'transaction_date' => 'datetime',
    ];

    /**
     * Get the portfolio that owns the transaction.
     */
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * Get the asset that owns the transaction.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the portfolio asset that owns the transaction.
     */
    public function portfolioAsset(): BelongsTo
    {
        return $this->belongsTo(PortfolioAsset::class);
    }
}
