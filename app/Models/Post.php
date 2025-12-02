<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = [
        'title',
        'content',
        'description',
        'link',
        'resource_id',
        'language',
        'image_url',
        'small_image_url',
        'score',
        'sentiment',
        'reason',
        'negative_aspects',
        'positive_aspects',
        'asset_id',
        'market_id',
        'country_id',
        'images',
        'category',
        'date',
        'sector_id',
        'is_rewritten',
        'source',
        'actions',
        'slug',
        'meta_tags',
        'meta_description',
    ];

    protected $hidden = [
        'source',
        'resource_id',
        'link',
    ];

    /**
     * Get the asset that the post is related to.
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Get the sector that the post is related to.
     */
    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    /**
     * Get the market that the post is related to.
     */
    public function market()
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * Get the country that the post is related to.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the impact level based on the score.
     */
    public function getImpactLevelAttribute()
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
