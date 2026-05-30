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
                    <th>Phone</th>
                    <th>Wallet</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <span class="badge badge-{{ $user->role === 'admin' ? 'danger' : ($user->role === 'driver' ? 'success' : 'primary') }}">
                            {{ ucfirst($user->role ?? 'passenger') }}
                        </span>
                    </td>
                    <td>{{ $user->phone ?? '—' }}</td>
                    <td>${{ number_format($user->wallet_balance ?? 0, 2) }}</td>
                    <td>{{ $user->created_at->format('Y-m-d') }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1" onclick="openEdit({
                            id: {{ $user->id }},
                            name: @json($user->name),
                            email: @json($user->email),
                            phone: @json($user->phone ?? ''),
                            role: @json($user->role ?? 'passenger'),
                            wallet_balance: '{{ $user->wallet_balance ?? 0 }}'
                        })"><i class="fas fa-edit"></i></button>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="d-inline"
                              onsubmit="return confirm('Delete user {{ addslashes($user->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $users->links() }}</div>
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add User</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="userForm" method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
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
                            <select name="role" id="f-role" class="form-control" required>
                                <option value="passenger">Passenger</option>
                                <option value="driver">Driver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group" id="wallet-group" style="display:none;">
                        <label>Wallet Balance ($)</label>
                        <input type="number" name="wallet_balance" id="f-wallet" class="form-control" min="0" step="0.01">
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

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Add User';
    document.getElementById('userForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('pw-label').innerHTML = 'Password <span class="text-danger">*</span>';
    document.getElementById('f-password').required = true;
    document.getElementById('wallet-group').style.display = 'none';
    document.getElementById('userForm').reset();
    $('#formModal').modal('show');
}

function openEdit(d) {
    document.getElementById('modalTitle').textContent = 'Edit User';
    document.getElementById('userForm').action = updateBase + d.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('pw-label').innerHTML = 'Password <small class="text-muted">(leave blank to keep)</small>';
    document.getElementById('f-password').required = false;
    document.getElementById('f-name').value = d.name;
    document.getElementById('f-email').value = d.email;
    document.getElementById('f-password').value = '';
    document.getElementById('f-phone').value = d.phone;
    document.getElementById('f-role').value = d.role;
    document.getElementById('f-wallet').value = d.wallet_balance;
    document.getElementById('wallet-group').style.display = 'block';
    $('#formModal').modal('show');
}
</script>
@endpush
