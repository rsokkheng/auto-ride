{{-- Dashboard banner: unread new purchase / rental orders. Kept live by notification-script. --}}
@php
    $anBanner = rescue(function () {
        $latest = fn (string $type) => \App\Models\AdminNotification::forUser(Auth::id())->unread()->where('type', $type)->latest('id');
        return [
            'purchase' => ['count' => $latest('PURCHASE_ORDER')->count(), 'latest' => $latest('PURCHASE_ORDER')->first()?->toPayload()],
            'rental'   => ['count' => $latest('RENTAL_ORDER')->count(),   'latest' => $latest('RENTAL_ORDER')->first()?->toPayload()],
        ];
    }, ['purchase' => ['count' => 0, 'latest' => null], 'rental' => ['count' => 0, 'latest' => null]], false);
@endphp
<style>
    .an-banner { display: flex; align-items: center; gap: 14px; border-radius: 12px; padding: 14px 16px; margin-bottom: 16px; border: 1px solid; }
    .an-banner.purchase { background: #f0fdf4; border-color: #bbf7d0; }
    .an-banner.rental   { background: #fef2f2; border-color: #fecaca; }
    .an-banner .an-icon { width: 40px; height: 40px; }
    .an-banner-title { font-weight: 700; color: #1e293b; font-size: .9rem; }
    .an-banner-sub { font-size: .8rem; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .an-banner .btn { border-radius: 8px; font-size: .8rem; font-weight: 600; white-space: nowrap; }
    .an-banner .close { font-size: 1.2rem; opacity: .4; }
</style>
<div class="row" id="anBanners">
    @foreach(['purchase' => ['New Purchase Order', 'fa-cart-shopping', 'green', 'btn-success'], 'rental' => ['New Rental Order', 'fa-calendar-days', 'red', 'btn-danger']] as $key => [$label, $icon, $tone, $btn])
        @php $b = $anBanner[$key]; @endphp
        <div class="col-md-6" data-banner="{{ $key }}" @if(! $b['count']) style="display:none" @endif>
            <div class="an-banner {{ $key }}">
                <div class="an-icon an-tone-{{ $tone }}"><i class="fas {{ $icon }}"></i></div>
                <div class="flex-grow-1" style="min-width:0;">
                    <div class="an-banner-title">{{ $label }} <span class="badge badge-pill {{ $key === 'purchase' ? 'badge-success' : 'badge-danger' }}" data-banner-count>{{ $b['count'] }}</span></div>
                    <div class="an-banner-sub" data-banner-sub>{{ $b['latest'] ? $b['latest']['message'] . ($b['latest']['detail'] ? ' · ' . $b['latest']['detail'] : '') : '' }}</div>
                </div>
                <a href="{{ $b['latest']['url'] ?? '#' }}" class="btn btn-sm {{ $btn }}" data-banner-link>View Order</a>
                <button type="button" class="close" onclick="this.closest('[data-banner]').style.display='none'" aria-label="Dismiss">&times;</button>
            </div>
        </div>
    @endforeach
</div>
