<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\FirestoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatController extends ApiController
{
    private const MAX_FILE_KB  = 10240; // 10 MB
    private const MAX_IMAGE_KB = 5120;  // 5 MB

    public function __construct(private FirestoreService $firestore) {}

    /**
     * POST /v1/chats
     * Create or resume a conversation. Supports text, image, or file as opening message.
     * Multipart form or JSON.
     * Body: user_id, topic?, message?, attachment (file)
     */
    public function create(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'user_id'    => 'required|exists:users,id|different:' . ($user->id ?? 0),
            'topic'      => 'nullable|string|max:100',
            'message'    => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:' . self::MAX_FILE_KB,
        ]);

        if (empty($data['message']) && ! $request->hasFile('attachment')) {
            return response()->json(['data' => null, 'message' => 'Provide a message or attachment.'], 422);
        }

        $targetId = (int) $data['user_id'];

        $conversation = ChatConversation::where(function ($q) use ($user, $targetId) {
            $q->where('passenger_id', $user->id)->where('driver_id', $targetId);
        })->orWhere(function ($q) use ($user, $targetId) {
            $q->where('passenger_id', $targetId)->where('driver_id', $user->id);
        })->where('status', 'open')->first();

        $isNew = false;

        if (! $conversation) {
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

        $message = $this->saveMessage($request, $conversation->id, $user->id, $data['message'] ?? null);

        $conversation->touch();
        $conversation->load('passenger', 'driver');

        $this->firestore->syncConversation($conversation, $message);
        $this->firestore->syncMessage($message->load('sender'));

        return $this->success([
            'conversation' => $conversation,
            'message'      => $message,
            'is_new'       => $isNew,
        ], $isNew ? 201 : 200);
    }

    /**
     * GET /v1/chats
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $conversations = ChatConversation::with(['passenger', 'driver'])
            ->where('passenger_id', $user->id)
            ->orWhere('driver_id', $user->id)
            ->orderByDesc('updated_at')
            ->get();

        return $this->success(['conversations' => $conversations]);
    }

    /**
     * GET /v1/chats/{conversation}
     */
    public function show(Request $request, ChatConversation $conversation)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$conversation->passenger_id, $conversation->driver_id], true)) {
            return $this->unauthorized();
        }

        return $this->success([
            'messages' => $conversation->messages()->with('sender')->orderBy('created_at')->get(),
        ]);
    }

    /**
     * POST /v1/chats/{conversation}/messages
     * Send text, image (camera/gallery), or file attachment.
     *
     * multipart/form-data:
     *   message    string  optional (required if no attachment)
     *   attachment file    optional (image or document, max 10 MB)
     *
     * OR application/json:
     *   message    string  required
     */
    public function store(Request $request, ChatConversation $conversation)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$conversation->passenger_id, $conversation->driver_id], true)) {
            return $this->unauthorized();
        }

        $request->validate([
            'message'    => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:' . self::MAX_FILE_KB,
        ]);

        if (! $request->filled('message') && ! $request->hasFile('attachment')) {
            return response()->json(['data' => null, 'message' => 'Provide a message or attachment.'], 422);
        }

        $message = $this->saveMessage($request, $conversation->id, $user->id, $request->input('message'));

        $conversation->touch();

        $this->firestore->syncConversation($conversation->load('passenger', 'driver'), $message);
        $this->firestore->syncMessage($message->load('sender'));

        return $this->success(['message' => $message->load('sender')], 201);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function saveMessage(Request $request, int $conversationId, int $senderId, ?string $text): ChatMessage
    {
        $type           = 'text';
        $attachmentUrl  = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $mime = $file->getMimeType() ?? '';

            $isImage = str_starts_with($mime, 'image/');
            $type    = $isImage ? 'image' : 'file';

            $folder   = $isImage ? 'chat/images' : 'chat/files';
            $filename = Str::random(16) . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs($folder, $filename, 'public');

            $attachmentUrl  = asset('storage/' . $path);
            $attachmentName = $file->getClientOriginalName();
        }

        return ChatMessage::create([
            'conversation_id' => $conversationId,
            'sender_id'       => $senderId,
            'message'         => $text,
            'type'            => $type,
            'attachment_url'  => $attachmentUrl,
            'attachment_name' => $attachmentName,
        ]);
    }
}
