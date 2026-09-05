@extends('admin.layout')
@section('title', $company->name)
@section('page-title', 'Company Detail')

@section('content')

{{-- Back --}}
<div class="mb-3">
    <a href="{{ route('admin.companies') }}" class="btn btn-sm btn-secondary">
        <i class="fas fa-arrow-left mr-1"></i> Back to Companies
    </a>
</div>

{{-- Company Info --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            {{ $company->name }}
            <span class="badge badge-{{ $company->active ? 'success' : 'secondary' }} ml-2">
                {{ $company->active ? 'Active' : 'Inactive' }}
            </span>
        </h3>
        <div>
            <button class="btn btn-sm btn-info mr-1"
                data-company="{{ e(json_encode([
                    'id'                       => $company->id,
                    'name'                     => $company->name,
                    'phone'                    => $company->phone ?? '',
                    'email'                    => $company->email ?? '',
                    'address'                  => $company->address ?? '',
                    'platform_commission_rate' => $company->platform_commission_rate ?? '',
                    'company_commission_rate'  => $company->company_commission_rate,
                    'rental_daily_rate'        => $company->rental_daily_rate,
                    'active'                   => $company->active,
                ])) }}"
                onclick="openEdit(this)"><i class="fas fa-edit mr-1"></i> Edit</button>
            <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" class="d-inline"
                  onsubmit="return confirm({{ Illuminate\Support\Js::from('Delete company ' . $company->name . '?') }})">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-danger"><i class="fas fa-trash mr-1"></i> Delete</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <table class="table table-sm table-borderless mb-0">
                    <tr><th style="width:40%">Phone</th><td>{{ $company->phone ?? '—' }}</td></tr>
                    <tr><th>Email</th><td>{{ $company->email ?? '—' }}</td></tr>
                    <tr><th>Address</th><td>{{ $company->address ?? '—' }}</td></tr>
                    <tr><th>Created</th><td>{{ $company->created_at->format('d M Y') }}</td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <table class="table table-sm table-borderless mb-0">
                    <tr>
                        <th style="width:55%">Platform Fee %</th>
                        <td>{{ $company->platform_commission_rate !== null ? $company->platform_commission_rate.'%' : '<span class="text-muted">default</span>' }}</td>
                    </tr>
                    <tr><th>Company Cut %</th><td>{{ $company->company_commission_rate }}%</td></tr>
                    <tr><th>Daily Rental</th><td>{{ $company->rental_daily_rate ? number_format($company->rental_daily_rate, 0).' ៛' : '—' }}</td></tr>
                    <tr><th>Total Drivers</th><td><span class="badge badge-secondary">{{ $company->drivers_count }}</span></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Drivers --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0">Drivers <span class="badge badge-secondary ml-1">{{ $company->drivers_count }}</span></h3>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Approval</th>
                    <th>Rating</th>
                    <th>Online</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drivers as $d)
                <tr>
                    <td>{{ $d->id }}</td>
                    <td>
                        <a href="{{ route('admin.drivers.show', $d) }}">{{ $d->name }}</a>
                    </td>
                    <td>{{ $d->phone ?? '—' }}</td>
                    <td>{{ $d->email ?? '—' }}</td>
                    <td>
                        @if($d->status_note)
                            <span class="badge badge-warning" title="{{ $d->status_note }}">Note</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @php $ap = $d->approval_status ?? 'pending'; @endphp
                        <span class="badge badge-{{ $ap === 'approved' ? 'success' : ($ap === 'rejected' ? 'danger' : 'warning') }}">
                            {{ ucfirst($ap) }}
                        </span>
                    </td>
                    <td>{{ $d->rating ? number_format($d->rating, 1) : '—' }}</td>
                    <td>
                        <span class="badge badge-{{ $d->available ? 'success' : 'secondary' }}">
                            {{ $d->available ? 'Online' : 'Offline' }}
                        </span>
                    </td>
                    <td>{{ $d->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No drivers in this company.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $drivers->links() }}</div>
</div>

{{-- Edit Modal (reuse same form as list page) --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Edit Company</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="companyForm" method="POST" action="">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="PUT">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label>Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f-name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" name="active" id="f-active" value="1">
                                <label class="custom-control-label" for="f-active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Phone</label>
                            <input type="text" name="phone" id="f-phone" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Email</label>
                            <input type="email" name="email" id="f-email" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Address</label>
                            <input type="text" name="address" id="f-address" class="form-control">
                        </div>
                    </div>
                    <hr>
                    <p class="text-muted small mb-2"><i class="fas fa-percentage mr-1"></i> Commission Settings</p>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Platform Fee % <small class="text-muted">(blank = default)</small></label>
                            <input type="number" name="platform_commission_rate" id="f-platform-rate" class="form-control" min="0" max="100" step="0.5">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Company Cut %</label>
                            <input type="number" name="company_commission_rate" id="f-company-rate" class="form-control" min="0" max="100" step="0.5">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Daily Rental Fee (KHR ៛)</label>
                            <input type="number" name="rental_daily_rate" id="f-rental-rate" class="form-control" min="0" step="1000">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const updateBase = '/admin/companies/';

function openEdit(btn) {
    const c = JSON.parse(btn.getAttribute('data-company'));
    document.getElementById('modalTitle').textContent = 'Edit Company #' + c.id;
    document.getElementById('companyForm').action = updateBase + c.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-name').value = c.name;
    document.getElementById('f-phone').value = c.phone;
    document.getElementById('f-email').value = c.email;
    document.getElementById('f-address').value = c.address;
    document.getElementById('f-platform-rate').value = c.platform_commission_rate;
    document.getElementById('f-company-rate').value = c.company_commission_rate;
    document.getElementById('f-rental-rate').value = c.rental_daily_rate;
    document.getElementById('f-active').checked = !!c.active;
    $('#formModal').modal('show');
}
</script>
@endpush
