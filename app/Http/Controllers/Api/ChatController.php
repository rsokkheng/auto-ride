<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\FirestoreService;
use Illuminate\Http\Request;

class ChatController extends ApiController
{
    public function __construct(private FirestoreService $firestore) {}
    /**
     * POST /v1/chats
     *
     * Create a new conversation or return existing one between two users.
     * Body: { "user_id": 3, "topic": "ride_support", "message": "Hello!" }
     */
    public function create(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'user_id' => 'required|exists:users,id|different:' . ($user->id ?? 0),
            'topic'   => 'nullable|string|max:100',
            'message' => 'required|string|max:2000',
        ]);

        $targetId = (int) $data['user_id'];

        // Find existing open conversation between these two users.
        $conversation = ChatConversation::where(function ($q) use ($user, $targetId) {
            $q->where('passenger_id', $user->id)->where('driver_id', $targetId);
        })->orWhere(function ($q) use ($user, $targetId) {
            $q->where('passenger_id', $targetId)->where('driver_id', $user->id);
        })->where('status', 'open')->first();

        $isNew = false;

        if (! $conversation) {
            // Determine passenger/driver slot by role.
            $target = \App\Models\User::find($targetId);

            [$passengerId, $driverId] = $target?->role === 'driver'
                ? [$user->id, $targetId]
                : [$targetId, $user->id];

            $conversation = ChatConversation::create([
                'passenger_id' => $passengerId,
                'driver_id'    => $driverId,
                'topic'        => $data['topic'] ?? null,
                'status'       => 'open',
            ]);

            $isNew = true;
        }

        // Send the first / opening message.
        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'message'         => $data['message'],
        ]);

        $conversation->touch();
        $conversation->load('passenger', 'driver');

        // Sync to Firestore — Flutter listens to chats/{id} and chats/{id}/messages
        $this->firestore->syncConversation($conversation, $message);
        $this->firestore->syncMessage($message->load('sender'));

        return $this->success([
            'conversation' => $conversation,
            'message'      => $message,
            'is_new'       => $isNew,
        ], $isNew ? 201 : 200);
    }

    public function index(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $conversations = ChatConversation::with(['passenger', 'driver'])
            ->where('passenger_id', $user->id)
            ->orWhere('driver_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        return $this->success(['conversations' => $conversations]);
    }

    public function show(Request $request, ChatConversation $conversation)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$conversation->passenger_id, $conversation->driver_id], true)) {
            return $this->unauthorized();
        }

        return $this->success(['messages' => $conversation->messages()->with('sender')->orderBy('created_at')->get()]);
    }

    public function store(Request $request, ChatConversation $conversation)
    {
        $user = $this->authUser($request);

        if (! $user || ! in_array($user->id, [$conversation->passenger_id, $conversation->driver_id], true)) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $message = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'message'         => $data['message'],
        ]);

        $conversation->touch();

        // Sync to Firestore
        $this->firestore->syncConversation($conversation->load('passenger', 'driver'), $message);
        $this->firestore->syncMessage($message->load('sender'));

        return $this->success(['message' => $message->load('sender')], 201);
    }
}
