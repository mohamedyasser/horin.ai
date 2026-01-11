<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'type',
        'trigger_type',
        'default_parameters',
        'default_delivery_config',
        'is_public',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'default_parameters' => 'array',
            'default_delivery_config' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class, 'template_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeSystem($query)
    {
        return $query->whereNull('user_id');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
