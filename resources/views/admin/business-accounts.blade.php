@extends('admin.layout')
@section('title', 'Business Accounts')
@section('page-title', 'Business Accounts')

@section('content')

<x-admin.page-toolbar subtitle="Companies that book rides for their staff on a monthly credit line." />

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Business Accounts" :value="number_format($stats['total'])" icon="fa-briefcase" tone="primary"
                       :href="route('admin.business-accounts')" :active="!request('status')" />
    <x-admin.stat-card label="Active" :value="number_format($stats['active'])" icon="fa-circle-check" tone="success"
                       :href="route('admin.business-accounts', ['status' => 'active'])" :active="request('status') === 'active'" />
    <x-admin.stat-card label="Credit Extended" :value="number_format($stats['limit']) . ' ៛'" icon="fa-credit-card" tone="info" sub="monthly limits, active accounts" />
    <x-admin.stat-card label="Credit Used" :value="number_format($stats['used']) . ' ៛'" icon="fa-chart-pie" tone="violet"
                       :sub="$stats['limit'] ? round($stats['used'] / $stats['limit'] * 100) . '% of the limit' : '—'" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.business-accounts')" placeholder="Search account, contact name, phone or email" :count="$accounts->total()" noun="account">
            <x-admin.filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" all="All statuses" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Owner</th>
                    <th class="text-center">Members</th>
                    <th>Credit Used</th>
                    <th>Billing</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($accounts as $account)
                @php $pct = $account->monthly_credit_limit_khr > 0 ? min(100, round($account->used_credit_khr / $account->monthly_credit_limit_khr * 100)) : 0; @endphp
                <tr>
                    <td>
                        <a href="{{ route('admin.business-accounts.show', $account) }}" style="font-weight:600;">{{ $account->name }}</a>
                        <div class="text-muted tw:text-xs"><code>{{ $account->code }}</code>@if($account->industry) · {{ $account->industry }}@endif</div>
                    </td>
                    <td>
                        <div>{{ $account->owner->name ?? '—' }}</div>
                        <div class="text-muted tw:text-xs">{{ $account->owner->email ?? '' }}</div>
                    </td>
                    <td class="text-center"><span class="badge badge-secondary"><i class="fas fa-users mr-1"></i>{{ $account->members_count }}</span></td>
                    <td style="min-width:170px;">
                        <div class="tw:text-xs"><b style="color:var(--ink-900);">{{ number_format($account->used_credit_khr) }}</b> / {{ number_format($account->monthly_credit_limit_khr) }} ៛</div>
                        <div style="height:5px;border-radius:9px;background:var(--ink-100);margin-top:4px;overflow:hidden;">
                            <span style="display:block;height:100%;width:{{ $pct }}%;background:{{ $pct >= 90 ? 'var(--danger)' : ($pct >= 70 ? 'var(--warning)' : 'var(--success)') }};"></span>
                        </div>
                        <div class="text-muted tw:text-xs">{{ $pct }}% used</div>
                    </td>
                    <td>{{ ucfirst($account->billing_cycle) }}</td>
                    <td><span class="status-pill tone-{{ $account->active ? 'success' : 'muted' }}">{{ $account->active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-center">
                        <x-admin.row-actions :edit="'openEdit(' . $account->id . ', ' . (int) $account->monthly_credit_limit_khr . ', ' . ($account->active ? 1 : 0) . ')'">
                            <a href="{{ route('admin.business-accounts.show', $account) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="View" data-toggle="tooltip"><i class="fas fa-eye"></i></a>
                        </x-admin.row-actions>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="7" icon="fa-briefcase" title="No business accounts yet"
                                   hint="Accounts appear here when a company signs up in the app." :clear="route('admin.business-accounts')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$accounts" />
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="editForm" method="POST">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Business Account</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Monthly Credit Limit (KHR ៛)</label>
                        <input type="number" name="monthly_credit_limit_khr" id="editCreditLimit" class="form-control" min="0" required>
                    </div>
                    <div class="form-group">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="editActive" name="active" value="1">
                            <label class="custom-control-label" for="editActive">Active</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </div>
        </form>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show position-fixed" style="bottom:20px;right:20px;z-index:9999">
    {{ session('success') }}<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
@endif
@endsection

@push('scripts')
<script>
function openEdit(id, creditLimit, active) {
    document.getElementById('editForm').action = '/admin/business-accounts/' + id;
    document.getElementById('editCreditLimit').value = creditLimit;
    document.getElementById('editActive').checked = active === 1;
    $('#editModal').modal('show');
}
</script>
@endpush
