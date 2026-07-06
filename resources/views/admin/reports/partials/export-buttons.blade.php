{{--
    Usage: @include('admin.reports.partials.export-buttons', ['route' => 'admin.export.orders'])
    Optional: pass $extraParams array for additional query params (e.g. ['view' => $view])
--}}
@php
    $qParams = array_merge(request()->only('period'), $extraParams ?? []);
@endphp
<div class="d-flex gap-2 align-items-center ml-auto">
    <span class="text-muted small mr-1"><i class="fas fa-download mr-1"></i>Export:</span>
    <a href="{{ route($route, array_merge($qParams, ['format'=>'excel'])) }}"
       class="btn btn-sm btn-success"
       title="Export to Excel (.xlsx)">
        <i class="fas fa-file-excel mr-1"></i>Excel
    </a>
    <a href="{{ route($route, array_merge($qParams, ['format'=>'pdf'])) }}"
       class="btn btn-sm btn-danger"
       title="Export to PDF">
        <i class="fas fa-file-pdf mr-1"></i>PDF
    </a>
</div>
