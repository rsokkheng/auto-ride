<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One step in a Partner package's status timeline. */
class PackageTrackingHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['delivery_id', 'status', 'raw_status', 'note'];

    protected $casts = ['created_at' => 'datetime'];
}
