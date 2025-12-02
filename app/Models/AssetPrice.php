<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetPrice extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Use the composite key's time component as the Eloquent primary key.
     * This avoids Eloquent assuming an `id` column.
     */
    protected $primaryKey = 'timestamp';

    /**
     * The primary key is not auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The primary key type.
     */
    protected $keyType = 'int';
}
