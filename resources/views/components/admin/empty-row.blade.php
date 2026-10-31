{{-- Empty-state table row. Shows a "clear filters" hint when the list is filtered. --}}
@props(['colspan', 'icon' => 'fa-inbox', 'title' => 'Nothing here yet', 'hint' => null, 'clear' => null])
@php $filtered = collect(request()->except(['page']))->filter(fn ($v) => filled($v))->isNotEmpty(); @endphp
<tr>
    <td colspan="{{ $colspan }}">
        <div class="empty-state">
            <div class="empty-icon"><i class="fas {{ $filtered ? 'fa-filter-circle-xmark' : $icon }}"></i></div>
            <h6>{{ $filtered ? 'No results match these filters' : $title }}</h6>
            <p class="mb-0 tw:text-sm">
                @if($filtered && $clear)
                    Try another search or <a href="{{ $clear }}">clear the filters</a>.
                @else
                    {{ $hint }}
                @endif
            </p>
        </div>
    </td>
</tr>
