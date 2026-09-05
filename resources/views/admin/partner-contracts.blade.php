@extends('admin.layout')
@section('title', 'Partner Contracts')

@section('content')
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1 class="m-0">Partner Contracts</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    <li class="breadcrumb-item active">Partner Contracts</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
<div class="container-fluid">

@if(session('success'))
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        {{ session('success') }}
    </div>
@endif

@include('admin.partials.search-box', [
    'route' => route('admin.partner-contracts'),
    'search' => $search ?? '',
    'placeholder' => 'Search by partner name, phone, or email…',
])

<div class="row">
    {{-- Contract List --}}
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">All Contracts</h3>
                <button class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addModal">
                    <i class="fas fa-plus mr-1"></i>New Contract
                </button>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover mb-0 text-nowrap">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Partner</th>
                            <th>Normal</th>
                            <th>Express</th>
                            <th>+Large</th>
                            <th>+Extra Large</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contracts as $c)
                        <tr>
                            <td class="text-muted small">{{ $c->id }}</td>
                            <td>
                                <div class="font-weight-bold">{{ $c->partner->name ?? '—' }}</div>
                                <small class="text-muted">{{ $c->partner->phone ?? '' }}</small>
                            </td>
                            <td><strong class="text-primary">{{ number_format($c->normal_fee) }} ៛</strong></td>
                            <td><strong class="text-danger">{{ number_format($c->express_fee) }} ៛</strong></td>
                            <td>+ {{ number_format($c->surcharge_large) }} ៛</td>
                            <td>+ {{ number_format($c->surcharge_extra_large) }} ៛</td>
                            <td>
                                @if($c->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-secondary">Inactive</span>
                                @endif
                            </td>
                            <td><small class="text-muted">{{ $c->updated_at->format('d M Y') }}</small></td>
                            <td class="text-nowrap">
                                <button class="btn btn-xs btn-info mr-1"
                                    data-id="{{ $c->id }}"
                                    data-partner="{{ $c->partner->name ?? '' }}"
                                    data-normal="{{ $c->normal_fee }}"
                                    data-express="{{ $c->express_fee }}"
                                    data-large="{{ $c->surcharge_large }}"
                                    data-xl="{{ $c->surcharge_extra_large }}"
                                    data-active="{{ $c->is_active ? '1' : '0' }}"
                                    data-notes="{{ $c->notes }}"
                                    onclick="openEdit(this)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST"
                                      action="{{ route('admin.partner-contracts.destroy', $c) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this contract?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="fas fa-file-contract fa-2x mb-2 d-block"></i>
                                No contracts yet. Click <strong>New Contract</strong> to add one.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($contracts->hasPages())
            <div class="card-footer">{{ $contracts->links() }}</div>
            @endif
        </div>
    </div>

    {{-- Info Panel --}}
    <div class="col-md-4">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-calculator mr-1"></i>Default Pricing</h3></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0 text-center">
                    <thead class="thead-light">
                        <tr><th>Package</th><th class="text-primary">Normal</th><th class="text-danger">Express</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-left">Small / Medium</td>
                            <td class="font-weight-bold text-primary">{{ number_format($defaults['normal_fee']) }} ៛</td>
                            <td class="font-weight-bold text-danger">{{ number_format($defaults['express_fee']) }} ៛</td>
                        </tr>
                        <tr>
                            <td class="text-left">Large</td>
                            <td class="font-weight-bold text-primary">{{ number_format($defaults['normal_fee'] + $defaults['surcharge_large']) }} ៛</td>
                            <td class="font-weight-bold text-danger">{{ number_format($defaults['express_fee'] + $defaults['surcharge_large']) }} ៛</td>
                        </tr>
                        <tr>
                            <td class="text-left">Extra Large</td>
                            <td class="font-weight-bold text-primary">{{ number_format($defaults['normal_fee'] + $defaults['surcharge_extra_large']) }} ៛</td>
                            <td class="font-weight-bold text-danger">{{ number_format($defaults['express_fee'] + $defaults['surcharge_extra_large']) }} ៛</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-2">
                <small class="text-muted">Applied to partners without a custom contract.</small>
                <a href="{{ route('admin.ride-pricing') }}" class="float-right small">Edit defaults</a>
            </div>
        </div>

        <div class="card card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-lightbulb mr-1"></i>How It Works</h3></div>
            <div class="card-body" style="font-size:.85rem">
                <ul class="pl-3 mb-0">
                    <li>Each partner can have <strong>one active contract</strong>.</li>
                    <li>Fee = <code>Normal/Express + size surcharge</code>.</li>
                    <li>Small &amp; Medium → no surcharge.</li>
                    <li>Creating a new contract deactivates the previous one.</li>
                    <li>No contract → uses Ride Pricing defaults above.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

</div>
</section>

{{-- Add Modal --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white"><i class="fas fa-file-contract mr-2"></i>New Partner Contract</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="{{ route('admin.partner-contracts.store') }}">
                @csrf
                <div class="modal-body">
                    @include('admin.partials.contract-form', ['contract' => null, 'partners' => $partners, 'defaults' => $defaults, 'formId' => 'add'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Contract</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <h5 class="modal-title text-white"><i class="fas fa-edit mr-2"></i>Edit Contract — <span id="edit-partner-name"></span></h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="editForm">
                @csrf @method('PUT')
                <div class="modal-body">
                    @include('admin.partials.contract-form', ['contract' => null, 'defaults' => $defaults, 'formId' => 'edit'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-save mr-1"></i>Update Contract</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openEdit(btn) {
    document.getElementById('edit-partner-name').textContent = btn.dataset.partner;
    document.getElementById('editForm').action = '/admin/partner-contracts/' + btn.dataset.id;

    var modal = document.getElementById('editModal');
    modal.querySelector('[name=normal_fee]').value            = btn.dataset.normal;
    modal.querySelector('[name=express_fee]').value           = btn.dataset.express;
    modal.querySelector('[name=surcharge_large]').value       = btn.dataset.large;
    modal.querySelector('[name=surcharge_extra_large]').value = btn.dataset.xl;
    modal.querySelector('[name=is_active]').checked           = btn.dataset.active === '1';
    modal.querySelector('[name=notes]').value                 = btn.dataset.notes || '';

    modal.querySelector('[name=normal_fee]').dispatchEvent(new Event('input', {bubbles: true}));
    $('#editModal').modal('show');
}
</script>
@endpush
@endsection
