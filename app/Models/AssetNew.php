<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetNew extends Model
{
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
