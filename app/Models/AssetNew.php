<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetNew extends Model
{
    use HasFactory, HasUuids;

    /**
     * Indicates if the IDs are auto-incrementing.
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

    protected $fillable = [
        'title',
        'content',
        'description',
        'resource_id',
        'image_url',
        'score',
        'sentiment',
        'reason',
        'risks',
        'opportunities',
        'asset_id',
        'market_id',
        'country_id',
        'category',
        'date',
        'sector_id',
        'is_rewritten',
        'source',
        'action',
        'slug',
        'meta_tags',
        'affected_sectors',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'risks' => 'array',
            'opportunities' => 'array',
            'meta_tags' => 'array',
            'affected_sectors' => 'array',
            'is_rewritten' => 'boolean',
            'date' => 'datetime',
        ];
    }

    protected $hidden = [
        'source',
        'resource_id',
    ];

    /**
     * Get the asset that the post is related to.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the sector that the post is related to.
     */
    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * Get the market that the post is related to.
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * Get the country that the post is related to.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the bookmarks for this news article.
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(NewsBookmark::class);
    }

    /**
     * Get the ratings for this news article.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(NewsRating::class);
    }

    /**
     * Get the comments for this news article.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(NewsComment::class);
    }

    /**
     * Get the impact level based on the score.
     */
    public function getImpactLevelAttribute(): ?string
    {
        if (! $this->score) {
            return null;
        }

        if ($this->score >= 7) {
            return 'high';
        } elseif ($this->score >= 4) {
            return 'medium';
        } else {
            return 'low';
        }
    }
}
