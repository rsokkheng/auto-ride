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
                    <th></th>
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
                    <td>
                        @if($user->avatar)
                            <img src="{{ asset('storage/'.$user->avatar) }}" alt=""
                                 style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                        @else
                            <div style="width:36px;height:36px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-user" style="color:#94a3b8;font-size:.75rem;"></i>
                            </div>
                        @endif
                    </td>
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
                            data-id="{{ $user->id }}"
                            data-name="{{ $user->name }}"
                            data-email="{{ $user->email }}"
                            data-phone="{{ $user->phone ?? '' }}"
                            data-role="{{ $user->role ?? 'passenger' }}"
                            data-driver-type="{{ $user->driver_type ?? '' }}"
                            data-company-id="{{ $user->company_id ?? '' }}"
                            data-salary="{{ $user->salary ?? '' }}"
                            data-commission="{{ $user->commission_rate ?? '' }}"
                            data-wallet="{{ $user->wallet_balance ?? 0 }}"
                            data-avatar="{{ $user->avatar_url ?? '' }}"
                            onclick="openEdit(this)"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                              onsubmit="return confirm('Delete user {{ addslashes($user->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center text-muted py-4">No users found.</td></tr>
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
            <form id="userForm" method="POST" action="{{ route('admin.users.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">

                    {{-- Avatar --}}
                    <div class="form-group text-center" id="avatar-group">
                        <div id="avatar-preview" class="mb-2"></div>
                        <label class="d-block font-weight-bold mb-1">
                            <i class="fas fa-camera mr-1 text-primary"></i> Profile Photo
                        </label>
                        <input type="file" name="avatar" id="f-avatar" class="form-control-file"
                               accept="image/jpeg,image/png,image/webp" onchange="previewAvatar(this)">
                        <small class="text-muted">jpeg / png / webp — max 3 MB</small>
                    </div>
                    <hr>

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
                        <input type="password" name="password" id="f-password" class="form-control">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Phone</label>
                            <input type="text" name="phone" id="f-phone" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Role <span class="text-danger">*</span></label>
                            <select name="role" id="f-role" class="form-control" required onchange="toggleDriverInfo()">
                                <option value="passenger">Passenger</option>
                                <option value="driver">Driver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    {{-- Driver Information — shown only when role = driver --}}
                    <div id="driver-info" style="display:none;">
                        <hr>
                        <p class="font-weight-bold mb-2" style="color:#1e293b;">
                            <i class="fas fa-id-card mr-1 text-primary"></i>
                            Driver Information
                        </p>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Driver Type</label>
                                <select name="driver_type" id="f-driver-type" class="form-control" onchange="toggleSalary()">
                                    <option value="">— Select driver type —</option>
                                    <option value="owner">Own Vehicle (Independent)</option>
                                    <option value="employee">Employee — Company Vehicle</option>
                                    <option value="rental">Rental — Rents from Company</option>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Company</label>
                                <select name="company_id" id="f-company" class="form-control">
                                    <option value="">— No company —</option>
                                    @foreach($companies as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6" id="salary-field" style="display:none;">
                                <label>Monthly Salary (KHR ៛) <small class="text-muted">— employee only</small></label>
                                <input type="number" name="salary" id="f-salary" class="form-control" min="0" step="10000" placeholder="e.g. 500,000">
                            </div>
                            <div class="form-group col-md-6">
                                <label>Commission Rate Override (%) <small class="text-muted">— blank = default</small></label>
                                <input type="number" name="commission_rate" id="f-commission" class="form-control" min="0" max="100" step="0.5"
                                    placeholder="{{ config('commission.platform_rate.owner', 20) }}%">
                            </div>
                        </div>
                    </div>

                    {{-- Wallet balance — edit only --}}
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
const storeUrl = '{{ route("admin.users.store") }}';
const updateBase = '/admin/users/';

function toggleDriverInfo() {
    var isDriver = document.getElementById('f-role').value === 'driver';
    document.getElementById('driver-info').style.display = isDriver ? 'block' : 'none';
    if (!isDriver) {
        document.getElementById('f-driver-type').value = '';
        document.getElementById('f-company').value     = '';
        document.getElementById('f-salary').value      = '';
        document.getElementById('salary-field').style.display = 'none';
    }
}

function toggleSalary() {
    var type = document.getElementById('f-driver-type').value;
    document.getElementById('salary-field').style.display = type === 'employee' ? 'block' : 'none';
}

function previewAvatar(input) {
    var preview = document.getElementById('avatar-preview');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '<img src="' + e.target.result + '" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #e63946;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function openCreate() {
    document.getElementById('modalTitle').textContent  = 'Add User';
    document.getElementById('userForm').action         = storeUrl;
    document.getElementById('formMethod').value        = 'POST';
    document.getElementById('pw-label').textContent    = 'Password *';
    document.getElementById('f-password').required     = true;
    document.getElementById('f-name').value            = '';
    document.getElementById('f-email').value           = '';
    document.getElementById('f-password').value        = '';
    document.getElementById('f-phone').value           = '';
    document.getElementById('f-role').value            = 'passenger';
    document.getElementById('f-driver-type').value     = '';
    document.getElementById('f-company').value         = '';
    document.getElementById('f-salary').value          = '';
    document.getElementById('f-commission').value      = '';
    document.getElementById('driver-info').style.display  = 'none';
    document.getElementById('salary-field').style.display = 'none';
    document.getElementById('wallet-group').style.display = 'none';
    document.getElementById('avatar-group').style.display = 'block';
    document.getElementById('avatar-preview').innerHTML   =
        '<div style="width:80px;height:80px;border-radius:50%;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;">' +
        '<i class="fas fa-user" style="color:#94a3b8;font-size:1.5rem;"></i></div>' +
        '<br><small class="text-muted">No photo selected</small>';
    $('#formModal').modal('show');
}

function openEdit(btn) {
    var isDriver = btn.dataset.role === 'driver';
    document.getElementById('modalTitle').textContent  = 'Edit User #' + btn.dataset.id;
    document.getElementById('userForm').action         = updateBase + btn.dataset.id;
    document.getElementById('formMethod').value        = 'PUT';
    document.getElementById('pw-label').textContent    = 'Password (leave blank to keep)';
    document.getElementById('f-password').required     = false;
    document.getElementById('f-name').value            = btn.dataset.name;
    document.getElementById('f-email').value           = btn.dataset.email;
    document.getElementById('f-password').value        = '';
    document.getElementById('f-phone').value           = btn.dataset.phone;
    document.getElementById('f-role').value            = btn.dataset.role;
    document.getElementById('f-driver-type').value     = btn.dataset.driverType  || '';
    document.getElementById('f-company').value         = btn.dataset.companyId   || '';
    document.getElementById('f-salary').value          = btn.dataset.salary      || '';
    document.getElementById('f-commission').value      = btn.dataset.commission  || '';
    document.getElementById('f-wallet').value          = btn.dataset.wallet      || 0;
    document.getElementById('driver-info').style.display  = isDriver ? 'block' : 'none';
    document.getElementById('salary-field').style.display = btn.dataset.driverType === 'employee' ? 'block' : 'none';
    document.getElementById('wallet-group').style.display = 'block';

    // Show avatar section with current photo preview in edit mode.
    var avatarGroup   = document.getElementById('avatar-group');
    var avatarPreview = document.getElementById('avatar-preview');
    avatarGroup.style.display = 'block';
    avatarPreview.innerHTML   = btn.dataset.avatar
        ? '<img src="' + btn.dataset.avatar + '" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #e63946;" class="mb-1"><br><small class="text-muted">Current photo</small>'
        : '<div style="width:80px;height:80px;border-radius:50%;background:#e2e8f0;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-user" style="color:#94a3b8;font-size:1.5rem;"></i></div><br><small class="text-muted">No photo</small>';

    $('#formModal').modal('show');
}
</script>
@endpush
