{{-- A <select> for <x-admin.filter-bar>. :options is [value => label] or a plain list; :value overrides the selected option. --}}
@props(['name', 'options' => [], 'all' => 'All', 'value' => null])
@php
    $isList  = array_is_list(is_array($options) ? $options : $options->all());
    $current = (string) ($value ?? request($name)); // :value when the page has a default other than "all"
@endphp
<select name="{{ $name }}" {{ $attributes->class(['form-control form-control-sm']) }} aria-label="{{ $all }}">
    <option value="">{{ $all }}</option>
    @foreach($options as $value => $label)
        @php $value = $isList ? $label : $value; @endphp
        <option value="{{ $value }}" @selected($current === (string) $value)>{{ $label }}</option>
    @endforeach
</select>
