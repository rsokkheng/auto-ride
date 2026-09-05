@extends('admin.layout')
@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
    <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-user-shield text-primary mr-2"></i>Roles</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreateRole()"><i class="fas fa-plus mr-1"></i> Create Role</button>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Role</th>
                    <th>Permissions</th>
                    <th>Admins with this role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($roles as $role)
                <tr>
                    <td class="font-weight-bold">
                        {{ $role->name }}
                        @if($role->name === 'Super Admin')
                            <span class="badge badge-warning ml-1">Protected</span>
                        @endif
                    </td>
                    <td>
                        @forelse($role->permissions as $perm)
                            <span class="badge badge-info mr-1 mb-1">{{ $perm->name }}</span>
                        @empty
                            <span class="text-muted">No permissions</span>
                        @endforelse
                    </td>
                    <td>{{ $admins->filter(fn($a) => $a->roles->contains('id', $role->id))->count() }}</td>
                    <td class="text-nowrap">
                        <button class="btn btn-xs btn-info mr-1"
                            onclick="openEditRole({{ $role->id }}, {{ Illuminate\Support\Js::from($role->name) }}, {{ Illuminate\Support\Js::from($role->permissions->pluck('name')->all()) }})">
                            <i class="fas fa-edit"></i>
                        </button>
                        @if($role->name !== 'Super Admin')
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="d-inline"
                              onsubmit="return confirm({{ Illuminate\Support\Js::from('Delete role \"' . $role->name . '\"? Admins with only this role will lose all admin permissions.') }})">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No roles yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="fas fa-users-cog text-primary mr-2"></i>Assign Roles to Admin Staff</h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Admin</th>
                    <th>Current Roles</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($admins as $admin)
                <tr>
                    <td>
                        <div class="font-weight-bold">{{ $admin->name }}</div>
                        <small class="text-muted">{{ $admin->email }}</small>
                    </td>
                    <td>
                        @forelse($admin->roles as $r)
                            <span class="badge badge-primary mr-1 mb-1">{{ $r->name }}</span>
                        @empty
                            <span class="badge badge-secondary">No role — no admin panel access</span>
                        @endforelse
                    </td>
                    <td>
                        <button class="btn btn-xs btn-info"
                            onclick="openAssign({{ $admin->id }}, {{ Illuminate\Support\Js::from($admin->name) }}, {{ Illuminate\Support\Js::from($admin->roles->pluck('id')->all()) }})">
                            <i class="fas fa-user-tag mr-1"></i>Assign Roles
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center text-muted py-4">No admin accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Create / Edit Role Modal --}}
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleModalTitle">Create Role</h5>
                <button type="button" class="close" onclick="hideModal('roleModal')"><span>&times;</span></button>
            </div>
            <form id="roleForm" method="POST" action="{{ route('admin.roles.store') }}">
                @csrf
                <input type="hidden" name="_method" id="roleMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Role Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="role-name" class="form-control" required maxlength="100" placeholder="e.g. Support Staff">
                    </div>
                    <div class="form-group mb-0">
                        <label>Permissions</label>
                        <div class="border rounded p-2" style="max-height:260px;overflow-y:auto;">
                            @foreach($permissions as $perm)
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input role-perm-check" name="permissions[]"
                                       value="{{ $perm->name }}" id="perm-{{ $perm->id }}">
                                <label class="custom-control-label" for="perm-{{ $perm->id }}">{{ $perm->name }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('roleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Assign Roles Modal --}}
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Roles — <span id="assign-admin-name"></span></h5>
                <button type="button" class="close" onclick="hideModal('assignModal')"><span>&times;</span></button>
            </div>
            <form id="assignForm" method="POST" action="{{ route('admin.roles.assign') }}">
                @csrf
                <input type="hidden" name="user_id" id="assign-user-id">
                <div class="modal-body">
                    @if($roles->isEmpty())
                        <p class="text-muted mb-0">No roles exist yet — create one first.</p>
                    @endif
                    @foreach($roles as $role)
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input assign-role-check" name="role_ids[]"
                               value="{{ $role->id }}" id="assign-role-{{ $role->id }}">
                        <label class="custom-control-label" for="assign-role-{{ $role->id }}">{{ $role->name }}</label>
                    </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal('assignModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function showModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.add('show');
    el.style.display = 'block';
    el.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    if (!document.getElementById(id + '-backdrop')) {
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = id + '-backdrop';
        backdrop.onclick = function () { hideModal(id); };
        document.body.appendChild(backdrop);
    }
}

function hideModal(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('show');
    el.style.display = 'none';
    document.body.classList.remove('modal-open');
    var backdrop = document.getElementById(id + '-backdrop');
    if (backdrop) backdrop.remove();
}

const roleStoreUrl = '{{ route("admin.roles.store") }}';
const roleUpdateBase = '{{ url("admin/roles") }}/';

function openCreateRole() {
    document.getElementById('roleModalTitle').textContent = 'Create Role';
    document.getElementById('roleForm').action = roleStoreUrl;
    document.getElementById('roleMethod').value = 'POST';
    document.getElementById('roleForm').reset();
    showModal('roleModal');
}

function openEditRole(id, name, permissions) {
    document.getElementById('roleModalTitle').textContent = 'Edit Role';
    document.getElementById('roleForm').action = roleUpdateBase + id;
    document.getElementById('roleMethod').value = 'PUT';
    document.getElementById('role-name').value = name;
    document.querySelectorAll('.role-perm-check').forEach(function (c) {
        c.checked = permissions.includes(c.value);
    });
    showModal('roleModal');
}

function openAssign(userId, userName, roleIds) {
    document.getElementById('assign-admin-name').textContent = userName;
    document.getElementById('assign-user-id').value = userId;
    document.querySelectorAll('.assign-role-check').forEach(function (c) {
        c.checked = roleIds.includes(parseInt(c.value, 10));
    });
    showModal('assignModal');
}
</script>
@endpush
