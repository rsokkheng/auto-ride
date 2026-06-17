@extends('admin.layout')
@section('title', 'Business Accounts')
@section('page-title', 'Business Accounts')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title mb-0"><i class="fas fa-building text-info mr-2"></i> Business Accounts</h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>Company</th>
                    <th>Code</th>
                    <th>Owner</th>
                    <th>Members</th>
                    <th>Credit Limit</th>
                    <th>Used</th>
                    <th>Billing</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accounts as $account)
                <tr>
                    <td>
                        <strong>{{ $account->name }}</strong>
                        @if($account->industry)
                            <br><small class="text-muted">{{ $account->industry }}</small>
                        @endif
                    </td>
                    <td><code>{{ $account->code }}</code></td>
                    <td>
                        {{ $account->owner->name ?? '—' }}<br>
                        <small class="text-muted">{{ $account->owner->email ?? '' }}</small>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-secondary">{{ $account->members_count }}</span>
                    </td>
                    <td>{{ number_format($account->monthly_credit_limit_khr) }} ៛</td>
                    <td>
                        @php $pct = $account->monthly_credit_limit_khr > 0 ? round($account->used_credit_khr / $account->monthly_credit_limit_khr * 100) : 0; @endphp
                        <div class="progress" style="height:6px;min-width:80px">
                            <div class="progress-bar bg-{{ $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success') }}"
                                 style="width:{{ $pct }}%"></div>
                        </div>
                        <small>{{ number_format($account->used_credit_khr) }} ៛ ({{ $pct }}%)</small>
                    </td>
                    <td>{{ ucfirst($account->billing_cycle) }}</td>
                    <td>
                        <span class="badge badge-{{ $account->active ? 'success' : 'secondary' }}">
                            {{ $account->active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('admin.business-accounts.show', $account) }}" class="btn btn-xs btn-outline-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button class="btn btn-xs btn-outline-primary" onclick="openEdit({{ $account->id }}, {{ $account->monthly_credit_limit_khr }}, {{ $account->active ? 1 : 0 }})">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No business accounts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($accounts->hasPages())
    <div class="card-footer">{{ $accounts->links() }}</div>
    @endif
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
