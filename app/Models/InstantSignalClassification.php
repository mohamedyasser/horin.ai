<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstantSignalClassification extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instant_signal_classifications';

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
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pid',
        'signal_id',
        'classification',
        'confidence',
        'metadata',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'confidence' => 'decimal:10',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Get the asset that owns the instant signal classification.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'pid', 'inv_id');
    }

    /**
     * Get the detected signal that owns the instant signal classification.
     */
    public function detectedSignal(): BelongsTo
    {
        return $this->belongsTo(InstantDetectedSignal::class, 'signal_id', 'id');
    }

    /**
     * Get the consumer actions for the classification.
     */
    public function consumerActions(): HasMany
    {
        return $this->hasMany(InstantConsumerAction::class, 'classification_id');
    }
}
