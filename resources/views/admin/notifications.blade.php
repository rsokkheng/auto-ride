@extends('admin.layout')
@section('title', 'Notifications')
@section('page-title', 'Notifications')

@section('content')
<div class="card" style="border-radius:14px;overflow:hidden;">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">
        <div class="an-tabs p-0">
            @foreach(['' => 'All', 'purchase' => 'Purchase', 'rental' => 'Rental', 'system' => 'System'] as $key => $label)
                <a href="{{ route('admin.notifications', array_filter(['category' => $key, 'unread' => request('unread')])) }}"
                   class="an-tab {{ ($category ?? '') === $key ? 'active' : '' }}">
                    {{ $label }} (<span data-count="{{ $key ?: 'all' }}">{{ $counts[$key ?: 'all'] }}</span>)
                </a>
            @endforeach
        </div>
        <div class="d-flex align-items-center" style="gap:14px;">
            <a href="{{ route('admin.notifications', array_filter(['category' => $category, 'unread' => request()->boolean('unread') ? null : 1])) }}"
               class="an-link">{{ request()->boolean('unread') ? 'Show all' : 'Unread only' }}</a>
            <form method="POST" action="{{ route('admin.notifications.read-all') }}" class="m-0">
                @csrf
                @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                <button type="submit" class="an-link">Mark all as read</button>
            </form>
        </div>
    </div>
    <div class="card-body p-0">
        @forelse($notifications as $n)
            @include('admin.partials.notification-item', ['n' => $n->toPayload()])
        @empty
            <div class="an-empty"><i class="far fa-bell-slash mb-2 d-block" style="font-size:1.6rem;"></i>No notifications here.</div>
        @endforelse
    </div>
    @if($notifications->hasPages())
        <div class="card-footer bg-white">{{ $notifications->links() }}</div>
    @endif
</div>
@endsection
