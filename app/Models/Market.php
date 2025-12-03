<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Market extends Model
{
    use HasUuids;

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'country_id',
        'name_en',
        'name_ar',
        'code',
        'timezone',
        'status',
        'asset_id',
        'tv_link',
        'trading_days',
        'open_at',
        'close_at',
        'is_open',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_open' => 'boolean',
    ];

    /**
     * Get the country that owns the market.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the assets for the market.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function getNameAttribute()
    {
        return app()->getLocale() === 'ar'
            ? $this->name_ar
            : $this->name_en;
    }

    /**
     * Approximate check for whether the market is open now.
     * Uses is_open flag OR current time within [open_at, close_at] on trading_days in the market's timezone.
     */
    public function isOpenNow(): bool
    {
        try {
            $tz = $this->timezone ?: config('app.timezone');
            $now = \Carbon\Carbon::now($tz);

            // Parse trading days (e.g., "Sun–Thu", "Mon-Fri", "Sun,Mon,Wed")
            $daysOrder = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $daysSet = [];
            $raw = (string) ($this->trading_days ?? '');
            if ($raw !== '') {
                $raw = str_replace(' ', '', $raw);
                foreach (preg_split('/,|،/u', $raw) as $segment) {
                    if ($segment === '') {
                        continue;
                    }
                    $range = preg_split('/–|-/', $segment);
                    if (count($range) === 2) {
                        [$start, $end] = $range;
                        $startIdx = array_search($start, $daysOrder, true);
                        $endIdx = array_search($end, $daysOrder, true);
                        if ($startIdx === false || $endIdx === false) {
                            continue;
                        }
                        if ($startIdx <= $endIdx) {
                            for ($i = $startIdx; $i <= $endIdx; $i++) {
                                $daysSet[] = $daysOrder[$i];
                            }
                        } else {
                            for ($i = $startIdx; $i < count($daysOrder); $i++) {
                                $daysSet[] = $daysOrder[$i];
                            }
                            for ($i = 0; $i <= $endIdx; $i++) {
                                $daysSet[] = $daysOrder[$i];
                            }
                        }
                    } else {
                        // Single day token
                        if (in_array($segment, $daysOrder, true)) {
                            $daysSet[] = $segment;
                        }
                    }
                }
                $daysSet = array_values(array_unique($daysSet));
            }

            $dayAbbr = $now->format('D');
            $isTradingDay = empty($daysSet) ? true : in_array($dayAbbr, $daysSet, true);

            $openCheck = true;
            if ($this->open_at && $this->close_at) {
                $openTime = $now->copy()->setTimeFromTimeString($this->open_at);
                $closeTime = $now->copy()->setTimeFromTimeString($this->close_at);
                $openCheck = $openTime <= $now && $now <= $closeTime;
            }

            return (bool) ($this->is_open || ($isTradingDay && $openCheck && (int) $this->status === 1));
        } catch (\Throwable $e) {
            // Fail-safe: fall back to the stored flag
            return (bool) $this->is_open;
        }
    }
}
