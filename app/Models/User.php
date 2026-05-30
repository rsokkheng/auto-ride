<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'password', 'phone', 'role', 'driver_type', 'company_name', 'api_token', 'refresh_token', 'token_expires_at', 'refresh_token_expires_at', 'available', 'status_note', 'wallet_balance', 'current_latitude', 'current_longitude', 'rating', 'total_ratings'])]
#[Hidden(['password', 'remember_token', 'api_token', 'refresh_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'         => 'datetime',
            'password'                  => 'hashed',
            'wallet_balance'            => 'integer',
            'token_expires_at'          => 'datetime',
            'refresh_token_expires_at'  => 'datetime',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function passengerRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'passenger_id');
    }

    public function driverRides(): HasMany
    {
        return $this->hasMany(Ride::class, 'driver_id');
    }

    public function senderDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'sender_id');
    }

    public function driverDeliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }

    public function chatConversations(): HasMany
    {
        return $this->hasMany(ChatConversation::class, 'passenger_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function safetyIncidents(): HasMany
    {
        return $this->hasMany(SafetyIncident::class);
    }

    public function marketplaceItems(): HasMany
    {
        return $this->hasMany(MarketplaceItem::class, 'seller_id');
    }

    public function pushNotifications(): HasMany
    {
        return $this->hasMany(PushNotification::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function assignedSupportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'assigned_to');
    }

    public function sosAlerts(): HasMany
    {
        return $this->hasMany(SOSAlert::class);
    }
}
