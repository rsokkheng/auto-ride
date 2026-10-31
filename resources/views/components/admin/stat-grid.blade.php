{{-- Responsive row of <x-admin.stat-card>s: 2 per row on phones, :cols on wide screens (2–6). --}}
@props(['cols' => 4])
@php
    $wide = [2 => 'tw:lg:grid-cols-2', 3 => 'tw:lg:grid-cols-3', 4 => 'tw:lg:grid-cols-4', 5 => 'tw:lg:grid-cols-5', 6 => 'tw:lg:grid-cols-3 tw:min-[87.5rem]:grid-cols-6'][$cols] ?? 'tw:lg:grid-cols-4';
@endphp
<div {{ $attributes->class(['tw:mb-4 tw:grid tw:grid-cols-2 tw:gap-3', $wide]) }}>
    {{ $slot }}
</div>
