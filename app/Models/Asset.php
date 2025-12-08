<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

class Asset extends Model
{
    use HasUuids, Searchable;

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'symbol';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tv_id',
        'symbol',
        'isin',
        'logo_id',
        'type',
        'currency',
        'inv_symbol',
        'inv_id',
        'name_en',
        'name_ar',
        'description_en',
        'description_ar',
        'short_description_en',
        'short_description_ar',
        'full_name',
        'mb_url',
        'status',
        'country_id',
        'market_id',
        'sector_id',
    ];

    protected $appends = ['name', 'description'];

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
     * Get the country that owns the asset.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the market that owns the asset.
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * Get the sector that owns the asset.
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * Get the portfolio assets for the asset.
     */
    public function portfolioAssets(): HasMany
    {
        return $this->hasMany(PortfolioAsset::class);
    }

    /**
     * Get the portfolio transactions for the asset.
     */
    public function portfolioTransactions(): HasMany
    {
        return $this->hasMany(PortfolioTransaction::class);
    }

    /**
     * Get the instant indicators for the asset.
     */
    public function instantIndicators(): HasMany
    {
        return $this->hasMany(InstantIndicator::class, 'pid', 'id');
    }

    /**
     * Get the wishlist items for the asset.
     */
    public function userWishlists(): HasMany
    {
        return $this->hasMany(UserWishlist::class);
    }

    /**
     * Get the users who have this asset in their wishlist.
     */
    public function wishlistUsers()
    {
        return $this->belongsToMany(User::class, 'user_wishlists');
    }

    public function latestPrice(): HasOne
    {
        return $this->hasOne(AssetPrice::class, 'pid', 'inv_id')->ofMany('timestamp', 'max');
    }

    public function latestPrediction(): HasOne
    {
        return $this->hasOne(PredictedAssetPrice::class, 'pid', 'inv_id')
            ->whereRaw('timestamp = (SELECT MAX(timestamp) FROM predicted_asset_prices AS sub WHERE sub.pid = predicted_asset_prices.pid)');
    }

    /**
     * Get the cached latest price from materialized view.
     * Use this for better performance on listing pages.
     */
    public function cachedPrice(): HasOne
    {
        return $this->hasOne(LatestAssetPrice::class, 'pid', 'inv_id');
    }

    /**
     * Get the cached latest prediction from materialized view.
     * Use this for better performance on listing pages.
     */
    public function cachedPrediction(): HasOne
    {
        return $this->hasOne(LatestPrediction::class, 'pid', 'inv_id');
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(PredictedAssetPrice::class, 'pid', 'inv_id');
    }

    public function latestIndicator(): HasOne
    {
        return $this->hasOne(InstantIndicator::class, 'pid', 'inv_id')
            ->ofMany('timestamp', 'max');
    }

    /**
     * Get the latest recommendation for this asset.
     */
    public function latestRecommendation(): HasOne
    {
        return $this->hasOne(LatestRecommendation::class, 'pid', 'inv_id');
    }

    /**
     * Get the active signals for this asset (last 30 minutes).
     */
    public function latestSignals(): HasMany
    {
        return $this->hasMany(LatestDetectedSignal::class, 'pid', 'inv_id');
    }

    /**
     * Get the latest pattern detection for this asset.
     */
    public function latestPatternDetection(): HasOne
    {
        return $this->hasOne(LatestPatternDetection::class, 'pid', 'inv_id');
    }

    /**
     * Get the latest anomalies for this asset.
     */
    public function latestAnomalies(): HasMany
    {
        return $this->hasMany(LatestAnomaly::class, 'symbol', 'inv_id');
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(AssetPrice::class, 'pid', 'inv_id');
    }

    public function getNameAttribute()
    {
        return app()->getLocale() === 'ar'
            ? $this->name_ar
            : $this->name_en;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar'
            ? $this->description_ar
            : $this->description_en;
    }

    public function scopeCrypto(Builder $q): Builder
    {
        return $q->where('type', 'crypto');
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'isin' => $this->isin,
            'full_name' => $this->full_name,
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'description_en' => $this->description_en,
            'description_ar' => $this->description_ar,
            // Filterable
            'market_id' => $this->market_id,
            'sector_id' => $this->sector_id,
            'country_id' => $this->country_id,
            // Denormalized market
            'market_code' => $this->market?->code,
            'market_name_en' => $this->market?->name_en,
            'market_name_ar' => $this->market?->name_ar,
            // Denormalized sector
            'sector_name_en' => $this->sector?->name_en,
            'sector_name_ar' => $this->sector?->name_ar,
        ];
    }
}
