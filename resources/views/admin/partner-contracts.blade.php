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
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Partner</th>
                            <th>Base Fee</th>
                            <th>Per Km</th>
                            <th>Surcharge (S/M/L)</th>
                            <th>Min Fee</th>
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
                            <td>{{ number_format($c->base_fee) }} ៛</td>
                            <td>{{ number_format($c->per_km_rate) }} ៛/km</td>
                            <td class="small">
                                {{ number_format($c->surcharge_small) }} /
                                {{ number_format($c->surcharge_medium) }} /
                                {{ number_format($c->surcharge_large) }} ៛
                            </td>
                            <td>{{ number_format($c->min_fee) }} ៛</td>
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
                                    data-base="{{ $c->base_fee }}"
                                    data-perkm="{{ $c->per_km_rate }}"
                                    data-small="{{ $c->surcharge_small }}"
                                    data-medium="{{ $c->surcharge_medium }}"
                                    data-large="{{ $c->surcharge_large }}"
                                    data-min="{{ $c->min_fee }}"
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

    {{-- System Default Rates --}}
    <div class="col-md-4">
        <div class="card card-info">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle mr-1"></i>System Default Rates</h3></div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Partners without a contract use these rates. Configure them in
                    <a href="{{ route('admin.ride-pricing') }}">Ride Pricing → Global Settings</a>.
                </p>
                <table class="table table-sm table-borderless mb-0">
                    <tr><td class="text-muted small">Base Fee</td><td class="font-weight-bold">{{ number_format($defaults['base_fee']) }} ៛</td></tr>
                    <tr><td class="text-muted small">Per Km</td><td class="font-weight-bold">{{ number_format($defaults['per_km_rate']) }} ៛</td></tr>
                    <tr><td class="text-muted small">Surcharge Small</td><td class="font-weight-bold">{{ number_format($defaults['surcharge_small']) }} ៛</td></tr>
                    <tr><td class="text-muted small">Surcharge Medium</td><td class="font-weight-bold">{{ number_format($defaults['surcharge_medium']) }} ៛</td></tr>
                    <tr><td class="text-muted small">Surcharge Large</td><td class="font-weight-bold">{{ number_format($defaults['surcharge_large']) }} ៛</td></tr>
                </table>
            </div>
        </div>

        <div class="card card-warning">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-lightbulb mr-1"></i>How It Works</h3></div>
            <div class="card-body text-sm text-muted" style="font-size:.85rem">
                <ul class="pl-3 mb-0">
                    <li>Each partner can have <strong>one active contract</strong>.</li>
                    <li>When a partner creates an order, the system checks for an active contract first.</li>
                    <li>If no contract → system default pricing applies.</li>
                    <li>Creating a new contract <strong>deactivates</strong> the previous one automatically.</li>
                    <li>Fee formula: <code>base_fee + (km × per_km) + surcharge</code>, rounded up to 100 ៛, minimum = <code>min_fee</code>.</li>
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
                    @include('admin.partials.contract-form', ['contract' => null, 'partners' => $partners, 'defaults' => $defaults])
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
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Base Fee (KHR) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="base_fee" id="e-base" class="form-control" min="0" step="100" required>
                                <div class="input-group-append"><span class="input-group-text">៛</span></div>
                            </div>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Per Km Rate (KHR) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" name="per_km_rate" id="e-perkm" class="form-control" min="0" step="100" required>
                                <div class="input-group-append"><span class="input-group-text">៛/km</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Surcharge — Small</label>
                            <div class="input-group">
                                <input type="number" name="surcharge_small" id="e-small" class="form-control" min="0" step="100">
                                <div class="input-group-append"><span class="input-group-text">៛</span></div>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Surcharge — Medium</label>
                            <div class="input-group">
                                <input type="number" name="surcharge_medium" id="e-medium" class="form-control" min="0" step="100">
                                <div class="input-group-append"><span class="input-group-text">៛</span></div>
                            </div>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Surcharge — Large</label>
                            <div class="input-group">
                                <input type="number" name="surcharge_large" id="e-large" class="form-control" min="0" step="100">
                                <div class="input-group-append"><span class="input-group-text">៛</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Minimum Fee (KHR)</label>
                            <div class="input-group">
                                <input type="number" name="min_fee" id="e-min" class="form-control" min="0" step="100">
                                <div class="input-group-append"><span class="input-group-text">៛</span></div>
                            </div>
                        </div>
                        <div class="form-group col-md-6 d-flex align-items-end">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" name="is_active" id="e-active" value="1">
                                <label class="custom-control-label" for="e-active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="e-notes" class="form-control" rows="2" placeholder="Optional notes about this contract"></textarea>
                    </div>
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
    document.getElementById('e-base').value   = btn.dataset.base;
    document.getElementById('e-perkm').value  = btn.dataset.perkm;
    document.getElementById('e-small').value  = btn.dataset.small;
    document.getElementById('e-medium').value = btn.dataset.medium;
    document.getElementById('e-large').value  = btn.dataset.large;
    document.getElementById('e-min').value    = btn.dataset.min;
    document.getElementById('e-active').checked = btn.dataset.active === '1';
    document.getElementById('e-notes').value  = btn.dataset.notes || '';
    $('#editModal').modal('show');
}
</script>
@endpush
@endsection
