@extends('admin.layout')
@section('title', 'Partner Deliveries')
@section('page-title', 'Partner Deliveries')

@section('content')

<x-admin.page-toolbar subtitle="Shop → ROTEH office → customer. Drivers finish each leg by scanning the package QR, or you mark it here.">
    <a href="{{ route('admin.daily-closing') }}" class="btn btn-outline-primary"><i class="fas fa-cash-register mr-1"></i> Daily Closing</a>
</x-admin.page-toolbar>

@php
    $stages = \App\Http\Controllers\Admin\PartnerAdminController::STAGES;
    $hints  = [
        'new'       => 'to assign',
        'pickup'    => 'shop → office',
        'at_office' => 'to send out',
        'out'       => 'to the customer',
        'done'      => 'completed',
        'problem'   => 'need attention',
    ];
    $reasons = ['cannot_contact' => 'Cannot contact receiver', 'receiver_refused' => 'Receiver refused', 'wrong_address' => 'Wrong address', 'receiver_postponed' => 'Receiver asked to postpone', 'other' => 'Other'];
    $pkg = fn ($d) => 'PKG-' . str_pad($d->id, 6, '0', STR_PAD_LEFT);
    [$assignTitle, $assignHint] = match ($stage) {
        'new'       => ['Assign pickup driver', 'They collect the packages at the shop and bring them to the office.'],
        'pickup'    => ['Change pickup driver', 'Only before the driver scans the package at the shop.'],
        'at_office' => ['Assign delivery driver', 'They collect the packages at the office and deliver them to the customer.'],
        default     => ['Assign staff driver', 'Packages not at the office yet go on a pickup run; packages at the office go out for delivery.'],
    };
@endphp

<x-admin.stat-grid :cols="6">
    @foreach($stages as $key => [$label, $icon, $tone])
        <x-admin.stat-card :label="($loop->index < 5 ? ($loop->index + 1) . ' · ' : '') . $label" :value="number_format($counts[$key])" :icon="$icon" :tone="$tone"
                           :sub="$hints[$key]" :href="route('admin.partner-deliveries', array_filter(['stage' => $key, 'partner_id' => request('partner_id')]))"
                           :active="$stage === $key" />
    @endforeach
</x-admin.stat-grid>

{{-- Bulk assign: tick packages → choose a ROTEH staff driver → Assign. The leg follows from where each package is. --}}
@if(in_array($stage, [null, 'new', 'pickup', 'at_office'], true))
<form method="POST" action="{{ route('admin.partner-packages.assign') }}" id="assignForm">
    @csrf
    <div class="card mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center" style="gap:8px;">
            <i class="fas fa-motorcycle text-muted"></i>
            <b class="mr-1">{{ $assignTitle }}</b>
            <span class="text-muted tw:text-xs" id="selCount">0 selected</span>
            <select name="driver_id" class="form-control form-control-sm" style="width:auto;min-width:240px;" required>
                <option value="">— Choose staff driver —</option>
                @foreach($staff as $s)
                    <option value="{{ $s->id }}">{{ $s->name }} · {{ $s->phone }} ({{ $staffLoad[$s->id] ?? 0 }} on hand)</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-success" id="assignBtn" disabled><i class="fas fa-user-check mr-1"></i>Assign</button>
            <span class="text-muted tw:text-xs">{{ $assignHint }}</span>
            @if($staff->isEmpty())
                <span class="text-danger tw:text-xs">No ROTEH staff drivers yet — create one in Users (type: ROTEH Staff).</span>
            @endif
        </div>
    </div>
</form>
@endif

@if($atRoteh > 0 && in_array($stage, [null, 'problem'], true))
<form method="POST" action="{{ route('admin.partner-packages.return') }}" id="returnForm" class="mb-3">
    @csrf
    <div class="alert alert-warning d-flex flex-wrap align-items-center mb-0" style="gap:8px;">
        <i class="fas fa-box-open"></i>
        <b>{{ $atRoteh }} undelivered package(s) at ROTEH</b> waiting to go back to the shop — tick them below, then
        <button class="btn btn-sm btn-warning"><i class="fas fa-store mr-1"></i>Returned to shop</button>
        <span class="text-muted tw:text-xs">(closing the partner in Daily Closing does this too)</span>
    </div>
</form>
@endif

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.partner-deliveries')" placeholder="Search PKG code, partner, recipient or reference"
                            :count="$packages->total()" noun="package" :keep="['stage']">
            @if($partners->isNotEmpty())
                <x-admin.filter-select name="partner_id" :options="$partners->all()" all="All partners" />
            @endif
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th style="width:28px;"><input type="checkbox" id="selAll" title="Select all assignable"></th>
                    <th>Package</th>
                    <th>Route</th>
                    <th>Recipient</th>
                    <th>Stage</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($packages as $d)
                @php
                    $st        = $d->workflowStage();
                    [$stLabel, , $stTone] = $stages[$st];
                    // Older packages (no leg) went straight to the customer.
                    $viaOffice = $d->leg || $d->office_received_at || $d->status === 'at_office';
                    $detail    = match (true) {
                        $d->status === 'created'                        => 'Waiting for confirmation',
                        $st === 'new'                                   => 'Confirmed — assign a pickup driver',
                        $st === 'pickup' && $d->status === 'picked_up'  => 'Picked up — on the way to the office',
                        $st === 'pickup'                                => 'Driver going to the shop',
                        $d->status === 'at_office'                      => 'Waiting for a delivery driver',
                        $st === 'at_office'                             => 'Delivery driver assigned — not left yet',
                        $st === 'out' && $d->delivery_scanned_at        => 'At the customer — verifying payment',
                        $st === 'out'                                   => 'On the way to the customer',
                        $st === 'done'                                  => 'Delivered ' . $d->completed_at?->format('d M H:i'),
                        $d->status === 'delivery_failed'                => 'Not delivered — ' . ($reasons[$d->failure_reason] ?? 'see details'),
                        $d->status === 'at_roteh'                       => 'Back at ROTEH — return to the shop',
                        $d->status === 'returned'                       => 'Returned to the shop',
                        default                                         => 'Cancelled',
                    };
                    $canComplete = in_array($st, ['out', 'at_office'], true) && $d->status !== 'at_office' && $d->driver_id
                                   || $d->status === 'delivery_failed';
                    $shop = $d->partner?->partnerProfile?->business_name ?: ($d->partner->company_name ?? $d->partner->name ?? '—');
                @endphp
                <tr data-row="{{ json_encode([
                        'id'              => $d->id,
                        'code'            => $pkg($d),
                        'sender_id'       => $d->sender_id,
                        'sender_name'     => $d->sender_name ?? $shop,
                        'pickup_address'  => $d->pickup_address,
                        'dropoff_address' => $d->dropoff_address,
                        'recipient_name'  => $d->recipient_name,
                        'recipient_phone' => $d->recipient_phone,
                        'package_size'    => $d->package_size ?? 'small',
                        'package_amount'  => (int) $d->package_amount,
                        'package_details' => $d->package_details ?? '',
                        'fee'             => (int) $d->fee,
                        'notes'           => $d->notes ?? '',
                        'payment_by'      => $d->payment_by ?? 'sender',
                        'payment_method'  => $d->payment_method ?? 'cash',
                        'payment_model'   => $d->payment_model ?? 'customer_pays',
                        'driver'          => $d->driver?->name,
                    ]) }}">
                    <td>
                        @if(\App\Services\StaffAssignmentService::canAssign($d))
                            <input type="checkbox" class="sel" form="assignForm" name="ids[]" value="{{ $d->id }}">
                        @elseif($d->status === 'at_roteh')
                            <input type="checkbox" class="selret" form="returnForm" name="ids[]" value="{{ $d->id }}" title="Returned to shop">
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('admin.deliveries.show', $d) }}" style="font-weight:600;">{{ $pkg($d) }}</a>
                        @if($d->service_option === 'express')<span class="badge badge-danger ml-1"><i class="fas fa-bolt"></i> Express</span>@endif
                        <div style="color:var(--ink-800);">{{ $shop }}</div>
                        <div class="text-muted tw:text-xs">
                            {{ ucfirst($d->package_type ?? 'parcel') }} · {{ ucfirst(str_replace('_', ' ', $d->package_size ?? 'small')) }}
                            · {{ $d->created_at->format('d M H:i') }}
                        </div>
                        @if($d->partner_reference)<div class="text-muted tw:text-xs">Ref: {{ $d->partner_reference }}</div>@endif
                    </td>
                    <td style="min-width:220px;max-width:280px;">
                        <div class="trip">
                            <div class="trip-stop"><span class="trip-label">Shop</span><span title="{{ $d->pickup_address }}">{{ $d->pickup_address ?: '—' }}</span></div>
                            @if($viaOffice || $st === 'new')
                                <div class="trip-stop is-stop"><span class="trip-label">ROTEH office</span><span>{{ $office }}</span></div>
                            @endif
                            <div class="trip-stop is-drop"><span class="trip-label">Customer</span><span title="{{ $d->dropoff_address }}">{{ $d->dropoff_address ?: '—' }}</span></div>
                        </div>
                    </td>
                    <td>
                        <div>{{ $d->recipient_name }}</div>
                        <div class="text-muted tw:text-xs">{{ $d->recipient_phone }}</div>
                        <div class="tw:text-xs" style="margin-top:2px;">
                            Fee <b>{{ number_format($d->fee) }} ៛</b>
                            @if($d->package_amount > 0) · COD <b>{{ number_format($d->package_amount) }} ៛</b>@endif
                        </div>
                    </td>
                    <td>
                        <span class="status-pill tone-{{ $stTone }}">{{ $stLabel }}</span>
                        <div class="tw:text-xs" style="color:var(--ink-700);margin-top:3px;">{{ $detail }}</div>
                        @if($d->pickupDriver)
                            <div class="text-muted tw:text-xs"><i class="fas fa-store mr-1" style="width:12px;"></i>Pickup: {{ $d->pickupDriver->name }}</div>
                        @endif
                        @if($d->driver && $d->status !== 'at_office' && ($d->leg !== 'pickup' || ! $d->pickupDriver))
                            <div class="text-muted tw:text-xs"><i class="fas fa-motorcycle mr-1" style="width:12px;"></i>{{ $viaOffice ? 'Delivery' : 'Driver' }}: {{ $d->driver->name }}</div>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="action-group">
                            @if($d->status === 'created')
                                <form method="POST" action="{{ route('admin.partner-packages.confirm', $d) }}" class="m-0">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success" title="Confirm the booking (assign later)" data-toggle="tooltip"><i class="fas fa-check mr-1"></i>Confirm</button>
                                </form>
                            @endif
                            @if($st === 'pickup')
                                <form method="POST" action="{{ route('admin.partner-deliveries.office', $d) }}" class="m-0"
                                      onsubmit="return confirm('Mark {{ $pkg($d) }} as received at the office?')">
                                    @csrf
                                    <button class="btn btn-sm btn-violet" title="The pickup driver brought it in" data-toggle="tooltip"><i class="fas fa-warehouse mr-1"></i>At office</button>
                                </form>
                            @endif
                            @if($canComplete)
                                <button type="button" class="btn btn-sm btn-success" onclick="openComplete(this.closest('tr'))"
                                        title="Delivered — record the money received" data-toggle="tooltip"><i class="fas fa-circle-check mr-1"></i>Completed</button>
                            @endif
                            @if(! in_array($st, ['done', 'problem'], true))
                                <button type="button" class="btn btn-sm btn-outline-primary btn-icon" onclick="openEdit(this.closest('tr'))" title="Edit" data-toggle="tooltip"><i class="fas fa-pen"></i></button>
                            @endif
                            <a href="{{ route('admin.deliveries.show', $d) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="View details" data-toggle="tooltip"><i class="fas fa-eye"></i></a>
                        </div>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="6" icon="fa-boxes-stacked" title="No partner packages here"
                                   hint="Approved partners book packages from the ROTEH app." :keep="['stage']"
                                   :clear="route('admin.partner-deliveries', array_filter(['stage' => $stage]))" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$packages" />
</div>

{{-- Completed: the money received at the drop-off (same record the driver app makes). --}}
<div class="modal fade" id="completeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="completeForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Complete <span id="c-code"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 p-2" style="background:var(--ink-50);border-radius:var(--radius-sm);">
                        <div class="tw:text-sm">COD to collect<div class="text-muted tw:text-xs">recorded against <b id="c-driver"></b></div></div>
                        <div style="font-size:1.25rem;font-weight:700;color:var(--ink-900);"><span id="c-expected"></span> ៛</div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label>Cash ៛</label>
                            <input type="number" name="cash_khr" id="c-cash-khr" class="form-control" min="0" step="100" value="{{ old('cash_khr') }}">
                        </div>
                        <div class="form-group col-6">
                            <label>Cash $</label>
                            <input type="number" name="cash_usd" class="form-control" min="0" step="0.01" value="{{ old('cash_usd') }}">
                        </div>
                        <div class="form-group col-6">
                            <label>Bank ៛</label>
                            <input type="number" name="bank_khr" class="form-control" min="0" step="100" value="{{ old('bank_khr') }}">
                        </div>
                        <div class="form-group col-6">
                            <label>Bank $</label>
                            <input type="number" name="bank_usd" class="form-control" min="0" step="0.01" value="{{ old('bank_usd') }}">
                        </div>
                        <div class="form-group col-6">
                            <label>Bank</label>
                            <select name="bank_name" class="form-control">
                                <option value="">—</option>
                                @foreach(\App\Services\PartnerPackageWorkflow::BANKS as $bank)
                                    <option @selected(old('bank_name') === $bank)>{{ $bank }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label>Bank ref</label>
                            <input type="text" name="bank_ref" class="form-control" maxlength="100" value="{{ old('bank_ref') }}">
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Note <small class="text-muted">(required if the amount differs from the COD)</small></label>
                        <input type="text" name="note" class="form-control" maxlength="500" value="{{ old('note') }}">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-success"><i class="fas fa-circle-check mr-1"></i>Mark delivered</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit the package details. Status and drivers move through the workflow above, not here. --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="editForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="service_type" value="delivery">
                <input type="hidden" name="status" value="created">
                @foreach(['sender_id', 'sender_name', 'pickup_address', 'payment_by', 'payment_method', 'payment_model'] as $f)
                    <input type="hidden" name="{{ $f }}" id="e-{{ $f }}">
                @endforeach
                <div class="modal-header">
                    <h5 class="modal-title">Edit <span id="e-code"></span></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Recipient Name <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_name" id="e-recipient_name" class="form-control" required maxlength="255">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Recipient Phone <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_phone" id="e-recipient_phone" class="form-control" required maxlength="24">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Customer Address <span class="text-danger">*</span></label>
                        <input type="text" name="dropoff_address" id="e-dropoff_address" class="form-control" required maxlength="255">
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Package Size</label>
                            <select name="package_size" id="e-package_size" class="form-control">
                                <option value="small">Small</option>
                                <option value="medium">Medium</option>
                                <option value="large">Large</option>
                                <option value="extra_large">Extra large</option>
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>COD Amount (៛)</label>
                            <input type="number" name="package_amount" id="e-package_amount" class="form-control" min="0" step="100">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Delivery Fee (៛) <span class="text-danger">*</span></label>
                            <input type="number" name="fee" id="e-fee" class="form-control" min="0" step="100" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Package Details</label>
                        <input type="text" name="package_details" id="e-package_details" class="form-control" maxlength="500">
                    </div>
                    <div class="form-group mb-0">
                        <label>Notes</label>
                        <textarea name="notes" id="e-notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const completeBase = @json(url('admin/partner-deliveries')) + '/';
const updateBase   = @json(url('admin/deliveries')) + '/';

function openComplete(row, keepInput) {
    const d = JSON.parse(row.dataset.row);
    const form = document.getElementById('completeForm');
    form.action = completeBase + d.id + '/complete';
    document.getElementById('c-code').textContent     = d.code;
    document.getElementById('c-driver').textContent   = d.driver || 'the delivery driver';
    document.getElementById('c-expected').textContent = d.package_amount.toLocaleString();
    if (! keepInput) {
        form.querySelectorAll('input[type=number], input[type=text]').forEach(i => i.value = '');
        form.querySelector('select[name=bank_name]').value = '';
        document.getElementById('c-cash-khr').value = d.package_amount || '';
    }
    $('#completeModal').modal('show');
}

function openEdit(row) {
    const d = JSON.parse(row.dataset.row);
    document.getElementById('editForm').action = updateBase + d.id;
    document.getElementById('e-code').textContent = d.code;
    ['sender_id', 'sender_name', 'pickup_address', 'payment_by', 'payment_method', 'payment_model',
     'recipient_name', 'recipient_phone', 'dropoff_address', 'package_size', 'package_amount', 'fee', 'package_details', 'notes']
        .forEach(f => document.getElementById('e-' + f).value = d[f] ?? '');
    $('#editModal').modal('show');
}

(function () {
    const boxes = () => Array.from(document.querySelectorAll('.sel'));
    const sync = () => {
        const n = boxes().filter(b => b.checked).length;
        const count = document.getElementById('selCount');
        if (! count) return;
        count.textContent = n + ' selected';
        document.getElementById('assignBtn').disabled = n === 0;
    };
    document.addEventListener('change', e => {
        if (e.target.id === 'selAll') boxes().forEach(b => b.checked = e.target.checked);
        if (e.target.classList.contains('sel') || e.target.id === 'selAll') sync();
    });

    // Reopen the Completed form after a validation error (e.g. a note was needed).
    @if(session('completeId'))
        const row = document.querySelector('tr[data-row*=\'"id":{{ (int) session('completeId') }},\']');
        if (row) $(() => openComplete(row, true));
    @endif
})();
</script>
@endpush
