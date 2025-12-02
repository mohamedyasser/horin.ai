<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUuids, Notifiable;

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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'language',
        'country_id',
        'experience_level',
        'theme',
        'investment_goal',
        'risk_level',
        'trading_style',
        'phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the portfolios for the user.
     */
    public function portfolios()
    {
        return $this->hasMany(Portfolio::class);
    }

    public function defaultPortfolio(): HasOne
    {
        return $this->hasOne(Portfolio::class)->where('is_default', true);
    }

    /**
     * Get the sectors that the user is interested in.
     */
    public function userSectors()
    {
        return $this->hasMany(UserSector::class);
    }

    /**
     * Get the markets that the user is interested in.
     */
    public function userMarkets()
    {
        return $this->hasMany(UserMarket::class);
    }

    /**
     * Get the markets that the user is interested in (direct relationship).
     */
    public function markets()
    {
        return $this->belongsToMany(Market::class, 'user_markets');
    }

    /**
     * Get the sectors that the user is interested in (direct relationship).
     */
    public function sectors()
    {
        return $this->belongsToMany(Sector::class, 'user_sectors');
    }

    /**
     * Get the wishlist items for the user.
     */
    public function userWishlists()
    {
        return $this->hasMany(UserWishlist::class);
    }

    /**
     * Get the assets that the user has in their wishlist (direct relationship).
     */
    public function wishlistAssets()
    {
        return $this->belongsToMany(Asset::class, 'user_wishlists');
    }
}
