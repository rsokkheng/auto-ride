{{--
    Subtitle + primary actions above a list page.
    <x-admin.page-toolbar subtitle="EV charging points shown in the app.">
        <button class="btn btn-primary">…</button>
    </x-admin.page-toolbar>
--}}
@props(['subtitle' => null])
<div class="page-toolbar">
    <p class="page-subtitle">{{ $subtitle }}</p>
    @if(trim($slot))
        <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-2">{{ $slot }}</div>
    @endif
</div>
