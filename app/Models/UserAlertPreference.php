<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAlertPreference extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (UserAlertPreference $preference) {
            // PostgreSQL requires explicit boolean casting
            // Convert to string 'true'/'false' which PostgreSQL accepts
            if (isset($preference->attributes['digest_enabled'])) {
                $preference->attributes['digest_enabled'] = $preference->attributes['digest_enabled'] ? 'true' : 'false';
            }
            if (isset($preference->attributes['smart_defaults_enabled'])) {
                $preference->attributes['smart_defaults_enabled'] = $preference->attributes['smart_defaults_enabled'] ? 'true' : 'false';
            }
        });
    }

    protected $fillable = [
        'user_id',
        'default_channels',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'max_alerts_per_hour',
        'max_alerts_per_day',
        'digest_enabled',
        'digest_time',
        'smart_defaults_enabled',
    ];

    protected function casts(): array
    {
        return [
            'default_channels' => 'array',
            'digest_enabled' => 'boolean',
            'smart_defaults_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInQuietHours(): bool
    {
        if (! $this->quiet_hours_start || ! $this->quiet_hours_end) {
            return false;
        }

        $now = now()->setTimezone($this->timezone);
        $start = $now->copy()->setTimeFromTimeString($this->quiet_hours_start);
        $end = $now->copy()->setTimeFromTimeString($this->quiet_hours_end);

        // Handle overnight quiet hours (e.g., 23:00 - 07:00)
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }

        return $now >= $start && $now <= $end;
    }

    public function getDefaultChannels(): array
    {
        return $this->default_channels ?? ['telegram', 'in_app'];
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->getDefaultChannels());
    }

    public static function getDefaults(): array
    {
        return [
            'default_channels' => ['telegram', 'in_app'],
            'quiet_hours_start' => '23:00',
            'quiet_hours_end' => '07:00',
            'timezone' => 'Africa/Cairo',
            'max_alerts_per_hour' => 10,
            'max_alerts_per_day' => 50,
            'digest_enabled' => true,
            'digest_time' => '20:00',
            'smart_defaults_enabled' => true,
        ];
    }
}
