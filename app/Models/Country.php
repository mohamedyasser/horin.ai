<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name_en',
        'name_ar',
        'code',
        'currency_en',
        'currency_ar',
        'currency_code',
        'status',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('active', function ($query) {
            $query->whereRaw('status = true');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

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
     * Get the markets for the country.
     */
    public function markets(): HasMany
    {
        return $this->hasMany(Market::class);
    }

    /**
     * Get the assets for the country.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Get the news articles for the country.
     */
    public function assetNews(): HasMany
    {
        return $this->hasMany(AssetNew::class);
    }

    /**
     * Build a list of countries keyed by id for select components.
     */
    public static function selectOptions(): array
    {
        $nameColumn = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';

        return static::query()
            ->orderBy($nameColumn)
            ->get(['id', 'name_en', 'name_ar'])
            ->mapWithKeys(function (self $country) use ($nameColumn): array {
                $label = $country->{$nameColumn} ?? null;

                if (! $label) {
                    $alternativeColumn = $nameColumn === 'name_ar' ? 'name_en' : 'name_ar';
                    $label = $country->{$alternativeColumn} ?? '';
                }

                return [$country->id => $label];
            })
            ->toArray();
    }

    public function getNameAttribute()
    {
        return app()->getLocale() === 'ar'
            ? $this->name_ar
            : $this->name_en;
    }
}
