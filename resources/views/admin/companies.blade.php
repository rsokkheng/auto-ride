@extends('admin.layout')
@section('title', 'Companies')
@section('page-title', 'Companies')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Fleet Companies</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add Company
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Platform Fee</th>
                    <th>Company Cut</th>
                    <th>Daily Rental</th>
                    <th>Drivers</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($companies as $c)
                <tr>
                    <td>{{ $c->id }}</td>
                    <td><strong>{{ $c->name }}</strong><br><small class="text-muted">{{ $c->address ?? '' }}</small></td>
                    <td>{{ $c->phone ?? '—' }}</td>
                    <td>{{ $c->email ?? '—' }}</td>
                    <td>{{ $c->platform_commission_rate !== null ? $c->platform_commission_rate.'%' : '<span class="text-muted">default</span>' }}</td>
                    <td>{{ $c->company_commission_rate }}%</td>
                    <td>{{ $c->rental_daily_rate ? number_format($c->rental_daily_rate, 0).' ៛' : '—' }}</td>
                    <td><span class="badge badge-secondary">{{ $c->drivers_count }}</span></td>
                    <td>
                        <span class="badge badge-{{ $c->active ? 'success' : 'secondary' }}">
                            {{ $c->active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1"
                            data-company="{{ e(json_encode([
                                'id'                       => $c->id,
                                'name'                     => $c->name,
                                'phone'                    => $c->phone ?? '',
                                'email'                    => $c->email ?? '',
                                'address'                  => $c->address ?? '',
                                'platform_commission_rate' => $c->platform_commission_rate ?? '',
                                'company_commission_rate'  => $c->company_commission_rate,
                                'rental_daily_rate'        => $c->rental_daily_rate,
                                'active'                   => $c->active,
                            ])) }}"
                            onclick="openEdit(this)"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.companies.destroy', $c) }}" class="d-inline"
                              onsubmit="return confirm('Delete company {{ addslashes($c->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No companies found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $companies->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Company</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="companyForm" method="POST" action="{{ route('admin.companies.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label>Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f-name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" name="active" id="f-active" value="1" checked>
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
                            <input type="number" name="platform_commission_rate" id="f-platform-rate" class="form-control" min="0" max="100" step="0.5" placeholder="{{ config('commission.platform_rate.owner') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Company Cut % <small class="text-muted">(rental/employee)</small></label>
                            <input type="number" name="company_commission_rate" id="f-company-rate" class="form-control" min="0" max="100" step="0.5" value="10">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Daily Rental Fee (KHR ៛)</label>
                            <input type="number" name="rental_daily_rate" id="f-rental-rate" class="form-control" min="0" step="1000" value="0">
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
const storeUrl = '{{ route('admin.companies.store') }}';
const updateBase = '/admin/companies/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add Company';
    document.getElementById('companyForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('companyForm').reset();
    document.getElementById('f-active').checked = true;
    document.getElementById('f-company-rate').value = '10';
    document.getElementById('f-rental-rate').value = '0';
    $('#formModal').modal('show');
}

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
    document.getElementById('f-active').checked = c.active;
    $('#formModal').modal('show');
}
</script>
@endpush
