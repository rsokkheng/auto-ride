{{-- One notification row. $n = AdminNotification::toPayload(). Mirrored by renderItem() in notification-script. --}}
<a href="{{ $n['url'] }}" class="an-item {{ $n['read'] ? '' : 'unread' }}" data-id="{{ $n['id'] }}">
    <div class="an-icon an-tone-{{ $n['tone'] }}"><i class="fas {{ $n['icon'] }}"></i></div>
    <div class="an-body">
        <div class="an-title">{{ $n['title'] }}</div>
        <div class="an-msg">{{ $n['message'] }}</div>
        @if($n['detail'])<div class="an-detail">{{ $n['detail'] }}</div>@endif
    </div>
    <div class="an-side">
        <div><span class="an-time" data-time="{{ $n['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($n['created_at'])->diffForHumans(short: true) }}</span> <i class="fas fa-arrow-right an-arrow"></i></div>
        @unless($n['read'])<span class="an-dot"></span>@endunless
    </div>
</a>
