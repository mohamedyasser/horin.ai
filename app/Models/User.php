<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'language',
        'country_id',
        'experience_level',
        'theme',
        'investment_goal',
        'risk_level',
        'trading_style',
        'phone',
        'telegram_id',
        'telegram_username',
        'telegram_photo_url',
        'onboarding_completed_at',
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
            'onboarding_completed_at' => 'datetime',
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

    /**
     * Get the alerts for the user.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * Get the active alerts for the user.
     */
    public function activeAlerts(): HasMany
    {
        return $this->hasMany(Alert::class)->where('status', 'active');
    }

    /**
     * Get the alert preferences for the user.
     */
    public function alertPreferences(): HasOne
    {
        return $this->hasOne(UserAlertPreference::class);
    }

    /**
     * Get the alert notifications for the user.
     */
    public function alertNotifications(): HasMany
    {
        return $this->hasMany(AlertNotification::class);
    }

    /**
     * Get the alert history for the user.
     */
    public function alertHistory(): HasMany
    {
        return $this->hasMany(AlertHistory::class);
    }

    /**
     * Get the alert templates created by the user.
     */
    public function alertTemplates(): HasMany
    {
        return $this->hasMany(AlertTemplate::class);
    }

    /**
     * Get the alert chains for the user.
     */
    public function alertChains(): HasMany
    {
        return $this->hasMany(AlertChain::class);
    }

    /**
     * Get or create alert preferences for the user.
     */
    public function getAlertPreferences(): UserAlertPreference
    {
        return $this->alertPreferences ?? UserAlertPreference::create([
            'user_id' => $this->id,
        ]);
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function markPhoneAsVerified(): bool
    {
        return $this->forceFill([
            'phone_verified_at' => $this->freshTimestamp(),
        ])->save();
    }

    public function markOnboardingAsComplete(): bool
    {
        return $this->forceFill([
            'onboarding_completed_at' => $this->freshTimestamp(),
        ])->save();
    }
}
