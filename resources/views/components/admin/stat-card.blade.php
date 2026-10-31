{{--
    Summary metric. Tone: primary | success | warning | danger | info | violet | muted.
    <x-admin.stat-card label="Total Ports" :value="42" icon="fa-plug" tone="success" sub="across 15 stations" />
    Pass :href to make it a filter link, :active to highlight it.
--}}
@props(['label', 'value', 'icon' => 'fa-chart-simple', 'tone' => 'primary', 'sub' => null, 'href' => null, 'active' => false])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['stat-card', 'is-active' => $active]) }}>
    <span class="stat-icon tone-{{ $tone }}"><i class="fas {{ $icon }}"></i></span>
    <div class="tw:min-w-0">
        <div class="stat-label">{{ $label }}</div>
        <div class="stat-value">{{ $value }}</div>
        @if($sub)<div class="stat-sub">{{ $sub }}</div>@endif
    </div>
</{{ $tag }}>
