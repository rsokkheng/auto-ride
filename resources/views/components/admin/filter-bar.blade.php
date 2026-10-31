{{--
    GET filter form for a list card header: search box + any selects in the slot,
    Apply, Clear (only when something is filtered) and a result count.

    <x-admin.filter-bar :action="route('admin.charging-stations')" placeholder="Search name, address or operator"
                        :count="$stations->total()" noun="station">
        <x-admin.filter-select name="operator" :options="$operators" all="All operators" />
    </x-admin.filter-bar>

    :keep lists query params that are page context (e.g. a tab), not filters: they are carried
    through as hidden inputs and kept by Clear.
--}}
@props(['action', 'placeholder' => 'Search…', 'search' => 'search', 'count' => null, 'noun' => 'result', 'keep' => []])
@php
    $kept     = array_filter(request()->only($keep), 'filled');
    $filtered = collect(request()->except(['page', 'view', ...$keep]))->filter(fn ($v) => filled($v))->isNotEmpty();
    $clearUrl = $kept ? $action . '?' . http_build_query($kept) : $action;
@endphp
<form method="GET" action="{{ $action }}" class="filter-bar">
    @foreach($kept as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <div class="search-input">
        <i class="fas fa-magnifying-glass"></i>
        <input type="search" name="{{ $search }}" class="form-control form-control-sm"
               placeholder="{{ $placeholder }}" value="{{ request($search) }}" aria-label="{{ $placeholder }}">
    </div>
    {{ $slot }}
    <button class="btn btn-sm btn-primary"><i class="fas fa-filter mr-1"></i> Apply</button>
    @if($filtered)
        <a href="{{ $clearUrl }}" class="btn btn-sm btn-secondary">Clear</a>
    @endif
    @if($count !== null)
        <span class="ml-auto text-muted tw:text-xs">{{ number_format($count) }} {{ Str::plural($noun, $count) }}</span>
    @endif
</form>
