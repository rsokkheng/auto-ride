@isset($partners)
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
@endisset

<h6 class="text-muted text-uppercase font-weight-bold mb-3" style="font-size:.7rem;letter-spacing:.1em;">Delivery Rates (Flat Fee)</h6>
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Normal Delivery Fee <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" name="normal_fee" class="form-control"
                   value="{{ $contract->normal_fee ?? $defaults['normal_fee'] }}" min="0" step="500" required>
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
        <small class="text-muted">Flat fee for normal delivery (Small &amp; Medium)</small>
    </div>
    <div class="form-group col-md-6">
        <label>Express Delivery Fee <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" name="express_fee" class="form-control"
                   value="{{ $contract->express_fee ?? $defaults['express_fee'] }}" min="0" step="500" required>
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
        <small class="text-muted">Flat fee for express delivery (Small &amp; Medium)</small>
    </div>
</div>

<h6 class="text-muted text-uppercase font-weight-bold mb-3 mt-2" style="font-size:.7rem;letter-spacing:.1em;">Package Size Surcharges</h6>
<div class="form-row">
    <div class="form-group col-md-6">
        <label>Large Package (+)</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text">+</span></div>
            <input type="number" name="surcharge_large" class="form-control"
                   value="{{ $contract->surcharge_large ?? $defaults['surcharge_large'] }}" min="0" step="500">
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
    </div>
    <div class="form-group col-md-6">
        <label>Extra Large Package (+)</label>
        <div class="input-group">
            <div class="input-group-prepend"><span class="input-group-text">+</span></div>
            <input type="number" name="surcharge_extra_large" class="form-control"
                   value="{{ $contract->surcharge_extra_large ?? $defaults['surcharge_extra_large'] }}" min="0" step="500">
            <div class="input-group-append"><span class="input-group-text">៛</span></div>
        </div>
    </div>
</div>

<div class="form-row align-items-center mb-3">
    <div class="col-md-6">
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" name="is_active"
                   id="is_active_{{ $formId ?? 'form' }}" value="1"
                   {{ ($contract->is_active ?? true) ? 'checked' : '' }}>
            <label class="custom-control-label" for="is_active_{{ $formId ?? 'form' }}">Active</label>
        </div>
    </div>
</div>

<div class="form-group">
    <label>Notes</label>
    <textarea name="notes" class="form-control" rows="2"
              placeholder="Optional — contract period, special terms…">{{ $contract->notes ?? '' }}</textarea>
</div>

{{-- Fee Preview Table --}}
<div class="card bg-light mt-2">
    <div class="card-header py-2"><strong class="small">Fee Preview</strong></div>
    <div class="card-body p-0">
        <table class="table table-sm mb-0 text-center">
            <thead class="thead-light">
                <tr><th>Package</th><th class="text-primary">Normal</th><th class="text-danger">Express</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-left text-muted">Small / Medium</td>
                    <td class="font-weight-bold text-primary" id="prev-n-sm-{{ $formId ?? 'form' }}">—</td>
                    <td class="font-weight-bold text-danger" id="prev-e-sm-{{ $formId ?? 'form' }}">—</td>
                </tr>
                <tr>
                    <td class="text-left text-muted">Large</td>
                    <td class="font-weight-bold text-primary" id="prev-n-lg-{{ $formId ?? 'form' }}">—</td>
                    <td class="font-weight-bold text-danger" id="prev-e-lg-{{ $formId ?? 'form' }}">—</td>
                </tr>
                <tr>
                    <td class="text-left text-muted">Extra Large</td>
                    <td class="font-weight-bold text-primary" id="prev-n-xl-{{ $formId ?? 'form' }}">—</td>
                    <td class="font-weight-bold text-danger" id="prev-e-xl-{{ $formId ?? 'form' }}">—</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    var fid = '{{ $formId ?? "form" }}';
    function fmt(n) { return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' ៛'; }
    function update() {
        var scope = document.getElementById('addModal') || document.getElementById('editModal') || document;
        function v(name) { var el = scope.querySelector('[name=' + name + ']'); return el ? (parseInt(el.value) || 0) : 0; }
        var n = v('normal_fee'), e = v('express_fee');
        var sl = v('surcharge_large'), sx = v('surcharge_extra_large');
        document.getElementById('prev-n-sm-' + fid).textContent = fmt(n);
        document.getElementById('prev-e-sm-' + fid).textContent = fmt(e);
        document.getElementById('prev-n-lg-' + fid).textContent = fmt(n + sl);
        document.getElementById('prev-e-lg-' + fid).textContent = fmt(e + sl);
        document.getElementById('prev-n-xl-' + fid).textContent = fmt(n + sx);
        document.getElementById('prev-e-xl-' + fid).textContent = fmt(e + sx);
    }
    document.addEventListener('input', function(ev) {
        if (['normal_fee','express_fee','surcharge_large','surcharge_extra_large'].includes(ev.target.name)) update();
    });
    setTimeout(update, 300);
})();
</script>
