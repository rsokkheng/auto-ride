@php
    $currentScope    = $scope    ?? 'company';
    $currentEntityId = $entityId ?? 0;
    $scopePartners   = $partners ?? collect();
    $scopeDrivers    = $drivers  ?? collect();
    $scopeDefs = [
        'company' => ['Company',  'building',   'info'],
        'partner' => ['Partners', 'handshake',  'warning'],
        'driver'  => ['Drivers',  'id-badge',   'success'],
    ];
@endphp
<span class="text-muted mx-2 d-none d-md-inline">|</span>
<span class="font-weight-bold mr-1">By:</span>
@foreach($scopeDefs as $key => [$label, $icon, $color])
<a href="{{ request()->fullUrlWithQuery(['scope' => $key, 'entity_id' => '']) }}"
   class="btn btn-sm {{ $currentScope === $key ? 'btn-'.$color : 'btn-outline-secondary' }} mr-1">
    <i class="fas fa-{{ $icon }} mr-1"></i>{{ $label }}
</a>
@endforeach
@if($currentScope === 'partner' && $scopePartners->isNotEmpty())
<select class="form-control form-control-sm d-inline-block scope-entity-select" style="max-width:170px;">
    <option value="">All Partners</option>
    @foreach($scopePartners as $p)
    <option value="{{ $p->id }}" {{ $currentEntityId == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
    @endforeach
</select>
@endif
@if($currentScope === 'driver' && $scopeDrivers->isNotEmpty())
<select class="form-control form-control-sm d-inline-block scope-entity-select" style="max-width:170px;">
    <option value="">All Drivers</option>
    @foreach($scopeDrivers as $d)
    <option value="{{ $d->id }}" {{ $currentEntityId == $d->id ? 'selected' : '' }}>{{ $d->name }}</option>
    @endforeach
</select>
@endif
@once
@push('scripts')
<script>
document.querySelectorAll('.scope-entity-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('entity_id', this.value || '');
        window.location = url.toString();
    });
});
</script>
@endpush
@endonce
