{{-- Bell dropdown + realtime alerts. Needs jQuery/Bootstrap loaded first. --}}
@php
    $reverb = config('broadcasting.connections.reverb');
    $prod   = app()->isProduction();
    $client = [
        'host'   => ($reverb['client']['host'] ?? null) ?: ($prod ? request()->getHost() : ($reverb['options']['host'] ?? request()->getHost())),
        'port'   => ($reverb['client']['port'] ?? null) ?: ($prod ? 443 : ($reverb['options']['port'] ?? 443)),
        'scheme' => ($reverb['client']['scheme'] ?? null) ?: ($prod ? 'https' : ($reverb['options']['scheme'] ?? 'https')),
    ];
    $anConfig = [
        'userId'     => Auth::id(),
        'feedUrl'    => route('admin.notifications.feed'),
        'readAllUrl' => route('admin.notifications.read-all'),
        'csrf'       => csrf_token(),
        'realtime'   => config('broadcasting.default') === 'reverb' && ! empty($reverb['key']) ? [
            'key'          => $reverb['key'],
            'host' => config('broadcasting.connections.reverb.client.host'),
            'port' => (int) config('broadcasting.connections.reverb.client.port', 443),
            'tls' => config('broadcasting.connections.reverb.client.scheme', 'https') === 'https',
            'authEndpoint' => route('admin.broadcasting.auth'),
        ] : null,
    ];
@endphp
@if($anConfig['realtime'])
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
@endif
<script>
(function ($) {
    const cfg = @json($anConfig);
    const $panel = $('#anPanel'), $list = $('#anList');
    let category = 'all', loaded = false, connected = false;

    const esc = s => $('<div>').text(s ?? '').html();

    function ago(iso) {
        const s = Math.max(0, (Date.now() - new Date(iso).getTime()) / 1000);
        if (s < 60) return 'just now';
        if (s < 3600) return Math.floor(s / 60) + ' min ago';
        if (s < 86400) return Math.floor(s / 3600) + ' hr ago';
        if (s < 604800) return Math.floor(s / 86400) + ' d ago';
        return new Date(iso).toLocaleDateString();
    }

    function renderItem(n) {
        return `<a href="${esc(n.url)}" class="an-item ${n.read ? '' : 'unread'}" data-id="${n.id}">
            <div class="an-icon an-tone-${esc(n.tone)}"><i class="fas ${esc(n.icon)}"></i></div>
            <div class="an-body">
                <div class="an-title">${esc(n.title)}</div>
                <div class="an-msg">${esc(n.message)}</div>
                ${n.detail ? `<div class="an-detail">${esc(n.detail)}</div>` : ''}
            </div>
            <div class="an-side">
                <div><span class="an-time" data-time="${esc(n.created_at)}">${ago(n.created_at)}</span> <i class="fas fa-arrow-right an-arrow"></i></div>
                ${n.read ? '' : '<span class="an-dot"></span>'}
            </div>
        </a>`;
    }

    function setCounts(c) {
        const all = c.all || 0;
        $('#anBadge').text(all > 99 ? '99+' : all).toggle(all > 0);
        $('[data-count]').each(function () { $(this).text(c[$(this).data('count')] || 0); });
        document.title = document.title.replace(/^\(\d+\+?\) /, '');
        if (all > 0) document.title = `(${all > 99 ? '99+' : all}) ` + document.title;
    }

    function load() {
        $.getJSON(cfg.feedUrl, { category: category === 'all' ? '' : category }).done(res => {
            loaded = true;
            setCounts(res.counts);
            $list.html(res.items.length ? res.items.map(renderItem).join('') : '<div class="an-empty"><i class="far fa-bell-slash mb-2 d-block" style="font-size:1.4rem;"></i>No notifications yet</div>');
        });
    }

    // Keep the dropdown open while switching tabs / marking read.
    $panel.on('click', e => { if (! $(e.target).closest('a.an-item, .an-foot a').length) e.stopPropagation(); });
    $('#anBell').on('show.bs.dropdown', load);

    $panel.on('click', '.an-tab', function () {
        category = $(this).data('category');
        $panel.find('.an-tab').removeClass('active');
        $(this).addClass('active');
        load();
    });

    $('#anReadAll').on('click', function () {
        $.ajax({ url: cfg.readAllUrl, method: 'POST', headers: { 'X-CSRF-TOKEN': cfg.csrf, Accept: 'application/json' } }).done(res => {
            setCounts(res.counts);
            $list.find('.an-item').removeClass('unread').find('.an-dot').remove();
            $('[data-banner]').hide();
        });
    });

    // Relative times tick over without a reload.
    setInterval(() => $('.an-time[data-time]').each(function () { $(this).text(ago($(this).data('time'))); }), 60000);

    function updateBanner(n) {
        const key = n.type === 'PURCHASE_ORDER' ? 'purchase' : n.type === 'RENTAL_ORDER' ? 'rental' : null;
        const $b = key && $(`[data-banner="${key}"]`);
        if (! $b || ! $b.length) return;
        const $count = $b.find('[data-banner-count]');
        $count.text((parseInt($count.text(), 10) || 0) + 1);
        $b.find('[data-banner-sub]').text(n.message + (n.detail ? ' · ' + n.detail : ''));
        $b.find('[data-banner-link]').attr('href', n.url);
        $b.show();
    }

    function toast(n) {
        let $wrap = $('.an-toasts');
        if (! $wrap.length) $wrap = $('<div class="an-toasts"></div>').appendTo('body');
        const $t = $(`<div class="an-toast">${renderItem(n)}</div>`).appendTo($wrap);
        setTimeout(() => $t.fadeOut(300, () => $t.remove()), 8000);
    }

    function onNotification(e) {
        const n = e.notification;
        setCounts(e.counts);
        if (loaded && (category === 'all' || category === n.category)) {
            $list.find('.an-empty').remove();
            $list.prepend(renderItem(n));
        }
        updateBanner(n);
        toast(n);
    }

    if (cfg.realtime && window.Pusher) {
        const pusher = new Pusher(cfg.realtime.key, {
            cluster: '',
            wsHost: cfg.realtime.host,
            wsPort: cfg.realtime.port,
            wssPort: cfg.realtime.port,
            forceTLS: cfg.realtime.tls,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: cfg.realtime.authEndpoint,
            auth: { headers: { 'X-CSRF-TOKEN': cfg.csrf, Accept: 'application/json' } },
        });
        pusher.connection.bind('state_change', s => {
            connected = s.current === 'connected';
            // Shows in DevTools → Console; "unavailable"/"failed" means the browser can't reach Reverb.
            console[connected ? 'info' : 'warn'](`[notifications] realtime ${s.current} (${cfg.realtime.tls ? 'wss' : 'ws'}://${cfg.realtime.host}:${cfg.realtime.port})`);
        });
        pusher.subscribe('private-admin.' + cfg.userId).bind('admin_notification.created', onNotification);
    }

    // Fallback while the socket is down (or Reverb isn't configured): refresh the badge.
    setInterval(() => {
        if (connected || document.hidden) return;
        $.getJSON(cfg.feedUrl).done(res => setCounts(res.counts));
    }, 60000);
})(jQuery);
</script>
