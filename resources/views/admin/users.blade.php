@extends('admin.layout')
@section('title', 'Users')
@section('page-title', 'Users')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">User list</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()">
            <i class="fas fa-plus mr-1"></i> Add User
        </button>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Driver Type</th>
                    <th>Company</th>
                    <th>Phone</th>
                    <th>Wallet</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                @php
                    $dtColor = ['owner' => 'success', 'rental' => 'warning', 'employee' => 'info'];
                    $dtLabel = ['owner' => 'Own Vehicle', 'rental' => 'Rental', 'employee' => 'Employee'];
                @endphp
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="badge badge-{{ $user->role === 'admin' ? 'danger' : ($user->role === 'driver' ? 'success' : 'primary') }}">
                            {{ ucfirst($user->role ?? 'passenger') }}
                        </span>
                    </td>
                    <td>
                        @if($user->role === 'driver' && $user->driver_type)
                            <span class="badge badge-{{ $dtColor[$user->driver_type] ?? 'secondary' }}">
                                {{ $dtLabel[$user->driver_type] ?? ucfirst($user->driver_type) }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $user->company?->name ?? '—' }}</td>
                    <td>{{ $user->phone ?? '—' }}</td>
                    <td>{{ number_format($user->wallet_balance ?? 0, 0) }} ៛</td>
                    <td>{{ $user->created_at->format('Y-m-d') }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1"
                            data-user="{{ e(json_encode([
                                'id'              => $user->id,
                                'name'            => $user->name,
                                'email'           => $user->email,
                                'phone'           => $user->phone ?? '',
                                'role'            => $user->role ?? 'passenger',
                                'driver_type'     => $user->driver_type ?? '',
                                'company_id'      => $user->company_id ?? '',
                                'salary'          => $user->salary ?? 0,
                                'commission_rate' => $user->commission_rate ?? '',
                                'wallet_balance'  => $user->wallet_balance ?? 0,
                            ])) }}"
                            onclick="openEdit(this)"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                              onsubmit="return confirm('Delete user {{ addslashes($user->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $users->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add User</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="userForm" method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">

                    {{-- Basic info --}}
                    <div class="form-group">
                        <label>Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="f-name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="f-email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label id="pw-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" id="f-password" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Phone</label>
                            <input type="text" name="phone" id="f-phone" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Role <span class="text-danger">*</span></label>
                            <select name="role" id="f-role" class="form-control" required onchange="toggleDriverFields()">
                                <option value="passenger">Passenger</option>
                                <option value="driver">Driver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    {{-- Driver-only section --}}
                    <div id="driver-section" style="display:none;">
                        <hr>
                        <p class="font-weight-bold text-sm mb-2" style="color:#1e293b;">
                            <i class="fas fa-id-card mr-1 text-primary"></i> Driver Details
                        </p>

                        {{-- Driver type --}}
                        <div class="form-group">
                            <label>Driver Type <span class="text-danger">*</span></label>
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="border rounded p-2 d-block text-center" id="lbl-owner" style="cursor:pointer;">
                                        <input type="radio" name="driver_type" value="owner" id="dt-owner" onchange="toggleDriverType()">
                                        <div class="mt-1"><i class="fas fa-car fa-lg text-success"></i></div>
                                        <div class="font-weight-bold mt-1" style="font-size:.85rem;">Own Vehicle</div>
                                        <small class="text-muted">Pays platform fee per trip</small>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="border rounded p-2 d-block text-center" id="lbl-employee" style="cursor:pointer;">
                                        <input type="radio" name="driver_type" value="employee" id="dt-employee" onchange="toggleDriverType()">
                                        <div class="mt-1"><i class="fas fa-user-tie fa-lg text-info"></i></div>
                                        <div class="font-weight-bold mt-1" style="font-size:.85rem;">Employee</div>
                                        <small class="text-muted">Salary-based, company vehicle</small>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="border rounded p-2 d-block text-center" id="lbl-rental" style="cursor:pointer;">
                                        <input type="radio" name="driver_type" value="rental" id="dt-rental" onchange="toggleDriverType()">
                                        <div class="mt-1"><i class="fas fa-key fa-lg text-warning"></i></div>
                                        <div class="font-weight-bold mt-1" style="font-size:.85rem;">Rental</div>
                                        <small class="text-muted">Rents vehicle from company</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Company (employee/rental only) --}}
                        <div id="company-section" style="display:none;">
                            <div class="form-group">
                                <label>Company</label>
                                <select name="company_id" id="f-company" class="form-control">
                                    <option value="">— No company —</option>
                                    @foreach($companies as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Salary (employee only) --}}
                        <div id="salary-section" style="display:none;">
                            <div class="form-group">
                                <label>Monthly Salary (KHR ៛)</label>
                                <input type="number" name="salary" id="f-salary" class="form-control" min="0" step="10000" placeholder="e.g. 500000">
                            </div>
                        </div>

                        {{-- Commission override --}}
                        <div class="form-group">
                            <label>
                                Platform Commission Rate Override (%)
                                <small class="text-muted ml-1">— leave blank to use default</small>
                            </label>
                            <input type="number" name="commission_rate" id="f-commission" class="form-control" min="0" max="100" step="0.5" placeholder="Default: {{ config('commission.platform_rate.owner', 20) }}%">
                        </div>
                    </div>

                    {{-- Wallet (edit only) --}}
                    <div class="form-group" id="wallet-group" style="display:none;">
                        <label>Wallet Balance (KHR ៛)</label>
                        <input type="number" name="wallet_balance" id="f-wallet" class="form-control" min="0" step="100">
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
const storeUrl = '{{ route('admin.users.store') }}';
const updateBase = '/admin/users/';

function toggleDriverFields() {
    const isDriver = document.getElementById('f-role').value === 'driver';
    document.getElementById('driver-section').style.display = isDriver ? 'block' : 'none';
    if (!isDriver) {
        document.querySelectorAll('input[name=driver_type]').forEach(r => r.checked = false);
        highlightType(null);
    }
    toggleDriverType();
}

function toggleDriverType() {
    const type = document.querySelector('input[name=driver_type]:checked')?.value ?? '';
    const needsCompany = type === 'employee' || type === 'rental';
    const needsSalary  = type === 'employee';
    document.getElementById('company-section').style.display = needsCompany ? 'block' : 'none';
    document.getElementById('salary-section').style.display  = needsSalary  ? 'block' : 'none';
    highlightType(type);
}

function highlightType(type) {
    ['owner', 'employee', 'rental'].forEach(t => {
        const lbl = document.getElementById('lbl-' + t);
        if (lbl) lbl.style.borderColor = (t === type) ? '#007bff' : '';
        if (lbl) lbl.style.background  = (t === type) ? '#f0f7ff' : '';
    });
}

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('userForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('pw-label').innerHTML = 'Password <span class="text-danger">*</span>';
    document.getElementById('f-password').required = true;
    document.getElementById('wallet-group').style.display = 'none';
    document.getElementById('driver-section').style.display = 'none';
    document.getElementById('company-section').style.display = 'none';
    document.getElementById('salary-section').style.display = 'none';
    document.getElementById('userForm').reset();
    highlightType(null);
    $('#formModal').modal('show');
}

function openEdit(btn) {
    const d = JSON.parse(btn.getAttribute('data-user'));
    document.getElementById('modalTitle').textContent = 'Edit User #' + d.id;
    document.getElementById('userForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('pw-label').innerHTML = 'Password <small class="text-muted">(leave blank to keep)</small>';
    document.getElementById('f-password').required = false;
    document.getElementById('f-name').value    = d.name;
    document.getElementById('f-email').value   = d.email;
    document.getElementById('f-password').value = '';
    document.getElementById('f-phone').value   = d.phone;
    document.getElementById('f-role').value    = d.role;
    document.getElementById('f-wallet').value  = d.wallet_balance;
    document.getElementById('wallet-group').style.display = 'block';

    const isDriver = d.role === 'driver';
    document.getElementById('driver-section').style.display = isDriver ? 'block' : 'none';

    if (isDriver) {
        const radio = document.getElementById('dt-' + d.driver_type);
        if (radio) radio.checked = true;
        document.getElementById('f-company').value    = d.company_id || '';
        document.getElementById('f-salary').value     = d.salary || '';
        document.getElementById('f-commission').value = d.commission_rate || '';
        toggleDriverType();
    } else {
        document.querySelectorAll('input[name=driver_type]').forEach(r => r.checked = false);
        highlightType(null);
    }

    $('#formModal').modal('show');
}
</script>
@endpush
