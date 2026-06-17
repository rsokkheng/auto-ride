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

#[Fillable(['name', 'email', 'password', 'phone', 'avatar', 'role', 'driver_type', 'company_name', 'company_id', 'salary', 'commission_rate', 'api_token', 'refresh_token', 'token_expires_at', 'refresh_token_expires_at', 'available', 'status_note', 'wallet_balance', 'current_latitude', 'current_longitude', 'rating', 'total_ratings', 'approval_status', 'approved_at', 'cancellation_count', 'cancellation_penalty_until', 'proxy_phone', 'fcm_token', 'referral_code', 'referred_by', 'city', 'service_zone', 'social_provider', 'social_id', 'current_streak', 'longest_streak', 'last_trip_date', 'loyalty_points', 'membership_tier_id', 'onboarding_completed_at', 'onboarding_steps', 'accessibility_settings'])]
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
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'wallet_balance'              => 'integer',
            'salary'                      => 'integer',
            'commission_rate'             => 'float',
            'token_expires_at'            => 'datetime',
            'refresh_token_expires_at'    => 'datetime',
            'approved_at'                 => 'datetime',
            'cancellation_penalty_until'  => 'datetime',
            'cancellation_count'          => 'integer',
            'current_streak'              => 'integer',
            'longest_streak'              => 'integer',
            'last_trip_date'              => 'date',
            'loyalty_points'              => 'integer',
            'membership_tier_id'          => 'integer',
            'onboarding_completed_at'     => 'datetime',
            'onboarding_steps'            => 'array',
            'accessibility_settings'      => 'array',
        ];
    }

    protected $appends = ['avatar_url', 'photo_url'];

    /** Full public URL for the profile avatar, or null if not set. */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    /** Alias for avatar_url — used by driver profile responses. */
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->avatar_url;
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

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->orderByDesc('created_at');
    }

    public function topUpRequests(): HasMany
    {
        return $this->hasMany(TopUpRequest::class)->orderByDesc('created_at');
    }

    public function savedPlaces(): HasMany
    {
        return $this->hasMany(UserSavedPlace::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(UserEmergencyContact::class);
    }

    public function incentives(): HasMany
    {
        return $this->hasMany(DriverIncentive::class, 'driver_id');
    }

    public function driverDocuments(): HasMany
    {
        return $this->hasMany(\App\Models\DriverDocument::class, 'driver_id');
    }

    public function userVouchers(): HasMany
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function membershipTier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MembershipTier::class);
    }

    public function biometricDevices(): HasMany
    {
        return $this->hasMany(BiometricDevice::class);
    }

    public function linkedAccounts(): HasMany
    {
        return $this->hasMany(LinkedAccount::class, 'primary_user_id');
    }
}
