<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstantConsumerAction extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instant_consumer_actions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'consumer_id',
        'classification_id',
        'action_type',
        'action_data',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'action_data' => 'array',
    ];

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
     * Get the consumer that owns the action.
     */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(InstantSignalConsumer::class, 'consumer_id');
    }

    /**
     * Get the classification that owns the action.
     */
    public function classification(): BelongsTo
    {
        return $this->belongsTo(InstantSignalClassification::class, 'classification_id');
    }
}
