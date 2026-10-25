{{--
    Khmer + Chinese inputs for a localized column (the English value stays in
    the page's existing `{field}` input). See App\Traits\HasLocalizedName.

    @include('admin.partials.localized-inputs', [
        'field'     => 'title',          // base column
        'label'     => 'Title',
        'idPrefix'  => 'b-title',        // optional: ids become b-title-kh / b-title-zh for fillLocalized()
        'model'     => $banner ?? null,  // optional: server-rendered value
        'textarea'  => false,            // optional
        'maxlength' => 100,              // optional
        'rows'      => 2,                // optional, textarea only
    ])
--}}
@php
    $model     = $model ?? null;
    $textarea  = $textarea ?? false;
    $maxlength = $maxlength ?? null;
    $idPrefix  = $idPrefix ?? null;
    $rows      = $rows ?? 2;
    $langs     = ['kh' => 'ខ្មែរ', 'zh' => '中文'];
@endphp
<div class="form-row">
    @foreach($langs as $suffix => $langName)
        @php
            $name  = "{$field}_{$suffix}";
            $value = old($name, $model?->getAttributes()[$name] ?? '');
        @endphp
        <div class="form-group col-md-6">
            <label>{{ $label }} <small class="text-muted">({{ $langName }})</small></label>
            @if($textarea)
                <textarea name="{{ $name }}" @if($idPrefix) id="{{ $idPrefix }}-{{ $suffix }}" @endif class="form-control" rows="{{ $rows }}"
                          @if($maxlength) maxlength="{{ $maxlength }}" @endif>{{ $value }}</textarea>
            @else
                <input type="text" name="{{ $name }}" @if($idPrefix) id="{{ $idPrefix }}-{{ $suffix }}" @endif class="form-control"
                       value="{{ $value }}" @if($maxlength) maxlength="{{ $maxlength }}" @endif>
            @endif
        </div>
    @endforeach
</div>

@once
@push('scripts')
<script>
    // Fill the kh / zh inputs from a model's JSON (e.g. data-* attribute) when opening an edit modal.
    window.fillLocalized = function (idPrefix, obj, field) {
        ['kh', 'zh'].forEach(function (s) {
            var el = document.getElementById(idPrefix + '-' + s);
            if (el) el.value = (obj && obj[field + '_' + s]) || '';
        });
    };
</script>
@endpush
@endonce
