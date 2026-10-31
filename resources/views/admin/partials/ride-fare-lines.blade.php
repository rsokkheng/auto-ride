{{--
    Full price breakdown for one ride. $p comes from the rides page's $info():
    trip, surge, airport, waiting, discount, fare, tip, cancel, paid, fee, driver, estimated.
--}}
@php $k = fn ($v) => number_format($v) . ' ៛'; @endphp
@if(! $p['fare'] && ! $p['cancel'])
    <span class="badge badge-light">Metered — not priced yet</span>
@else
<div class="fare-lines">
    @if($p['fare'])
        <div class="fl"><span>Trip fare</span><span>{{ $k($p['trip']) }}</span></div>
        @if($p['surge'] || $p['airport'])
            <div class="fl sub"><span>
                @if($p['surge'])incl. surge ×{{ $p['surge'] }}@endif
                @if($p['surge'] && $p['airport']) · @endif
                @if($p['airport'])incl. airport {{ number_format($p['airport']) }}@endif
            </span><span></span></div>
        @endif
        @if($p['waiting'])<div class="fl"><span>Waiting fee</span><span>+{{ $k($p['waiting']) }}</span></div>@endif
        @if($p['discount'])<div class="fl minus"><span>Promo discount</span><span>−{{ $k($p['discount']) }}</span></div>@endif
        <div class="fl total"><span>Fare</span><span>{{ $k($p['fare']) }}</span></div>
        @if($p['tip'])
            <div class="fl"><span>Tip</span><span>+{{ $k($p['tip']) }}</span></div>
            <div class="fl total"><span>Customer paid</span><span>{{ $k($p['paid']) }}</span></div>
        @endif
    @endif
    @if($p['cancel'])
        <div class="fl total"><span>Cancellation fee</span><span>{{ $k($p['cancel']) }}</span></div>
    @endif
    @if($p['fare'])
        <div class="sep"></div>
        <div class="fl split"><span>Platform fee{{ $p['estimated'] ? ' (est.)' : '' }}</span><span>{{ $k($p['fee']) }}</span></div>
        @if($p['company'])
            <div class="fl split"><span>{{ $p['companyName'] ?: 'Company' }} share</span><span>{{ $k($p['company']) }}</span></div>
        @endif
        <div class="fl driver"><span>{{ $p['salary'] ? 'Driver (on salary)' : 'Driver earns' }}</span><span>{{ $k($p['driver']) }}</span></div>
    @endif
    <div class="fl sub tw:mt-1"><span>
        {{ $payLabels[$r->payment_method] ?? ucfirst((string) $r->payment_method ?: 'Cash') }}
        @if($r->payment_status) · <span style="color:{{ $r->payment_status === 'paid' ? 'var(--success)' : 'var(--warning)' }};">{{ ucfirst($r->payment_status) }}</span>@endif
    </span><span></span></div>
</div>
@endif
