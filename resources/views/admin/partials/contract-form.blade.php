<div class="form-group">
    <label>Partner <span class="text-danger">*</span></label>
    <select name="partner_id" class="form-control" required>
        <option value="">— Select Partner —</option>
        @foreach($partners as $p)
            <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->phone }})</option>
        @endforeach
    </select>
    <small class="text-muted">Only users with role = Partner are listed.</small>
</div>
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Base Fee (KHR) <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" name="base_fee" class="form-control" value="{{ $defaults['base_fee'] }}" min="0" step="100" required>
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
        <small class="text-muted">Flat charge per delivery</small>
    </div>
    <div class="form-group col-md-6">
        <label>Per Km Rate (KHR) <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" name="per_km_rate" class="form-control" value="{{ $defaults['per_km_rate'] }}" min="0" step="100" required>
            <div class="input-group-append"><span class="input-group-text">៛/km</span></div>
        </div>
        <small class="text-muted">Charged per km of route distance</small>
    </div>
</div>
<div class="form-row">
    <div class="form-group col-md-4">
        <label>Surcharge — Small</label>
        <div class="input-group">
            <input type="number" name="surcharge_small" class="form-control" value="{{ $defaults['surcharge_small'] }}" min="0" step="100">
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
    </div>
    <div class="form-group col-md-4">
        <label>Surcharge — Medium</label>
        <div class="input-group">
            <input type="number" name="surcharge_medium" class="form-control" value="{{ $defaults['surcharge_medium'] }}" min="0" step="100">
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
    </div>
    <div class="form-group col-md-4">
        <label>Surcharge — Large</label>
        <div class="input-group">
            <input type="number" name="surcharge_large" class="form-control" value="{{ $defaults['surcharge_large'] }}" min="0" step="100">
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
    </div>
</div>
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Minimum Fee (KHR)</label>
        <div class="input-group">
            <input type="number" name="min_fee" class="form-control" value="{{ $defaults['min_fee'] }}" min="0" step="100">
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
        <small class="text-muted">Delivery fee will never go below this amount</small>
    </div>
    <div class="form-group col-md-6 d-flex align-items-center pt-2">
        <div class="custom-control custom-switch mt-3">
            <input type="checkbox" class="custom-control-input" name="is_active" id="a-active" value="1" checked>
            <label class="custom-control-label" for="a-active">Active immediately</label>
        </div>
    </div>
</div>
<div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes (e.g. contract period, special terms)"></textarea>
</div>

{{-- Live fee preview --}}
<div class="card bg-light mt-2">
    <div class="card-body py-2">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <div class="small text-muted font-weight-bold">Example Fee Preview</div>
                <div class="small text-muted">5 km, Medium package</div>
            </div>
            <div class="h5 mb-0 font-weight-bold text-primary" id="preview-fee">—</div>
        </div>
    </div>
</div>

<script>
(function() {
    function fmt(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function calcPreview() {
        var form    = document.querySelector('#addModal form, #editModal form');
        if (!form) return;
        var base    = parseInt(form.querySelector('[name=base_fee]').value)    || 0;
        var perkm   = parseInt(form.querySelector('[name=per_km_rate]').value) || 0;
        var med     = parseInt(form.querySelector('[name=surcharge_medium]').value) || 0;
        var minFee  = parseInt(form.querySelector('[name=min_fee]').value)     || 0;
        var raw     = base + Math.ceil(5 * perkm) + med;
        var fee     = Math.ceil(raw / 100) * 100;
        fee         = Math.max(fee, minFee);
        var el      = document.getElementById('preview-fee');
        if (el) el.textContent = fmt(fee) + ' ៛';
    }
    document.addEventListener('input', function(e) {
        if (['base_fee','per_km_rate','surcharge_medium','min_fee'].includes(e.target.name)) {
            calcPreview();
        }
    });
    setTimeout(calcPreview, 300);
})();
</script>
