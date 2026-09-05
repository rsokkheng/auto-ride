<?php

namespace App\Http\Controllers\Api;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class SupportController extends ApiController
{
    public function index(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $tickets = SupportTicket::with('messages.sender')
            ->where('user_id', $user->id)
            ->orWhere('assigned_to', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        return $this->success(['tickets' => $tickets]);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'subject'     => 'required|string|max:255',
            'message'     => 'required|string|max:1000',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'category'    => 'nullable|in:driver_late,overcharged,lost_item,safety,cancel_trip,other',
            'ride_id'     => 'nullable|exists:rides,id',
            'delivery_id' => 'nullable|exists:deliveries,id',
        ]);

        $ticket = SupportTicket::create([
            'user_id'     => $user->id,
            'subject'     => $data['subject'],
            'category'    => $data['category'] ?? null,
            'ride_id'     => $data['ride_id'] ?? null,
            'delivery_id' => $data['delivery_id'] ?? null,
            'status'      => 'open',
            'priority'    => $data['priority'] ?? 'medium',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => $data['message'],
        ]);

        return $this->success(['ticket' => $ticket->load('messages.sender')], 201);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$ticket->user_id, $ticket->assigned_to], true)) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $message = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'sender_id' => $user->id,
            'message' => $data['message'],
        ]);

        $ticket->touch();

        return $this->success(['message' => $message], 201);
    }
}
