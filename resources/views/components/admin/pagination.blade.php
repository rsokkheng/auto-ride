{{-- Card footer: "Showing 1–10 of 42" + page links. Renders nothing when there's one page. --}}
@props(['paginator'])
@if($paginator->hasPages())
    <div class="card-footer tw:flex tw:flex-wrap tw:items-center tw:justify-between tw:gap-2">
        <span class="text-muted tw:text-xs">
            Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}
        </span>
        {{ $paginator->onEachSide(1)->withQueryString()->links() }}
    </div>
@endif
