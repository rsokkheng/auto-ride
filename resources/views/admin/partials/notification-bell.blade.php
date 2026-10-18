{{-- Header bell + dropdown panel. Expects $anCounts (AdminNotification::unreadCounts). Behaviour lives in notification-script. --}}
<style>
    .an-bell .nav-link { position: relative; }
    .an-badge { position: absolute; top: 2px; right: 0; background: #ef4444; color: #fff; font-size: .62rem; font-weight: 700; min-width: 18px; height: 18px; line-height: 14px; border-radius: 9px; padding: 0 4px; text-align: center; border: 2px solid #fff; }
    .an-panel { width: 380px; max-width: calc(100vw - 24px); padding: 0; border: none; border-radius: 14px; box-shadow: 0 12px 40px rgba(15,23,42,.16); overflow: hidden; }
    .an-head { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px 8px; }
    .an-head h6 { margin: 0; font-weight: 700; color: #1e293b; font-size: 1rem; }
    .an-link { font-size: .8rem; color: #16a34a; font-weight: 600; background: none; border: 0; padding: 0; cursor: pointer; }
    .an-link:hover { color: #15803d; text-decoration: underline; }
    .an-tabs { display: flex; gap: 6px; padding: 4px 16px 10px; flex-wrap: wrap; }
    .an-tab { border: 1px solid #e2e8f0; background: #f8fafc; color: #475569; border-radius: 999px; padding: 3px 11px; font-size: .75rem; cursor: pointer; text-decoration: none !important; }
    .an-tab.active { border-color: #16a34a; color: #15803d; background: #f0fdf4; font-weight: 600; }
    .an-list { max-height: 420px; overflow-y: auto; border-top: 1px solid #f1f5f9; }
    .an-item { display: flex; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: inherit !important; text-decoration: none !important; background: #fff; }
    .an-item:hover { background: #f8fafc; }
    .an-icon { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.05rem; }
    .an-tone-green { background: #dcfce7; color: #16a34a; }
    .an-tone-red   { background: #fee2e2; color: #dc2626; }
    .an-tone-blue  { background: #dbeafe; color: #2563eb; }
    .an-tone-amber { background: #fef3c7; color: #d97706; }
    .an-body { flex: 1; min-width: 0; }
    .an-title { font-weight: 700; font-size: .85rem; color: #1e293b; }
    .an-item:not(.unread) .an-title { font-weight: 600; color: #475569; }
    .an-msg, .an-detail { font-size: .78rem; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .an-side { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; }
    .an-time { font-size: .72rem; color: #94a3b8; white-space: nowrap; }
    .an-arrow { font-size: .7rem; color: #1e293b; margin-left: 4px; }
    .an-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; }
    .an-empty { padding: 32px 16px; text-align: center; color: #94a3b8; font-size: .85rem; }
    .an-foot { padding: 12px 16px; }
    .an-foot a { display: block; text-align: center; border: 1px solid #16a34a; color: #15803d; border-radius: 10px; padding: 8px; font-weight: 600; font-size: .85rem; }
    .an-foot a:hover { background: #f0fdf4; text-decoration: none; }
    .an-toasts { position: fixed; right: 16px; bottom: 16px; z-index: 1080; display: flex; flex-direction: column; gap: 10px; width: 340px; max-width: calc(100vw - 32px); }
    .an-toast { border-radius: 12px; box-shadow: 0 12px 40px rgba(15,23,42,.18); overflow: hidden; animation: an-in .25s ease-out; }
    .an-toast .an-item { border-bottom: 0; }
    @keyframes an-in { from { transform: translateY(12px); opacity: 0; } to { transform: none; opacity: 1; } }
</style>

<li class="nav-item dropdown an-bell" id="anBell">
    <a class="nav-link" data-toggle="dropdown" href="#" aria-label="Notifications">
        <i class="far fa-bell" style="font-size:1.1rem;color:#1e293b;"></i>
        <span class="an-badge" id="anBadge" @if(! $anCounts['all']) style="display:none" @endif>{{ $anCounts['all'] > 99 ? '99+' : $anCounts['all'] }}</span>
    </a>
    <div class="dropdown-menu dropdown-menu-right an-panel" id="anPanel">
        <div class="an-head">
            <h6>Notifications</h6>
            <button type="button" class="an-link" id="anReadAll">Mark all as read</button>
        </div>
        <div class="an-tabs">
            @foreach(['all' => 'All', 'purchase' => 'Purchase', 'rental' => 'Rental', 'system' => 'System'] as $key => $label)
                <button type="button" class="an-tab {{ $key === 'all' ? 'active' : '' }}" data-category="{{ $key }}">
                    {{ $label }} (<span data-count="{{ $key }}">{{ $anCounts[$key] }}</span>)
                </button>
            @endforeach
        </div>
        <div class="an-list" id="anList"><div class="an-empty"><i class="fas fa-spinner fa-spin"></i></div></div>
        <div class="an-foot"><a href="{{ route('admin.notifications') }}">View All Notifications</a></div>
    </div>
</li>
