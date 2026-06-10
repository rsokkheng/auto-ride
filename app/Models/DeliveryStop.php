<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryStop extends Model
{
    protected $fillable = [
        'delivery_id', 'address', 'lat', 'lng',
        'recipient_name', 'recipient_phone', 'notes',
        'sort_order', 'status', 'proof_photo', 'delivered_at',
    ];

    protected $casts = [
        'lat'          => 'float',
        'lng'          => 'float',
        'sort_order'   => 'integer',
        'delivered_at' => 'datetime',
    ];

    protected $appends = ['proof_photo_url'];

    public function getProofPhotoUrlAttribute(): ?string
    {
        if (! $this->proof_photo) return null;
        if (str_starts_with($this->proof_photo, 'http')) return $this->proof_photo;
        return asset('storage/' . $this->proof_photo);
    }

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }
}
