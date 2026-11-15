@extends('admin.layout')
@section('title', 'Roles & Permissions')
@section('page-title', 'Roles & Permissions')

@php
    use App\Support\AdminPermissions as AP;

    $allPerms  = $permissions->all();
    $total     = count($allPerms);
    $grouped   = AP::grouped($allPerms);
    $tones     = ['primary', 'info', 'success', 'warning', 'danger'];
    $toneOf    = fn ($role, $i) => $role->name === AP::SUPER_ADMIN ? 'violet' : $tones[$i % count($tones)];
    $initials  = fn ($name) => mb_strtoupper(collect(preg_split('/\s+/', trim($name)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''));
    $noRole    = $admins->filter(fn ($a) => $a->roles->isEmpty())->count();
    $access    = fn ($admin) => $admin->roles->flatMap(fn ($r) => $r->permissions->pluck('name'))->unique()->values();
@endphp

@section('content')

<x-admin.page-toolbar subtitle="Decide what each admin can do. A role is a set of permissions; give admins one or more roles.">
    <button class="btn btn-primary" onclick="openRole()"><i class="fas fa-plus mr-1"></i> Create Role</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Roles" :value="$roles->count()" icon="fa-user-shield" tone="violet" sub="including Super Admin"
                       :href="route('admin.roles')" :active="$tab === 'roles'" />
    <x-admin.stat-card label="Admin Staff" :value="$admins->count()" icon="fa-users-gear" tone="primary" sub="admin accounts"
                       :href="route('admin.roles', ['tab' => 'staff'])" :active="$tab === 'staff' && ! request('search')" />
    <x-admin.stat-card label="Without a Role" :value="$noRole" icon="fa-user-lock" :tone="$noRole ? 'danger' : 'success'"
                       :sub="$noRole ? 'no access to the admin panel' : 'everyone has a role'" :href="route('admin.roles', ['tab' => 'staff'])" />
    <x-admin.stat-card label="Permissions" :value="$total" icon="fa-key" tone="info" :sub="count($grouped) . ' areas of the admin panel'" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header pb-0">
        <nav class="card-tabs mb-0" style="border-bottom:0;">
            <a href="{{ route('admin.roles') }}" @class(['active' => $tab === 'roles'])><i class="fas fa-user-shield"></i> Roles <span class="count">{{ $roles->count() }}</span></a>
            <a href="{{ route('admin.roles', ['tab' => 'staff']) }}" @class(['active' => $tab === 'staff'])><i class="fas fa-users-gear"></i> Admin staff <span class="count">{{ $admins->count() }}</span></a>
        </nav>
    </div>

    @if($tab === 'roles')
        <div class="card-body" style="background:var(--ink-50);">
            <div class="role-grid">
                @foreach($roles as $i => $role)
                    @php
                        $tone      = $toneOf($role, $i);
                        $names     = $role->permissions->pluck('name');
                        $isSuper   = $role->name === AP::SUPER_ADMIN;
                        $members   = $admins->filter(fn ($a) => $a->roles->contains('id', $role->id))->values();
                        $pct       = $total ? round($names->count() / $total * 100) : 0;
                    @endphp
                    <div @class(['role-card', 'is-protected' => $isSuper])
                         data-role="{{ json_encode(['id' => $role->id, 'name' => $role->name, 'permissions' => $names->all(), 'protected' => $isSuper]) }}">
                        <div class="role-head">
                            <span class="role-icon tone-{{ $tone }}"><i class="fas {{ $isSuper ? 'fa-crown' : 'fa-user-shield' }}"></i></span>
                            <div class="tw:min-w-0 tw:flex-1">
                                <div class="role-name text-truncate" title="{{ $role->name }}">{{ $role->name }}</div>
                                <div class="role-sub">
                                    @if($isSuper) Full access · can't be deleted
                                    @else {{ $members->count() }} {{ Str::plural('admin', $members->count()) }}
                                    @endif
                                </div>
                            </div>
                            @if($isSuper)<span class="status-pill tone-violet">Protected</span>@endif
                        </div>

                        <div class="perm-meter"><span style="width:{{ $pct }}%;"></span></div>
                        <div class="d-flex justify-content-between tw:text-xs text-muted">
                            <span>{{ $names->count() }} of {{ $total }} permissions</span><span>{{ $pct }}%</span>
                        </div>

                        <div class="perm-chips">
                            @forelse($isSuper ? collect() : $names->sortBy(fn ($n) => array_search($n, array_keys(AP::INFO)))->take(6) as $perm)
                                <span class="perm-chip" title="{{ AP::description($perm) }}"><i class="fas {{ AP::icon($perm) }}"></i>{{ AP::label($perm) }}</span>
                            @empty
                                @if($isSuper)
                                    <span class="perm-chip"><i class="fas fa-infinity"></i>Everything in the admin panel</span>
                                @else
                                    <span class="text-muted tw:text-xs"><i class="fas fa-circle-exclamation mr-1"></i>No permissions yet — this role can't open any section.</span>
                                @endif
                            @endforelse
                            @if(! $isSuper && $names->count() > 6)<span class="perm-chip">+{{ $names->count() - 6 }} more</span>@endif
                        </div>

                        <div class="role-foot">
                            <div class="avatar-stack" title="{{ $members->pluck('name')->implode(', ') }}">
                                @forelse($members->take(5) as $m)
                                    <span class="avatar-chip">{{ $initials($m->name) }}</span>
                                @empty
                                    <span class="text-muted tw:text-xs">Nobody has this role</span>
                                @endforelse
                                @if($members->count() > 5)<span class="more">+{{ $members->count() - 5 }}</span>@endif
                            </div>
                            <div class="action-group">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openRole(this.closest('.role-card'))">
                                    <i class="fas {{ $isSuper ? 'fa-eye' : 'fa-pen' }} mr-1"></i>{{ $isSuper ? 'View' : 'Edit' }}
                                </button>
                                @unless($isSuper)
                                    <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="m-0"
                                          onsubmit="return confirm({{ Illuminate\Support\Js::from('Delete the role “' . $role->name . '”? ' . $members->count() . ' admin(s) will lose the access it gives.') }})">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon" title="Delete role" data-toggle="tooltip"><i class="fas fa-trash"></i></button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    </div>
                @endforeach

                <button type="button" class="role-card tw:items-center tw:justify-center text-muted" onclick="openRole()"
                        style="border-style:dashed;background:transparent;min-height:220px;cursor:pointer;">
                    <span class="role-icon tone-muted mb-2"><i class="fas fa-plus"></i></span>
                    <span style="font-weight:600;color:var(--ink-700);">Create a role</span>
                    <span class="tw:text-xs">e.g. Accountant, Dispatcher, Support</span>
                </button>
            </div>
        </div>

        {{-- Who can do what, at a glance --}}
        <div class="card-body p-0 border-top">
            <div class="px-4 pt-3 pb-2 d-flex align-items-center justify-content-between">
                <div><b style="color:var(--ink-900);">Permission matrix</b><div class="text-muted tw:text-xs">Which role opens which part of the admin panel.</div></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm perm-matrix mb-0">
                    <thead>
                        <tr>
                            <th style="min-width:260px;">Permission</th>
                            @foreach($roles as $i => $role)
                                <th><span class="status-pill tone-{{ $toneOf($role, $i) }}">{{ $role->name }}</span></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($grouped as $area => $perms)
                            <tr class="matrix-group"><td colspan="{{ $roles->count() + 1 }}"><i class="fas {{ AP::GROUPS[$area][1] ?? 'fa-key' }} mr-1"></i>{{ AP::GROUPS[$area][0] ?? 'Other' }}</td></tr>
                            @foreach($perms as $perm)
                                <tr>
                                    <td>
                                        <div style="font-weight:600;color:var(--ink-900);"><i class="fas {{ AP::icon($perm) }} mr-2 text-muted" style="width:14px;"></i>{{ AP::label($perm) }}</div>
                                        <div class="text-muted tw:text-xs" style="padding-left:22px;">{{ AP::description($perm) }}</div>
                                    </td>
                                    @foreach($roles as $role)
                                        <td class="text-center">
                                            @if($role->permissions->contains('name', $perm))
                                                <i class="fas fa-circle-check yes" title="{{ $role->name }} can manage {{ AP::label($perm) }}"></i>
                                            @else
                                                <i class="fas fa-minus no"></i>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card-header border-top">
            <x-admin.filter-bar :action="route('admin.roles')" placeholder="Search name or email" :count="$staff->count()" noun="admin" :keep="['tab']" />
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-hover text-nowrap mb-0">
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Roles</th>
                        <th>Access</th>
                        <th>Joined</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($staff as $admin)
                    @php
                        $perms = $access($admin);
                        $isMe  = $admin->id === auth()->id();
                        $row   = ['id' => $admin->id, 'name' => $admin->name, 'roles' => $admin->roles->pluck('id')->all(), 'me' => $isMe];
                    @endphp
                    <tr>
                        <td>
                            <div class="person">
                                <span class="avatar-chip">{{ $initials($admin->name) }}</span>
                                <div class="tw:min-w-0">
                                    <div class="person-name">{{ $admin->name }} @if($isMe)<span class="badge badge-light ml-1">You</span>@endif</div>
                                    <div class="person-sub">{{ $admin->email }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="tw:flex tw:flex-wrap tw:gap-1">
                                @forelse($admin->roles as $r)
                                    @php $ri = $roles->search(fn ($x) => $x->id === $r->id); @endphp
                                    <span class="status-pill tone-{{ $toneOf($r, $ri === false ? 0 : $ri) }}">{{ $r->name }}</span>
                                @empty
                                    <span class="status-pill tone-danger">No role</span>
                                @endforelse
                            </div>
                        </td>
                        <td style="min-width:180px;">
                            @if($perms->isEmpty())
                                <span class="text-danger tw:text-xs"><i class="fas fa-lock mr-1"></i>Can't open the admin panel</span>
                            @else
                                <div class="perm-meter mt-0 mb-1" style="max-width:160px;"><span style="width:{{ round($perms->count() / max(1, $total) * 100) }}%;"></span></div>
                                <div class="text-muted tw:text-xs">{{ $perms->count() === $total ? 'Full access' : $perms->count() . ' of ' . $total . ' permissions' }}</div>
                            @endif
                        </td>
                        <td class="text-muted tw:text-sm">{{ $admin->created_at?->format('d M Y') }}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-row="{{ json_encode($row) }}" onclick="openAssign(JSON.parse(this.dataset.row))">
                                <i class="fas fa-user-tag mr-1"></i>Change roles
                            </button>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row colspan="5" icon="fa-users-gear" title="No admin accounts"
                                       hint="Create an admin from Users, then give them a role here." :keep="['tab']" :clear="route('admin.roles', ['tab' => 'staff'])" />
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Create / edit role --}}
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form id="roleForm" method="POST" action="{{ route('admin.roles.store') }}" class="modal-content">
            @csrf
            <input type="hidden" name="_method" id="roleMethod" value="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="roleModalTitle">Create Role</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 tw:text-sm js-protected" style="display:none;">
                    <i class="fas fa-crown mr-1"></i> Super Admin always has every permission and can't be renamed — it's the way back in if other roles are misconfigured.
                </div>
                <div class="form-group">
                    <label>Role name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="role-name" class="form-control" required maxlength="100" placeholder="e.g. Accountant">
                </div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="mb-0">Permissions</label>
                    <span class="tw:text-xs text-muted"><b id="permCount">0</b> of {{ $total }} selected ·
                        <a href="#" onclick="toggleAll(true);return false;">Select all</a> ·
                        <a href="#" onclick="toggleAll(false);return false;">Clear</a></span>
                </div>
                <div class="perm-picker">
                    @foreach($grouped as $area => $perms)
                        <div class="picker-group">
                            <span><i class="fas {{ AP::GROUPS[$area][1] ?? 'fa-key' }} mr-1"></i>{{ AP::GROUPS[$area][0] ?? 'Other' }}</span>
                            <button type="button" class="js-group-toggle" data-area="{{ $area }}">Select all</button>
                        </div>
                        @foreach($perms as $perm)
                            <label class="perm-option">
                                <input type="checkbox" name="permissions[]" value="{{ $perm }}" class="role-perm" data-area="{{ $area }}">
                                <span>
                                    <span class="opt-title d-block"><i class="fas {{ AP::icon($perm) }} mr-1 text-muted"></i>{{ AP::label($perm) }}</span>
                                    <span class="opt-desc d-block">{{ AP::description($perm) }}</span>
                                </span>
                            </label>
                        @endforeach
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary js-save"><i class="fas fa-save mr-1"></i>Save role</button>
            </div>
        </form>
    </div>
</div>

{{-- Change an admin's roles --}}
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="assignForm" method="POST" action="{{ route('admin.roles.assign') }}" class="modal-content">
            @csrf
            <input type="hidden" name="user_id" id="assign-user-id">
            <div class="modal-header">
                <h5 class="modal-title">Roles for <span id="assign-name"></span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted tw:text-sm mb-2">An admin gets every permission from all their roles. With no role they can't open the admin panel.</p>
                <div class="alert alert-warning py-2 tw:text-sm js-me" style="display:none;">
                    <i class="fas fa-triangle-exclamation mr-1"></i> This is your account — keep a role with <b>Roles & Permissions</b> or you'll lock yourself out of this page.
                </div>
                <div class="perm-picker" style="max-height:none;">
                    @forelse($roles as $i => $role)
                        <label class="perm-option">
                            <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" class="assign-role">
                            <span class="tw:flex-1">
                                <span class="opt-title d-flex align-items-center justify-content-between">
                                    <span><span class="role-icon tone-{{ $toneOf($role, $i) }} mr-2" style="width:24px;height:24px;font-size:.7rem;border-radius:7px;"><i class="fas {{ $role->name === AP::SUPER_ADMIN ? 'fa-crown' : 'fa-user-shield' }}"></i></span>{{ $role->name }}</span>
                                    <span class="tw:text-xs text-muted" style="font-weight:500;">{{ $role->name === AP::SUPER_ADMIN ? 'Full access' : $role->permissions->count() . ' permissions' }}</span>
                                </span>
                                <span class="opt-desc d-block mt-1">{{ $role->name === AP::SUPER_ADMIN ? 'Everything in the admin panel.' : ($role->permissions->map(fn ($p) => AP::label($p->name))->implode(' · ') ?: 'No permissions yet') }}</span>
                            </span>
                        </label>
                    @empty
                        <p class="text-muted mb-0 p-3">No roles yet — create one first.</p>
                    @endforelse
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save roles</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const roleStoreUrl   = @json(route('admin.roles.store'));
const roleUpdateBase = @json(url('admin/roles')) + '/';
const permBoxes = () => Array.from(document.querySelectorAll('.role-perm'));

function syncPermCount() {
    document.getElementById('permCount').textContent = permBoxes().filter(b => b.checked).length;
    document.querySelectorAll('.js-group-toggle').forEach(btn => {
        const group = permBoxes().filter(b => b.dataset.area === btn.dataset.area);
        btn.textContent = group.every(b => b.checked) ? 'Clear' : 'Select all';
    });
}
function toggleAll(on) {
    permBoxes().forEach(b => { if (! b.disabled) b.checked = on; });
    syncPermCount();
}
document.addEventListener('change', e => { if (e.target.classList.contains('role-perm')) syncPermCount(); });
document.querySelectorAll('.js-group-toggle').forEach(btn => btn.addEventListener('click', () => {
    const group = permBoxes().filter(b => b.dataset.area === btn.dataset.area && ! b.disabled);
    const on = ! group.every(b => b.checked);
    group.forEach(b => b.checked = on);
    syncPermCount();
}));

/** card = a .role-card to edit, or nothing to create. */
function openRole(card) {
    const r = card ? JSON.parse(card.dataset.role) : {name: '', permissions: [], protected: false};
    const form = document.getElementById('roleForm');
    form.action = card ? roleUpdateBase + r.id : roleStoreUrl;
    document.getElementById('roleMethod').value = card ? 'PUT' : 'POST';
    document.getElementById('roleModalTitle').textContent = r.protected ? 'Super Admin' : (card ? 'Edit role — ' + r.name : 'Create role');
    const name = document.getElementById('role-name');
    name.value = r.name;
    name.readOnly = r.protected;
    permBoxes().forEach(b => { b.checked = r.protected || r.permissions.includes(b.value); b.disabled = r.protected; b.closest('.perm-option').classList.toggle('is-locked', r.protected); });
    form.querySelector('.js-protected').style.display = r.protected ? '' : 'none';
    form.querySelector('.js-save').style.display = r.protected ? 'none' : '';
    syncPermCount();
    $('#roleModal').modal('show');
}

function openAssign(a) {
    document.getElementById('assign-name').textContent = a.name;
    document.getElementById('assign-user-id').value = a.id;
    document.querySelectorAll('.assign-role').forEach(c => c.checked = a.roles.includes(parseInt(c.value, 10)));
    document.querySelector('#assignModal .js-me').style.display = a.me ? '' : 'none';
    $('#assignModal').modal('show');
}
</script>
@endpush
