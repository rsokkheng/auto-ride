<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatController extends ApiController
{
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
            'sender_id' => $user->id,
            'message' => $data['message'],
        ]);

        $conversation->touch();

        return $this->success(['message' => $message], 201);
    }
}
