<?php

namespace App\Mail;

use App\Models\Ride;
use App\Models\Delivery;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TripReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public string $tripType;   // ride | delivery
    public array  $details;

    public function __construct(string $tripType, array $details)
    {
        $this->tripType = $tripType;
        $this->details  = $details;
    }

    public static function fromRide(Ride $ride): self
    {
        return new self('ride', [
            'ref'              => 'RIDE-' . str_pad($ride->id, 6, '0', STR_PAD_LEFT),
            'date'             => $ride->completed_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i'),
            'from'             => $ride->pickup_address,
            'to'               => $ride->dropoff_address,
            'service_type'     => ucfirst(str_replace('_', ' ', $ride->service_type ?? 'Standard')),
            'distance_km'      => $ride->distance_km ?? '—',
            'duration_min'     => $ride->duration_min ?? '—',
            'base_fare'        => $ride->fare ?? 0,
            'waiting_fee'      => $ride->waiting_fee ?? 0,
            'surge_multiplier' => $ride->surge_multiplier ?? 1.0,
            'discount'         => $ride->discount_amount ?? 0,
            'total'            => $ride->fare ?? 0,
            'payment_method'   => ucfirst($ride->payment_method ?? 'cash'),
            'driver_name'      => $ride->driver?->name ?? 'N/A',
            'driver_rating'    => $ride->driver?->rating ?? null,
            'vehicle_plate'    => $ride->vehicle?->plate_number ?? null,
        ]);
    }

    public static function fromDelivery(Delivery $delivery): self
    {
        $label = $delivery->service_type === 'moving' ? 'MOV' : 'DEL';
        return new self('delivery', [
            'ref'            => $label . '-' . str_pad($delivery->id, 6, '0', STR_PAD_LEFT),
            'date'           => $delivery->completed_at?->format('d M Y, H:i') ?? now()->format('d M Y, H:i'),
            'from'           => $delivery->pickup_address,
            'to'             => $delivery->dropoff_address,
            'service_type'   => $delivery->service_type === 'moving' ? 'Moving Service' : 'Package Delivery',
            'package_size'   => $delivery->package_size ?? null,
            'distance_km'    => $delivery->distance_km ?? '—',
            'total'          => $delivery->fare ?? 0,
            'payment_method' => ucfirst($delivery->payment_method ?? 'cash'),
            'driver_name'    => $delivery->driver?->name ?? 'N/A',
        ]);
    }

    public function envelope(): Envelope
    {
        $ref = $this->details['ref'] ?? 'Trip';
        return new Envelope(subject: "AutoRide Receipt — {$ref}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.trip-receipt');
    }
}
