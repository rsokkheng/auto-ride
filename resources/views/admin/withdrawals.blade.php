@extends('admin.layout')
@section('title', 'Withdrawal Requests')
@section('page-title', 'Withdrawal Requests')

@section('content')

@php $exportQuery = array_merge(['status' => $status], array_filter(['search' => $search, 'method' => $method, 'date_from' => $dateFrom, 'date_to' => $dateTo])); @endphp
<x-admin.page-toolbar subtitle="Drivers cashing out their wallet balance. Pay the driver first, then approve to deduct it from their wallet.">
    <a href="{{ route('admin.withdrawals.export', $exportQuery) }}" class="btn btn-secondary"><i class="fas fa-file-csv mr-1"></i> Export CSV</a>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Pending" :value="number_format($counts['pending'])" icon="fa-hourglass-half" tone="warning"
                       :sub="number_format($amounts['pending']) . ' ៛ requested'" :href="route('admin.withdrawals', ['status' => 'pending'])" :active="$status === 'pending'" />
    <x-admin.stat-card label="Approved" :value="number_format($counts['approved'])" icon="fa-circle-check" tone="success"
                       :href="route('admin.withdrawals', ['status' => 'approved'])" :active="$status === 'approved'" />
    <x-admin.stat-card label="Rejected" :value="number_format($counts['rejected'])" icon="fa-circle-xmark" tone="danger"
                       :href="route('admin.withdrawals', ['status' => 'rejected'])" :active="$status === 'rejected'" />
    <x-admin.stat-card label="Paid Out This Month" :value="number_format($amounts['approved_month']) . ' ៛'" icon="fa-money-bill-transfer" tone="violet"
                       :sub="'≈ $' . number_format($amounts['approved_month'] / 4000, 2)" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.withdrawals')" placeholder="Driver name or phone" :count="$withdrawals->total()" :noun="$status . ' request'">
            <input type="hidden" name="status" value="{{ $status }}">
            <x-admin.filter-select name="method" :options="['bank_transfer' => 'Bank transfer', 'aba' => 'ABA', 'wing' => 'Wing', 'acleda' => 'ACLEDA']" all="All methods" />
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom }}" title="From" aria-label="From date" style="min-width:0;width:145px;">
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo }}" title="To" aria-label="To date" style="min-width:0;width:145px;">
        </x-admin.filter-bar>
    </div>

    {{-- Bulk action bar (pending only) --}}
    @if($status === 'pending')
    <div class="card-body py-2" id="bulkBar" style="display:none;background:var(--primary-soft);border-bottom:1px solid var(--ink-200);">
        <div class="d-flex align-items-center" style="gap:8px">
            <span class="text-muted small"><span id="selectedCount">0</span> selected</span>
            <button type="button" class="btn btn-sm btn-success" onclick="openBulkApprove()">
                <i class="fas fa-check mr-1"></i>Bulk Approve
            </button>
            <button type="button" class="btn btn-sm btn-danger" onclick="openBulkReject()">
                <i class="fas fa-times mr-1"></i>Bulk Reject
            </button>
            <button type="button" class="btn btn-sm btn-link text-muted" onclick="clearSelection()">Clear</button>
        </div>
    </div>
    @endif

    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    @if($status === 'pending')
                    <th style="width:36px">
                        <input type="checkbox" id="selectAll" title="Select all on this page">
                    </th>
                    @endif
                    <th>Driver</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Account Details</th>
                    <th>Requested</th>
                    @if($status !== 'pending')
                        <th>Processed By</th>
                        <th>Admin Note</th>
                    @endif
                    @if($status === 'pending')
                        <th>Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($withdrawals as $w)
                <tr id="row-{{ $w->id }}">
                    @if($status === 'pending')
                    <td>
                        <input type="checkbox" class="row-check" value="{{ $w->id }}">
                    </td>
                    @endif
                    <td>
                        <div style="font-weight:600;color:var(--ink-900);">{{ $w->driver->name }}</div>
                        <div class="text-muted tw:text-xs">{{ $w->driver->phone }} · wallet {{ number_format($w->driver->wallet_balance) }} ៛</div>
                    </td>
                    <td>
                        <div style="font-weight:700;color:var(--ink-900);">{{ number_format($w->amount_khr) }} ៛</div>
                        <div class="text-muted tw:text-xs">≈ ${{ number_format($w->amount_khr / 4000, 2) }}</div>
                    </td>
                    <td><span class="badge badge-light">{{ ['bank_transfer' => 'Bank transfer', 'aba' => 'ABA', 'wing' => 'Wing', 'acleda' => 'ACLEDA'][$w->payment_method] ?? strtoupper(str_replace('_', ' ', $w->payment_method)) }}</span></td>
                    <td>
                        <div>{{ $w->account_name ?? '—' }}</div>
                        <div class="text-muted tw:text-xs">{{ $w->account_number ?? '—' }}@if($w->bank_name) · {{ $w->bank_name }}@endif</div>
                    </td>
                    <td>
                        <div>{{ $w->created_at->format('d M Y') }}</div>
                        <div class="text-muted tw:text-xs">{{ $w->created_at->format('H:i') }} · {{ $w->created_at->diffForHumans() }}</div>
                    </td>
                    @if($status !== 'pending')
                        <td>
                            @if($w->processor)
                                <div>{{ $w->processor->name }}</div>
                                <small class="text-muted">{{ $w->processed_at?->format('d M Y H:i') }}</small>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><div class="text-muted text-truncate tw:text-xs" style="max-width:200px;" title="{{ $w->admin_note }}">{{ $w->admin_note ?? '—' }}</div></td>
                    @endif
                    @if($status === 'pending')
                        <td class="text-nowrap">
                            <div class="action-group">
                            <button class="btn btn-sm btn-success"
                                onclick="openApprove({{ $w->id }}, {{ Illuminate\Support\Js::from($w->driver->name) }}, {{ $w->amount_khr }})">
                                <i class="fas fa-check mr-1"></i>Approve
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                onclick="openReject({{ $w->id }}, {{ Illuminate\Support\Js::from($w->driver->name) }})">
                                <i class="fas fa-xmark mr-1"></i>Reject
                            </button>
                            </div>
                        </td>
                    @endif
                </tr>
                @empty
                <x-admin.empty-row colspan="10" icon="fa-money-bill-transfer"
                                   :title="$status === 'pending' ? 'No withdrawals waiting' : 'No ' . $status . ' withdrawals'"
                                   :hint="$status === 'pending' ? 'Driver cash-out requests will appear here.' : null"
                                   :clear="route('admin.withdrawals', ['status' => $status])" />
                @endforelse
            </tbody>
        </table>
    </div>

    <x-admin.pagination :paginator="$withdrawals" />
</div>

{{-- Single Approve modal --}}
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle mr-2"></i>Approve Withdrawal</h5>
                <button type="button" class="close text-white" onclick="hideModal(this.closest('.modal').id)"><span>&times;</span></button>
            </div>
            <form id="approveForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p id="approveText" class="mb-3"></p>
                    <p class="text-muted small mb-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        The driver's wallet has already been debited. Approving marks this request as paid out.
                    </p>
                    <div class="form-group mb-0">
                        <label>Reference / Note <small class="text-muted">(optional)</small></label>
                        <input type="text" name="admin_note" class="form-control"
                               placeholder="Transaction reference, receipt number, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal(this.closest('.modal').id)">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i>Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Single Reject modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Reject Withdrawal</h5>
                <button type="button" class="close text-white" onclick="hideModal(this.closest('.modal').id)"><span>&times;</span></button>
            </div>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="modal-body">
                    <p id="rejectText" class="mb-3"></p>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-undo mr-1"></i>
                        The held amount will be <strong>returned to the driver's wallet</strong>.
                    </div>
                    <div class="form-group mb-0">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea name="admin_note" class="form-control" rows="2" required
                                  placeholder="Why is this being rejected?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal(this.closest('.modal').id)">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i>Reject &amp; Refund</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Approve modal --}}
<div class="modal fade" id="bulkApproveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-double mr-2"></i>Bulk Approve Withdrawals</h5>
                <button type="button" class="close text-white" onclick="hideModal(this.closest('.modal').id)"><span>&times;</span></button>
            </div>
            <form id="bulkApproveForm" method="POST" action="{{ route('admin.withdrawals.bulk-approve') }}">
                @csrf
                <div id="bulkApproveIds"></div>
                <div class="modal-body">
                    <p>Approve <strong id="bulkApproveCount"></strong> withdrawal(s)?</p>
                    <p class="text-muted small"><i class="fas fa-info-circle mr-1"></i>All selected drivers' wallets have already been debited.</p>
                    <div class="form-group mb-0">
                        <label>Reference / Note <small class="text-muted">(optional, applies to all)</small></label>
                        <input type="text" name="admin_note" class="form-control" placeholder="Batch reference, date, etc.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal(this.closest('.modal').id)">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check mr-1"></i>Approve All Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Reject modal --}}
<div class="modal fade" id="bulkRejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle mr-2"></i>Bulk Reject Withdrawals</h5>
                <button type="button" class="close text-white" onclick="hideModal(this.closest('.modal').id)"><span>&times;</span></button>
            </div>
            <form id="bulkRejectForm" method="POST" action="{{ route('admin.withdrawals.bulk-reject') }}">
                @csrf
                <div id="bulkRejectIds"></div>
                <div class="modal-body">
                    <p>Reject <strong id="bulkRejectCount"></strong> withdrawal(s)?</p>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-undo mr-1"></i>Funds will be <strong>returned to each driver's wallet</strong>.
                    </div>
                    <div class="form-group mb-0">
                        <label>Reason <span class="text-danger">*</span></label>
                        <textarea name="admin_note" class="form-control" rows="2" required
                                  placeholder="Why are these being rejected?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="hideModal(this.closest('.modal').id)">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-times mr-1"></i>Reject All Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── Modal show/hide (plain DOM — no jQuery/Bootstrap JS plugin dependency,
// so these still work even if that CDN bundle fails to load) ──────────────
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

// ── Single actions ────────────────────────────────────────────────────────
function openApprove(id, name, amount) {
    document.getElementById('approveForm').action = '/admin/withdrawals/' + id + '/approve';
    document.getElementById('approveText').innerHTML =
        'Approve <strong>' + fmtNum(amount) + ' ៛</strong> withdrawal for <strong>' + name + '</strong>?';
    showModal('approveModal');
}

function openReject(id, name) {
    document.getElementById('rejectForm').action = '/admin/withdrawals/' + id + '/reject';
    document.getElementById('rejectText').innerHTML =
        'Reject withdrawal request from <strong>' + name + '</strong>?';
    showModal('rejectModal');
}

// ── Checkbox / selection ──────────────────────────────────────────────────
function getChecked() {
    return [...document.querySelectorAll('.row-check:checked')].map(c => c.value);
}

function updateBulkBar() {
    var ids = getChecked();
    var bar = document.getElementById('bulkBar');
    if (!bar) return;
    document.getElementById('selectedCount').textContent = ids.length;
    bar.style.display = ids.length > 0 ? 'block' : 'none';
}

function clearSelection() {
    document.querySelectorAll('.row-check, #selectAll').forEach(c => c.checked = false);
    updateBulkBar();
}

document.addEventListener('DOMContentLoaded', function () {
    var selectAll = document.getElementById('selectAll');
    if (!selectAll) return;

    selectAll.addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(c => c.checked = selectAll.checked);
        updateBulkBar();
    });

    document.querySelectorAll('.row-check').forEach(function (c) {
        c.addEventListener('change', function () {
            var all = document.querySelectorAll('.row-check');
            selectAll.checked = [...all].every(c => c.checked);
            updateBulkBar();
        });
    });
});

// ── Bulk actions ──────────────────────────────────────────────────────────
function fillBulkIds(containerId, ids) {
    var el = document.getElementById(containerId);
    el.innerHTML = '';
    ids.forEach(function (id) {
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'ids[]';
        inp.value = id;
        el.appendChild(inp);
    });
}

function openBulkApprove() {
    var ids = getChecked();
    if (!ids.length) return;
    fillBulkIds('bulkApproveIds', ids);
    document.getElementById('bulkApproveCount').textContent = ids.length;
    showModal('bulkApproveModal');
}

function openBulkReject() {
    var ids = getChecked();
    if (!ids.length) return;
    fillBulkIds('bulkRejectIds', ids);
    document.getElementById('bulkRejectCount').textContent = ids.length;
    showModal('bulkRejectModal');
}

function fmtNum(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>
@endpush
