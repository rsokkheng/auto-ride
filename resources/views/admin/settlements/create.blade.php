@extends('admin.layout')
@section('title', 'Generate Settlement')
@section('content-header')
<div class="row mb-2">
    <div class="col-sm-6"><h1 class="m-0"><i class="fas fa-plus-circle mr-2 text-primary"></i>Generate Settlement</h1></div>
    <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.settlements.index') }}">Settlements</a></li>
            <li class="breadcrumb-item active">Generate</li>
        </ol>
    </div>
</div>
@endsection
@section('content')
<div class="container-fluid">

<div class="row">
    <div class="col-lg-6">

        {{-- Step 1: Parameters --}}
        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-sliders-h mr-2"></i>Settlement Parameters</h3></div>
            <div class="card-body">
                <div class="form-group">
                    <label>Settlement Type</label>
                    <div>
                        <div class="icheck-primary d-inline mr-4">
                            <input type="radio" id="typeDriver" name="settlement_type_radio" value="driver" checked>
                            <label for="typeDriver"><i class="fas fa-id-badge mr-1 text-info"></i>Driver Settlement</label>
                        </div>
                        <div class="icheck-warning d-inline">
                            <input type="radio" id="typePartner" name="settlement_type_radio" value="partner">
                            <label for="typePartner"><i class="fas fa-handshake mr-1 text-warning"></i>Partner Settlement</label>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="driverSelect">
                    <label>Select Driver <span class="text-danger">*</span></label>
                    <select id="driverDropdown" class="form-control select2">
                        <option value="">-- Select Driver --</option>
                        @foreach($drivers as $d)
                        <option value="{{ $d->id }}" data-bank="{{ $d->bank_name ?? '' }}" data-account="{{ $d->bank_account ?? '' }}" data-wallet="{{ $d->wallet_balance }}">
                            {{ $d->name }} ({{ $d->phone }})
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group d-none" id="partnerSelect">
                    <label>Select Partner <span class="text-danger">*</span></label>
                    <select id="partnerDropdown" class="form-control select2">
                        <option value="">-- Select Partner --</option>
                        @foreach($partners as $p)
                        <option value="{{ $p->id }}" data-wallet="{{ $p->wallet_balance }}">
                            {{ $p->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label>Period Start <span class="text-danger">*</span></label>
                            <input type="date" id="periodStart" class="form-control" value="{{ now()->subDays(30)->format('Y-m-d') }}">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label>Period End <span class="text-danger">*</span></label>
                            <input type="date" id="periodEnd" class="form-control" value="{{ now()->format('Y-m-d') }}">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Quick Period</label>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="setPeriod(7)">7 Days</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setPeriod(30)">30 Days</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setPeriodMonth()">This Month</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="setPeriodLastMonth()">Last Month</button>
                    </div>
                </div>

                <button type="button" id="calcBtn" class="btn btn-info btn-block">
                    <i class="fas fa-calculator mr-2"></i>Calculate Settlement
                </button>
            </div>
        </div>

    </div>
    <div class="col-lg-6">

        {{-- Preview Card --}}
        <div class="card card-outline card-success" id="previewCard" style="display:none;">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-eye mr-2"></i>Settlement Preview</h3></div>
            <div class="card-body">
                <div id="previewContent"></div>
            </div>
        </div>

        {{-- Empty state --}}
        <div class="card card-outline card-secondary text-center py-5" id="emptyState">
            <div class="card-body">
                <i class="fas fa-calculator fa-3x text-muted mb-3"></i>
                <p class="text-muted">Select an entity and period, then click <strong>Calculate Settlement</strong> to preview.</p>
            </div>
        </div>

    </div>
</div>

{{-- Settlement Form (hidden until calculated) --}}
<div class="card card-outline card-primary" id="settlementForm" style="display:none;">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-edit mr-2"></i>Settlement Details</h3></div>
    <form method="POST" action="{{ route('admin.settlements.store') }}">
    @csrf
    <input type="hidden" name="settlement_type" id="formType">
    <input type="hidden" name="user_id" id="formUserId">
    <input type="hidden" name="period_start" id="formStart">
    <input type="hidden" name="period_end" id="formEnd">

    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="">-- Select --</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="aba">ABA</option>
                        <option value="acleda">ACLEDA</option>
                        <option value="wing">Wing</option>
                        <option value="pi_pay">Pi Pay</option>
                        <option value="cash">Cash</option>
                        <option value="wallet_credit">Wallet Credit</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" id="formBankName" class="form-control" placeholder="e.g. ABA Bank">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="bank_account" id="formBankAccount" class="form-control" placeholder="e.g. 000123456">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Account Holder</label>
                    <input type="text" name="account_holder" class="form-control" placeholder="Account holder name">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Adjustments (KHR)</label>
                    <input type="number" name="adjustments" id="formAdj" class="form-control" value="0" placeholder="+ credit, - debit">
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Adjustment Note</label>
                    <input type="text" name="adjustment_note" class="form-control" placeholder="Reason for adjustment">
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Internal notes for this settlement..."></textarea>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" name="submit_action" value="draft" class="btn btn-secondary">
            <i class="fas fa-save mr-1"></i>Save as Draft
        </button>
        <button type="submit" name="submit_action" value="pending" class="btn btn-warning">
            <i class="fas fa-paper-plane mr-1"></i>Submit for Approval
        </button>
        <a href="{{ route('admin.settlements.index') }}" class="btn btn-outline-secondary ml-auto">Cancel</a>
    </div>
    </form>
</div>

</div>
@endsection
@push('scripts')
<script>
function setPeriod(days) {
    var end = new Date();
    var start = new Date(); start.setDate(start.getDate() - days);
    document.getElementById('periodEnd').value   = end.toISOString().split('T')[0];
    document.getElementById('periodStart').value = start.toISOString().split('T')[0];
}
function setPeriodMonth() {
    var now = new Date();
    document.getElementById('periodStart').value = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
    document.getElementById('periodEnd').value   = new Date(now.getFullYear(), now.getMonth()+1, 0).toISOString().split('T')[0];
}
function setPeriodLastMonth() {
    var now = new Date();
    document.getElementById('periodStart').value = new Date(now.getFullYear(), now.getMonth()-1, 1).toISOString().split('T')[0];
    document.getElementById('periodEnd').value   = new Date(now.getFullYear(), now.getMonth(), 0).toISOString().split('T')[0];
}

// Type toggle
document.querySelectorAll('input[name="settlement_type_radio"]').forEach(function(r) {
    r.addEventListener('change', function() {
        var isDriver = this.value === 'driver';
        document.getElementById('driverSelect').classList.toggle('d-none', !isDriver);
        document.getElementById('partnerSelect').classList.toggle('d-none', isDriver);
        resetPreview();
    });
});

function resetPreview() {
    document.getElementById('previewCard').style.display = 'none';
    document.getElementById('emptyState').style.display  = '';
    document.getElementById('settlementForm').style.display = 'none';
}

function fmt(n) { return new Intl.NumberFormat().format(Math.abs(parseInt(n)||0)); }

document.getElementById('calcBtn').addEventListener('click', function() {
    var type = document.querySelector('input[name="settlement_type_radio"]:checked').value;
    var uid  = type === 'driver'
        ? document.getElementById('driverDropdown').value
        : document.getElementById('partnerDropdown').value;
    var start = document.getElementById('periodStart').value;
    var end   = document.getElementById('periodEnd').value;

    if (!uid) { alert('Please select a ' + type + '.'); return; }
    if (!start || !end) { alert('Please select period dates.'); return; }

    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Calculating...';
    this.disabled  = true;
    var btn = this;

    fetch('{{ route('admin.settlements.preview') }}?' + new URLSearchParams({
        settlement_type: type, user_id: uid, period_start: start, period_end: end
    }), {
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'}
    })
    .then(r => r.json())
    .then(data => {
        btn.innerHTML = '<i class="fas fa-calculator mr-2"></i>Calculate Settlement';
        btn.disabled  = false;

        if (data.errors) {
            alert(Object.values(data.errors).flat().join('\n'));
            return;
        }

        var html = '<div class="mb-3"><strong class="h6">' + data.user_name + '</strong> <small class="text-muted">(' + data.user_phone + ')</small></div>';
        html += '<div class="row mb-2">';

        if (type === 'driver') {
            html += card('Rides', data.rides_count, 'info');
            html += card('Deliveries', data.deliveries_count, 'primary');
            html += card('Gross Earnings', fmt(data.gross_earnings) + ' ៛', 'success');
            html += card('Commission', fmt(data.commission_total) + ' ៛', 'danger');
            html += card('Tips', fmt(data.tips_total) + ' ៛', 'warning');
            html += card('COD (owed)', fmt(data.cod_collected) + ' ៛', 'orange');
        } else {
            html += card('Orders', data.orders_count, 'info');
            html += card('Delivery Fees', fmt(data.delivery_fees) + ' ៛', 'danger');
            html += card('COD Handled', fmt(data.cod_handled) + ' ៛', 'warning');
        }

        html += '</div>';
        var net = parseInt(data.net_payout);
        var netClass = net >= 0 ? 'success' : 'danger';
        var netLabel = net >= 0 ? 'Company Pays Out' : 'Entity Owes Company';
        html += '<div class="alert alert-' + netClass + ' text-center mb-3">';
        html += '<div class="h5 mb-0"><i class="fas fa-' + (net>=0?'arrow-up':'arrow-down') + '-left mr-2"></i>';
        html += 'Net Payout: <strong>' + fmt(net) + ' ៛</strong></div>';
        html += '<small>' + netLabel + '</small></div>';

        if (data.wallet_balance !== undefined) {
            html += '<div class="text-muted small text-center">Current wallet balance: <strong>' + fmt(data.wallet_balance) + ' ៛</strong></div>';
        }

        document.getElementById('previewContent').innerHTML = html;
        document.getElementById('previewCard').style.display = '';
        document.getElementById('emptyState').style.display  = 'none';

        // Fill hidden form fields
        document.getElementById('formType').value   = type;
        document.getElementById('formUserId').value = uid;
        document.getElementById('formStart').value  = start;
        document.getElementById('formEnd').value    = end;
        if (data.bank_name)    document.getElementById('formBankName').value    = data.bank_name;
        if (data.bank_account) document.getElementById('formBankAccount').value = data.bank_account;
        document.getElementById('settlementForm').style.display = '';
    })
    .catch(err => {
        btn.innerHTML = '<i class="fas fa-calculator mr-2"></i>Calculate Settlement';
        btn.disabled  = false;
        alert('Error calculating settlement. Please try again.');
        console.error(err);
    });
});

function card(label, value, color) {
    var bgMap = {orange: 'warning'};
    var bg = bgMap[color] || color;
    return '<div class="col-6 mb-2"><div class="card shadow-none border"><div class="card-body p-2 text-center">' +
        '<div class="h6 mb-0 text-' + bg + '">' + value + '</div>' +
        '<small class="text-muted">' + label + '</small>' +
        '</div></div></div>';
}
</script>
@endpush
