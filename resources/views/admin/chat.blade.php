@extends('admin.layout')
@section('title', 'Chat Testing')
@section('page-title', 'Chat Testing')

@push('styles')
<style>
    .chat-wrapper        { display:flex; height:calc(100vh - 200px); min-height:500px; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; background:#fff; }
    .chat-sidebar        { width:300px; flex-shrink:0; border-right:1px solid #e2e8f0; display:flex; flex-direction:column; background:#f8fafc; }
    .chat-sidebar-header { padding:16px; border-bottom:1px solid #e2e8f0; }
    .conv-list           { flex:1; overflow-y:auto; }
    .conv-item           { display:flex; align-items:center; gap:10px; padding:12px 16px; cursor:pointer; border-bottom:1px solid #f1f5f9; transition:background .15s; }
    .conv-item:hover     { background:#f1f5f9; }
    .conv-item.active    { background:#fee2e2; border-left:3px solid #e63946; }
    .conv-avatar         { width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#e63946,#c1121f);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0; }
    .conv-info           { flex:1; min-width:0; }
    .conv-name           { font-weight:600; font-size:.85rem; color:#1e293b; white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .conv-preview        { font-size:.75rem; color:#94a3b8; white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    .conv-badge          { font-size:.7rem; background:#e63946; color:#fff; border-radius:20px; padding:1px 7px; }

    .chat-main           { flex:1; display:flex; flex-direction:column; }
    .chat-header         { padding:14px 20px; border-bottom:1px solid #e2e8f0; display:flex; align-items:center; gap:12px; background:#fff; }
    .chat-messages       { flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:10px; background:#f8fafc; }
    .chat-empty          { display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#94a3b8; }
    .msg-row             { display:flex; align-items:flex-end; gap:8px; }
    .msg-row.admin       { flex-direction:row-reverse; }
    .msg-bubble          { max-width:65%; padding:10px 14px; border-radius:18px; font-size:.875rem; line-height:1.5; word-break:break-word; }
    .msg-row:not(.admin) .msg-bubble { background:#fff; border:1px solid #e2e8f0; color:#1e293b; border-bottom-left-radius:4px; }
    .msg-row.admin .msg-bubble       { background:linear-gradient(135deg,#e63946,#c1121f); color:#fff; border-bottom-right-radius:4px; }
    .msg-time            { font-size:.7rem; color:#94a3b8; white-space:nowrap; margin-bottom:2px; }
    .msg-sender          { font-size:.7rem; color:#64748b; margin-bottom:2px; }
    .chat-input-area     { padding:12px 16px; border-top:1px solid #e2e8f0; background:#fff; display:flex; gap:8px; align-items:flex-end; }
    .chat-input          { flex:1; border:1px solid #e2e8f0; border-radius:22px; padding:10px 16px; font-size:.875rem; resize:none; outline:none; max-height:120px; overflow-y:auto; }
    .chat-input:focus    { border-color:#e63946; box-shadow:0 0 0 2px rgba(230,57,70,.1); }
    .btn-send            { width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#e63946,#c1121f);border:none;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:opacity .15s; }
    .btn-send:disabled   { opacity:.5;cursor:not-allowed; }
    .no-conv-selected    { display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:#94a3b8;gap:8px; }
</style>
@endpush

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div></div>
    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#newChatModal">
        <i class="fas fa-plus mr-1"></i> New Chat
    </button>
</div>

<div class="chat-wrapper">

    {{-- ── Sidebar ── --}}
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <p class="font-weight-bold mb-1" style="font-size:.8rem;color:#64748b;letter-spacing:.05em;">CONVERSATIONS</p>
            <input type="text" id="conv-search" class="form-control form-control-sm" placeholder="Search by name…">
        </div>
        <div class="conv-list" id="conv-list">
            @forelse($conversations as $conv)
            @php
                $other = $conv->passenger_id === $admin->id ? $conv->driver : $conv->passenger;
                $lastMsg = $conv->messages->first();
                $initial = strtoupper(substr($other?->name ?? '?', 0, 1));
                $unread  = $conv->messages->filter(fn($m) => $m->sender_id !== $admin->id && is_null($m->read_at))->count();
            @endphp
            <div class="conv-item {{ request('open') == $conv->id ? 'active' : '' }}"
                 data-id="{{ $conv->id }}"
                 data-name="{{ $other?->name ?? 'Unknown' }}"
                 data-role="{{ $other?->role ?? '' }}"
                 onclick="openConversation({{ $conv->id }}, '{{ addslashes($other?->name ?? 'Unknown') }}', '{{ $other?->role ?? '' }}')">
                <div class="conv-avatar">{{ $initial }}</div>
                <div class="conv-info">
                    <div class="conv-name">
                        {{ $other?->name ?? 'Unknown' }}
                        <small class="text-muted ml-1">({{ ucfirst($other?->role ?? '') }})</small>
                    </div>
                    <div class="conv-preview">{{ $lastMsg?->message ?? 'No messages yet' }}</div>
                </div>
                @if($unread > 0)
                    <span class="conv-badge">{{ $unread }}</span>
                @endif
            </div>
            @empty
            <div class="text-center text-muted p-4" style="font-size:.85rem;">No conversations yet.<br>Click <strong>New Chat</strong> to start.</div>
            @endforelse
        </div>
    </div>

    {{-- ── Main Chat Area ── --}}
    <div class="chat-main">

        {{-- Header --}}
        <div class="chat-header" id="chat-header" style="display:none;">
            <div class="conv-avatar" id="chat-avatar" style="width:36px;height:36px;font-size:.8rem;"></div>
            <div>
                <div class="font-weight-bold" id="chat-name" style="font-size:.95rem;"></div>
                <small class="text-muted" id="chat-role"></small>
            </div>
            <div class="ml-auto">
                <span class="badge badge-success" id="typing-indicator" style="display:none;">
                    <i class="fas fa-circle mr-1" style="font-size:.4rem;"></i> Live
                </span>
            </div>
        </div>

        {{-- Messages --}}
        <div class="chat-messages" id="chat-messages">
            <div class="no-conv-selected" id="no-conv">
                <i class="fas fa-comments" style="font-size:3rem;opacity:.2;"></i>
                <span>Select a conversation or start a new one</span>
            </div>
        </div>

        {{-- Input --}}
        <div class="chat-input-area" id="chat-input-area" style="display:none;">
            <textarea class="chat-input" id="msg-input" rows="1"
                placeholder="Type a message…"
                onkeydown="handleKey(event)"
                oninput="autoResize(this)"></textarea>
            <button class="btn-send" id="btn-send" onclick="sendMessage()" title="Send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>

    </div>
</div>

{{-- New Chat Modal --}}
<div class="modal fade" id="newChatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-comments mr-2"></i>Start New Chat</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="{{ route('admin.chat.start') }}">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="font-weight-bold">Select User <span class="text-danger">*</span></label>
                        <select name="user_id" class="form-control" required>
                            <option value="">— Choose driver or passenger —</option>
                            @foreach($users->groupBy('role') as $role => $group)
                                <optgroup label="{{ ucfirst($role) }}s">
                                    @foreach($group as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} — {{ $u->email }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">First Message <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control" rows="3"
                                  placeholder="Hi! This is the admin. How can I help you?" required maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-1"></i> Send & Open
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const adminId       = {{ $admin->id }};
let activeConvId    = {{ request('open') ?? 'null' }};
let pollTimer       = null;
let lastMessageId   = 0;

// Auto-open if redirected with ?open=
if (activeConvId) {
    const el = document.querySelector('.conv-item[data-id="' + activeConvId + '"]');
    if (el) openConversation(activeConvId, el.dataset.name, el.dataset.role);
}

// ── Conversation sidebar search ────────────────────────────────────────────
document.getElementById('conv-search').addEventListener('input', function () {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.conv-item').forEach(function (el) {
        el.style.display = el.dataset.name.toLowerCase().includes(q) ? '' : 'none';
    });
});

// ── Open conversation ──────────────────────────────────────────────────────
function openConversation(convId, name, role) {
    activeConvId = convId;
    lastMessageId = 0;

    // Highlight active
    document.querySelectorAll('.conv-item').forEach(el => el.classList.remove('active'));
    const item = document.querySelector('.conv-item[data-id="' + convId + '"]');
    if (item) item.classList.add('active');

    // Update header
    document.getElementById('no-conv').style.display      = 'none';
    document.getElementById('chat-header').style.display  = 'flex';
    document.getElementById('chat-input-area').style.display = 'flex';
    document.getElementById('chat-name').textContent  = name;
    document.getElementById('chat-role').textContent  = ucfirst(role);
    document.getElementById('chat-avatar').textContent = name.charAt(0).toUpperCase();

    // Load messages
    loadMessages(true);

    // Start polling
    clearInterval(pollTimer);
    document.getElementById('typing-indicator').style.display = 'inline-block';
    pollTimer = setInterval(() => loadMessages(false), 3000);

    document.getElementById('msg-input').focus();
}

// ── Load messages via AJAX ─────────────────────────────────────────────────
function loadMessages(scrollToBottom) {
    if (!activeConvId) return;

    fetch('/admin/chat/' + activeConvId + '/messages', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        const messages = data.messages;
        if (!messages.length && scrollToBottom) {
            document.getElementById('chat-messages').innerHTML =
                '<div class="chat-empty"><i class="fas fa-comment-slash" style="font-size:2rem;opacity:.2;"></i><span>No messages yet</span></div>';
            return;
        }

        // Check if new messages arrived
        const newestId = messages.length ? messages[messages.length - 1].id : 0;
        if (!scrollToBottom && newestId === lastMessageId) return; // no change
        lastMessageId = newestId;

        const box = document.getElementById('chat-messages');
        const wasAtBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 60;

        box.innerHTML = messages.map(m => `
            <div class="msg-row ${m.is_admin ? 'admin' : ''}">
                <div>
                    <div class="msg-sender">${m.is_admin ? 'You (Admin)' : m.sender}</div>
                    <div class="msg-bubble">${escapeHtml(m.message)}</div>
                    <div class="msg-time">${m.time}</div>
                </div>
            </div>
        `).join('');

        if (scrollToBottom || wasAtBottom) {
            box.scrollTop = box.scrollHeight;
        }
    })
    .catch(() => {});
}

// ── Send message ───────────────────────────────────────────────────────────
function sendMessage() {
    if (!activeConvId) return;
    const input = document.getElementById('msg-input');
    const text  = input.value.trim();
    if (!text) return;

    const btn = document.getElementById('btn-send');
    btn.disabled = true;

    fetch('/admin/chat/' + activeConvId + '/send', {
        method:  'POST',
        headers: {
            'Content-Type':     'application/json',
            'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ message: text }),
    })
    .then(r => r.json())
    .then(() => {
        input.value = '';
        input.style.height = '';
        loadMessages(true);
    })
    .finally(() => { btn.disabled = false; input.focus(); });
}

// ── Helpers ────────────────────────────────────────────────────────────────
function handleKey(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 120) + 'px';
}

function ucfirst(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

function escapeHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>');
}
</script>

{{-- Make sure csrf meta tag exists in layout head --}}
@endpush
