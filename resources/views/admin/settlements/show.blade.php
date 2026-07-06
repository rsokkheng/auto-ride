@extends('admin.layout')
@section('title', 'Settlement #' . $settlement->id)
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6">
        <h1 class="m-0">
            <i class="fas fa-file-invoice-dollar mr-2 text-success"></i>Settlement #{{ $settlement->id }}
            @php $cls = $settlement->statusBadgeClass(); @endphp
            <span class="badge badge-{{ $cls }} ml-2" style="font-size:.6em;vertical-align:middle;">{{ ucfirst($settlement->status) }}</span>
        </h1>
    </div>
    <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.settlements.index') }}">Settlements</a></li>
            <li class="breadcrumb-item active">#{{ $settlement->id }}</li>
        </ol>
    </div>
</div>
@endsection
@section('content')
<div class="container-fluid">

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
@endif

<div class="row">

    {{-- Left: Settlement Info + Line Items --}}
    <div class="col-lg-8">

        {{-- Header Info --}}
        <div class="card card-outline card-{{ $cls }}">
            <div class="card-header">
                <h3 class="card-title">
                    @if($settlement->settlement_type === 'driver')
                    <i class="fas fa-id-badge mr-2 text-info"></i>Driver Settlement
                    @else
                    <i class="fas fa-handshake mr-2 text-warning"></i>Partner Settlement
                    @endif
                </h3>
                <div class="card-tools">
                    <span class="badge badge-{{ $cls }} px-3 py-2">{{ ucfirst($settlement->status) }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" style="width:130px;">Entity</td><td><strong>{{ $settlement->user->name ?? '—' }}</strong></td></tr>
                            <tr><td class="text-muted">Phone</td><td>{{ $settlement->user->phone ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Period</td><td>{{ $settlement->period_start->format('d M Y') }} – {{ $settlement->period_end->format('d M Y') }}</td></tr>
                            <tr><td class="text-muted">Created</td><td>{{ $settlement->created_at->format('d M Y H:i') }} <small class="text-muted">by {{ $settlement->creator->name ?? 'System' }}</small></td></tr>
                            @if($settlement->approved_at)
                            <tr><td class="text-muted">Approved</td><td>{{ $settlement->approved_at->format('d M Y H:i') }} <small class="text-muted">by {{ $settlement->approver->name ?? '—' }}</small></td></tr>
                            @endif
                            @if($settlement->paid_at)
                            <tr><td class="text-muted">Paid At</td><td class="text-success font-weight-bold">{{ $settlement->paid_at->format('d M Y H:i') }}</td></tr>
                            @endif
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-0">
                            <tr><td class="text-muted" style="width:130px;">Payment Method</td><td>{{ $settlement->payment_method ? ucwords(str_replace('_', ' ', $settlement->payment_method)) : '—' }}</td></tr>
                            <tr><td class="text-muted">Bank Name</td><td>{{ $settlement->bank_name ?? '—' }}</td></tr>
                            <tr><td class="text-muted">Account No.</td><td><strong>{{ $settlement->bank_account ?? '—' }}</strong></td></tr>
                            <tr><td class="text-muted">Account Holder</td><td>{{ $settlement->account_holder ?? '—' }}</td></tr>
                            @if($settlement->payment_reference)
                            <tr><td class="text-muted">Reference</td><td><span class="badge badge-success">{{ $settlement->payment_reference }}</span></td></tr>
                            @endif
                        </table>
                    </div>
                </div>
                @if($settlement->notes)
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted"><i class="fas fa-sticky-note mr-1"></i>Notes:</small>
                    <div style="white-space:pre-line;font-size:.85rem;">{{ $settlement->notes }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Financial Summary --}}
        <div class="card card-outline card-success">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-coins mr-2"></i>Financial Summary</h3></div>
            <div class="card-body">
                @if($settlement->settlement_type === 'driver')
                <div class="row text-center">
                    <div class="col-md-2 col-4 mb-3">
                        <div class="h4 text-info font-weight-bold">{{ $settlement->rides_count }}</div>
                        <small class="text-muted">Rides</small>
                    </div>
                    <div class="col-md-2 col-4 mb-3">
                        <div class="h4 text-primary font-weight-bold">{{ $settlement->deliveries_count }}</div>
                        <small class="text-muted">Deliveries</small>
                    </div>
                    <div class="col-md-2 col-4 mb-3">
                        <div class="h5 text-success font-weight-bold">{{ number_format($settlement->gross_earnings) }}</div>
                        <small class="text-muted">Gross ៛</small>
                    </div>
                    <div class="col-md-2 col-4 mb-3">
                        <div class="h5 text-danger font-weight-bold">{{ number_format($settlement->commission_total) }}</div>
                        <small class="text-muted">Commission ៛</small>
                    </div>
                    <div class="col-md-2 col-4 mb-3">
                        <div class="h5 text-warning font-weight-bold">{{ number_format($settlement->tips_total) }}</div>
                        <small class="text-muted">Tips ៛</small>
                    </div>
                    <div class="col-md-2 col-4 mb-3">
                        <div class="h5 text-orange font-weight-bold" style="color:#fd7e14;">{{ number_format($settlement->cod_collected) }}</div>
                        <small class="text-muted">COD ៛</small>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <tr><td>Gross Earnings</td><td class="text-right text-success font-weight-bold">+ {{ number_format($settlement->gross_earnings) }} ៛</td></tr>
                            <tr><td>Less: Commission</td><td class="text-right text-danger">- {{ number_format($settlement->commission_total) }} ៛</td></tr>
                            <tr><td>Add: Tips</td><td class="text-right text-warning">+ {{ number_format($settlement->tips_total) }} ៛</td></tr>
                            <tr><td>Less: COD Collected (remit)</td><td class="text-right text-danger">- {{ number_format($settlement->cod_collected) }} ៛</td></tr>
                            @if($settlement->adjustments != 0)
                            <tr><td>Adjustments <small class="text-muted">({{ $settlement->adjustment_note }})</small></td><td class="text-right {{ $settlement->adjustments >= 0 ? 'text-success' : 'text-danger' }}">{{ $settlement->adjustments >= 0 ? '+' : '-' }} {{ number_format(abs($settlement->adjustments)) }} ៛</td></tr>
                            @endif
                            <tr class="font-weight-bold" style="border-top:2px solid #dee2e6;">
                                <td>Net Payout</td>
                                <td class="text-right {{ $settlement->net_payout >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:1.1rem;">{{ number_format(abs($settlement->net_payout)) }} ៛</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 d-flex align-items-center justify-content-center">
                        <div class="text-center p-3 {{ $settlement->net_payout >= 0 ? 'bg-success' : 'bg-danger' }} text-white rounded">
                            <div class="h4 mb-1">{{ number_format(abs($settlement->net_payout)) }} ៛</div>
                            <div>{{ $settlement->net_payout >= 0 ? 'Company pays to driver' : 'Driver owes company' }}</div>
                        </div>
                    </div>
                </div>
                @else
                {{-- Partner Summary --}}
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div class="h4 text-info font-weight-bold">{{ $settlement->orders_count }}</div>
                        <small class="text-muted">Total Orders</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-danger font-weight-bold">{{ number_format($settlement->delivery_fees) }}</div>
                        <small class="text-muted">Delivery Fees ៛</small>
                    </div>
                    <div class="col-4">
                        <div class="h4 text-warning font-weight-bold">{{ number_format($settlement->cod_handled) }}</div>
                        <small class="text-muted">COD Handled ៛</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm mb-0">
                            <tr><td>COD Collected for Partner</td><td class="text-right text-success font-weight-bold">+ {{ number_format($settlement->cod_handled) }} ៛</td></tr>
                            <tr><td>Less: Delivery Fees</td><td class="text-right text-danger">- {{ number_format($settlement->delivery_fees) }} ៛</td></tr>
                            @if($settlement->adjustments != 0)
                            <tr><td>Adjustments</td><td class="text-right {{ $settlement->adjustments >= 0 ? 'text-success' : 'text-danger' }}">{{ $settlement->adjustments >= 0 ? '+' : '-' }} {{ number_format(abs($settlement->adjustments)) }} ៛</td></tr>
                            @endif
                            <tr class="font-weight-bold" style="border-top:2px solid #dee2e6;">
                                <td>Net Settlement</td>
                                <td class="text-right {{ $settlement->net_payout >= 0 ? 'text-success' : 'text-danger' }}" style="font-size:1.1rem;">{{ number_format(abs($settlement->net_payout)) }} ៛</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 d-flex align-items-center justify-content-center">
                        <div class="text-center p-3 {{ $settlement->net_payout >= 0 ? 'bg-success' : 'bg-danger' }} text-white rounded">
                            <div class="h4 mb-1">{{ number_format(abs($settlement->net_payout)) }} ៛</div>
                            <div>{{ $settlement->net_payout >= 0 ? 'Company pays to partner' : 'Partner owes company' }}</div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Line Items --}}
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-list mr-2"></i>Transaction Details
                    <small class="text-muted">(up to 100 records)</small>
                </h3>
            </div>
            <div class="card-body p-0" style="max-height:400px;overflow-y:auto;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="thead-light sticky-top">
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th class="text-right">Credit</th>
                            <th class="text-right">Debit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lineItems as $item)
                        @php
                            $typeLabel = match($item['type']) {
                                'trip_earning'      => ['Trip Earning', 'success'],
                                'delivery_payment'  => ['Delivery Pay', 'primary'],
                                'platform_commission' => ['Commission', 'danger'],
                                'tip_in'            => ['Tip Received', 'warning'],
                                'tip_out'           => ['Tip Paid', 'warning'],
                                'cod_collected'     => ['COD Collected', 'dark'],
                                'cod_received'      => ['COD (for partner)', 'success'],
                                'delivery_fee'      => ['Delivery Fee', 'danger'],
                                default             => [ucwords(str_replace('_', ' ', $item['type'])), 'secondary']
                            };
                        @endphp
                        <tr>
                            <td><small>{{ is_string($item['date']) ? \Carbon\Carbon::parse($item['date'])->format('d M H:i') : \Carbon\Carbon::instance($item['date'])->format('d M H:i') }}</small></td>
                            <td><span class="badge badge-{{ $typeLabel[1] }}" style="font-size:.7rem;">{{ $typeLabel[0] }}</span></td>
                            <td><small class="text-muted">{{ $item['note'] ?? '—' }}</small></td>
                            <td class="text-right text-success">{{ $item['credit'] > 0 ? number_format($item['credit']) : '—' }}</td>
                            <td class="text-right text-danger">{{ $item['debit'] > 0 ? number_format($item['debit']) : '—' }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No transactions found for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Right: Action Panel --}}
    <div class="col-lg-4">

        {{-- Status Timeline --}}
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-history mr-2"></i>Status Timeline</h3></div>
            <div class="card-body p-0">
                @php
                    $steps = [
                        ['status' => 'draft',      'icon' => 'fa-file-alt',     'label' => 'Draft Created',     'color' => 'secondary', 'time' => $settlement->created_at],
                        ['status' => 'pending',    'icon' => 'fa-clock',        'label' => 'Submitted',          'color' => 'warning',   'time' => null],
                        ['status' => 'approved',   'icon' => 'fa-thumbs-up',    'label' => 'Approved',           'color' => 'info',      'time' => $settlement->approved_at],
                        ['status' => 'processing', 'icon' => 'fa-spinner',      'label' => 'Processing Payment', 'color' => 'primary',   'time' => $settlement->processed_at],
                        ['status' => 'paid',       'icon' => 'fa-check-circle', 'label' => 'Payment Complete',   'color' => 'success',   'time' => $settlement->paid_at],
                    ];
                    $statusOrder = ['draft' => 0, 'pending' => 1, 'approved' => 2, 'processing' => 3, 'paid' => 4];
                    $currentStep = $statusOrder[$settlement->status] ?? 0;
                @endphp
                <ul class="list-unstyled p-3 mb-0">
                    @foreach($steps as $i => $step)
                    @php $done = $i <= $currentStep && !in_array($settlement->status, ['failed','cancelled']); @endphp
                    <li class="d-flex align-items-start mb-3">
                        <div class="mr-3">
                            <span class="d-flex align-items-center justify-content-center rounded-circle {{ $done ? 'bg-'.$step['color'] : 'bg-light border' }}" style="width:34px;height:34px;">
                                <i class="fas {{ $step['icon'] }} {{ $done ? 'text-white' : 'text-muted' }}" style="font-size:.8rem;"></i>
                            </span>
                        </div>
                        <div>
                            <div class="{{ $done ? 'font-weight-bold' : 'text-muted' }}">{{ $step['label'] }}</div>
                            @if($step['time'])
                            <small class="text-muted">{{ \Carbon\Carbon::parse($step['time'])->format('d M Y H:i') }}</small>
                            @endif
                        </div>
                    </li>
                    @endforeach
                    @if(in_array($settlement->status, ['failed','cancelled']))
                    <li class="d-flex align-items-start mb-3">
                        <div class="mr-3">
                            <span class="d-flex align-items-center justify-content-center rounded-circle bg-danger" style="width:34px;height:34px;">
                                <i class="fas fa-times text-white" style="font-size:.8rem;"></i>
                            </span>
                        </div>
                        <div>
                            <div class="font-weight-bold text-danger">{{ ucfirst($settlement->status) }}</div>
                        </div>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- Action Buttons --}}
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Actions</h3></div>
            <div class="card-body">

                @if($settlement->canApprove())
                {{-- Approve --}}
                <button type="button" class="btn btn-success btn-block mb-2" data-toggle="modal" data-target="#modalApprove">
                    <i class="fas fa-thumbs-up mr-2"></i>Approve Settlement
                </button>
                {{-- Reject --}}
                <button type="button" class="btn btn-danger btn-block mb-2" data-toggle="modal" data-target="#modalReject">
                    <i class="fas fa-times mr-2"></i>Reject / Cancel
                </button>
                @endif

                @if($settlement->canProcess())
                <form method="POST" action="{{ route('admin.settlements.processing', $settlement) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-primary btn-block mb-2" onclick="return confirm('Mark as Processing? This means payment has been initiated.')">
                        <i class="fas fa-play mr-2"></i>Mark as Processing
                    </button>
                </form>
                @endif

                @if($settlement->canMarkPaid())
                <button type="button" class="btn btn-success btn-block mb-2" data-toggle="modal" data-target="#modalPaid">
                    <i class="fas fa-check-double mr-2"></i>Mark as Paid
                </button>
                <button type="button" class="btn btn-danger btn-block mb-2" data-toggle="modal" data-target="#modalFailed">
                    <i class="fas fa-exclamation-triangle mr-2"></i>Mark as Failed
                </button>
                @endif

                @if($settlement->canCancel())
                <form method="POST" action="{{ route('admin.settlements.cancel', $settlement) }}">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-outline-dark btn-block mb-2" onclick="return confirm('Cancel this settlement?')">
                        <i class="fas fa-ban mr-2"></i>Cancel Settlement
                    </button>
                </form>
                @endif

                @if($settlement->canDelete())
                <form method="POST" action="{{ route('admin.settlements.destroy', $settlement) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-block" onclick="return confirm('Delete this draft settlement? This cannot be undone.')">
                        <i class="fas fa-trash mr-2"></i>Delete Draft
                    </button>
                </form>
                @endif

                <hr>
                <a href="{{ route('admin.settlements.receipt', $settlement) }}" target="_blank" class="btn btn-outline-info btn-block mb-2">
                    <i class="fas fa-print mr-2"></i>Print Receipt
                </a>
                <a href="{{ route('admin.settlements.receipt.pdf', $settlement) }}" class="btn btn-outline-success btn-block mb-2">
                    <i class="fas fa-file-pdf mr-2"></i>Download PDF
                </a>
                <a href="{{ route('admin.settlements.index') }}" class="btn btn-outline-secondary btn-block">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>

    </div>
</div>

{{-- Approve Modal --}}
<div class="modal fade" id="modalApprove">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="{{ route('admin.settlements.approve', $settlement) }}">
        @csrf @method('PATCH')
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-thumbs-up mr-2"></i>Approve Settlement</h5></div>
            <div class="modal-body">
                <p>Approving settlement <strong>#{{ $settlement->id }}</strong> for <strong>{{ $settlement->user->name }}</strong></p>
                <p>Net Payout: <strong class="text-success">{{ number_format(abs($settlement->net_payout)) }} ៛</strong></p>
                <div class="form-group mb-0">
                    <label>Note (optional)</label>
                    <input type="text" name="note" class="form-control form-control-sm" placeholder="Approval note...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i>Approve</button>
            </div>
        </div>
        </form>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="modalReject">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="{{ route('admin.settlements.reject', $settlement) }}">
        @csrf @method('PATCH')
        <div class="modal-content">
            <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-times mr-2"></i>Reject Settlement</h5></div>
            <div class="modal-body">
                <div class="form-group mb-0">
                    <label>Reason <span class="text-danger">*</span></label>
                    <input type="text" name="note" class="form-control form-control-sm" placeholder="Rejection reason..." required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times mr-1"></i>Reject</button>
            </div>
        </div>
        </form>
    </div>
</div>

{{-- Mark Paid Modal --}}
<div class="modal fade" id="modalPaid">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="{{ route('admin.settlements.paid', $settlement) }}">
        @csrf @method('PATCH')
        <div class="modal-content">
            <div class="modal-header bg-success text-white"><h5 class="modal-title"><i class="fas fa-check-double mr-2"></i>Confirm Payment</h5></div>
            <div class="modal-body">
                <p>Mark <strong>#{{ $settlement->id }}</strong> as paid?</p>
                <div class="form-group mb-0">
                    <label>Payment Reference</label>
                    <input type="text" name="payment_reference" class="form-control form-control-sm" value="{{ $settlement->payment_reference }}" placeholder="Transaction ID / Ref no.">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i>Mark Paid</button>
            </div>
        </div>
        </form>
    </div>
</div>

{{-- Mark Failed Modal --}}
<div class="modal fade" id="modalFailed">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="{{ route('admin.settlements.failed', $settlement) }}">
        @csrf @method('PATCH')
        <div class="modal-content">
            <div class="modal-header bg-danger text-white"><h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Mark as Failed</h5></div>
            <div class="modal-body">
                <div class="form-group mb-0">
                    <label>Failure Reason</label>
                    <input type="text" name="reason" class="form-control form-control-sm" placeholder="e.g. Insufficient funds, Wrong account">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times mr-1"></i>Mark Failed</button>
            </div>
        </div>
        </form>
    </div>
</div>

</div>
@endsection
