@extends('admin.layout')
@section('title', 'Banners')
@section('page-title', 'App Banners')

@section('content')

<x-admin.page-toolbar subtitle="Image banners shown at the top of the Passenger and Driver apps.">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Upload Banner</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Passenger App" :value="number_format($stats['live_passenger'])" icon="fa-user" tone="info"
                       :sub="'live now · ' . number_format($stats['all_passenger']) . ' total'"
                       :href="route('admin.banners', ['app' => 'passenger', 'status' => 'live'])"
                       :active="$app === 'passenger' && request('status') === 'live'" />
    <x-admin.stat-card label="Driver App" :value="number_format($stats['live_driver'])" icon="fa-car-side" tone="warning"
                       :sub="'live now · ' . number_format($stats['all_driver']) . ' total'"
                       :href="route('admin.banners', ['app' => 'driver', 'status' => 'live'])"
                       :active="$app === 'driver' && request('status') === 'live'" />
    <x-admin.stat-card label="Scheduled" :value="number_format($stats['scheduled'])" icon="fa-calendar-days" tone="violet" sub="start in the future"
                       :href="route('admin.banners', ['status' => 'scheduled'])" :active="!$app && request('status') === 'scheduled'" />
    <x-admin.stat-card label="Expired" :value="number_format($stats['expired'])" icon="fa-hourglass-end" tone="muted" sub="past their end date"
                       :href="route('admin.banners', ['status' => 'expired'])" :active="!$app && request('status') === 'expired'" />
</x-admin.stat-grid>

@php
    $now = now();
    $tabs = [
        ''          => ['All banners', 'fa-images', $stats['total']],
        'passenger' => ['Passenger app', 'fa-user', $stats['all_passenger']],
        'driver'    => ['Driver app', 'fa-car-side', $stats['all_driver']],
    ];
@endphp
<div class="card">
    <div class="card-header">
        <nav class="card-tabs">
            @foreach($tabs as $key => [$label, $icon, $count])
                <a href="{{ route('admin.banners', array_filter(['app' => $key])) }}" @class(['active' => (string) $app === $key])>
                    <i class="fas {{ $icon }}"></i> {{ $label }} <span class="count">{{ number_format($count) }}</span>
                </a>
            @endforeach
        </nav>
        <x-admin.filter-bar :action="route('admin.banners')" placeholder="Search title or deeplink" :count="$banners->total()" noun="banner" :keep="['app']">
            <x-admin.filter-select name="status" :options="['live' => 'Live now', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'inactive' => 'Turned off']" all="All statuses" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Banner</th>
                    <th>Shown in</th>
                    <th>Schedule</th>
                    <th class="text-center">Order</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($banners as $b)
                @php
                    [$state, $tone] = match (true) {
                        ! $b->active                                  => ['Turned off', 'muted'],
                        $b->valid_until && $b->valid_until->lt($now)  => ['Expired', 'muted'],
                        $b->valid_from && $b->valid_from->gt($now)    => ['Scheduled', 'info'],
                        default                                       => ['Live', 'success'],
                    };
                @endphp
                <tr data-row="{{ json_encode([
                        'id'          => $b->id,
                        ...$b->only(['title_en', 'title_kh', 'title_zh']),
                        'title'       => $b->title,
                        'image_url'   => $b->image_url,
                        'deeplink'    => $b->deeplink ?? '',
                        'target_role' => $b->target_role,
                        'sort_order'  => $b->sort_order,
                        'active'      => $b->active,
                        'valid_from'  => $b->valid_from?->format('Y-m-d\TH:i') ?? '',
                        'valid_until' => $b->valid_until?->format('Y-m-d\TH:i') ?? '',
                    ]) }}">
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            <a href="{{ $b->image_url }}" target="_blank" rel="noopener" title="Open full image">
                                <img src="{{ $b->image_url }}" alt="" loading="lazy"
                                     style="width:120px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--ink-200);background:var(--ink-50);"
                                     onerror="this.style.visibility='hidden'">
                            </a>
                            <div class="tw:min-w-0">
                                <div class="text-truncate" style="max-width:280px;font-weight:600;color:var(--ink-900);" title="{{ $b->title }}">{{ $b->title }}</div>
                                <div class="text-muted text-truncate tw:text-xs" style="max-width:280px;" title="{{ $b->deeplink }}">
                                    <i class="fas fa-link mr-1"></i>{{ $b->deeplink ?: 'No link' }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="tw:flex tw:gap-1">
                            @if(in_array($b->target_role, ['all', 'passenger']))
                                <span class="app-chip is-passenger"><i class="fas fa-user"></i> Passenger</span>
                            @endif
                            @if(in_array($b->target_role, ['all', 'driver']))
                                <span class="app-chip is-driver"><i class="fas fa-car-side"></i> Driver</span>
                            @endif
                        </div>
                    </td>
                    <td>
                        @if($b->valid_from || $b->valid_until)
                            <div>{{ $b->valid_from?->format('d M Y') ?? 'Any time' }} → {{ $b->valid_until?->format('d M Y') ?? 'No end' }}</div>
                            @if($b->valid_until && $b->valid_until->gte($now))
                                <div class="text-muted tw:text-xs">ends {{ $b->valid_until->diffForHumans() }}</div>
                            @endif
                        @else
                            <span class="text-muted">Always on</span>
                        @endif
                    </td>
                    <td class="text-center"><span class="badge badge-light">{{ $b->sort_order }}</span></td>
                    <td><span class="status-pill tone-{{ $tone }}">{{ $state }}</span></td>
                    <td class="text-center">
                        <x-admin.row-actions edit="openEdit(this.closest('tr'))"
                                             :delete="route('admin.banners.destroy', $b)" :confirm="'Delete banner “' . $b->title . '”?'" />
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="6" icon="fa-images" title="No banners yet" :keep="['app']"
                                   hint="Upload one to promote offers in the app." :clear="route('admin.banners', array_filter(['app' => $app]))" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$banners" />
</div>

<div class="modal fade" id="bannerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bannerModalTitle">Upload Banner</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="bannerForm" method="POST" enctype="multipart/form-data" action="{{ route('admin.banners.store') }}">
                @csrf
                <input type="hidden" name="_method" id="bannerMethod" value="POST">
                <div class="modal-body">
                    <div class="form-section-title">Show in</div>
                    <div class="choice-cards">
                        @foreach([
                            'all'       => ['Both apps', 'Passenger and Driver', 'fa-mobile-screen'],
                            'passenger' => ['Passenger app', 'Riders only', 'fa-user'],
                            'driver'    => ['Driver app', 'Drivers only', 'fa-car-side'],
                        ] as $value => [$title, $sub, $icon])
                            <label class="choice-card">
                                <input type="radio" name="target_role" value="{{ $value }}" @checked($value === 'all') required>
                                <span class="choice-body">
                                    <span class="choice-icon"><i class="fas {{ $icon }}"></i></span>
                                    <span><span class="choice-title d-block">{{ $title }}</span><span class="choice-sub">{{ $sub }}</span></span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="form-section-title">Image <span class="text-danger" id="imgRequired">*</span></div>
                    <label class="drop-zone mb-1" id="dropZone">
                        <input type="file" name="image" id="b-image" accept="image/jpeg,image/png,image/webp">
                        <img id="b-preview" alt="" hidden>
                        <span class="drop-hint">
                            <i class="fas fa-cloud-arrow-up d-block"></i>
                            <span style="font-weight:600;color:var(--ink-800);">Click or drop an image here</span><br>
                            <small>JPG, PNG or WEBP · max 4 MB · 1200 × 400 recommended (3:1)</small>
                        </span>
                        <span class="drop-change"><i class="fas fa-arrows-rotate"></i> Change image</span>
                    </label>
                    <small class="text-muted d-block" id="b-file-info">&nbsp;</small>

                    <div class="form-section-title">Content</div>
                    <div class="form-group">
                        <label>Title <small class="text-muted">(English)</small> <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="b-title" class="form-control" required maxlength="100">
                    </div>
                    @include('admin.partials.localized-inputs', ['field' => 'title', 'label' => 'Title', 'idPrefix' => 'b-title', 'maxlength' => 100])
                    <div class="form-group">
                        <label>Deeplink <small class="text-muted">(optional, opens when tapped, e.g. autoride://promo/10)</small></label>
                        <input type="text" name="deeplink" id="b-deeplink" class="form-control" maxlength="255">
                    </div>

                    <div class="form-section-title">Schedule</div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Start <small class="text-muted">(empty = now)</small></label>
                            <input type="datetime-local" name="valid_from" id="b-from" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>End <small class="text-muted">(empty = no end)</small></label>
                            <input type="datetime-local" name="valid_until" id="b-until" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4 mb-0">
                            <label>Sort Order <small class="text-muted">(lower shows first)</small></label>
                            <input type="number" name="sort_order" id="b-order" class="form-control" value="0" min="0" max="255">
                        </div>
                        <div class="form-group col-md-8 mb-0 d-flex align-items-end">
                            <div class="custom-control custom-switch mb-2">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" class="custom-control-input" name="active" id="b-active" value="1" checked>
                                <label class="custom-control-label" for="b-active">Active <small class="text-muted">(turn off to hide without deleting)</small></label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const storeUrl   = '{{ route("admin.banners.store") }}';
const updateBase = '{{ url("admin/banners") }}/';
const MAX_BYTES  = 4 * 1024 * 1024;

const imageInput = document.getElementById('b-image');
const dropZone   = document.getElementById('dropZone');
const preview    = document.getElementById('b-preview');
const fileInfo   = document.getElementById('b-file-info');

function showPreview(src, info) {
    preview.hidden = !src;
    preview.src = src || '';
    dropZone.classList.toggle('has-image', !!src);
    fileInfo.innerHTML = info || '&nbsp;';
}

imageInput.addEventListener('change', () => {
    const file = imageInput.files[0];
    if (!file) return;
    if (file.size > MAX_BYTES) {
        imageInput.value = '';
        showPreview(null, '<span class="text-danger">That file is ' + (file.size / 1048576).toFixed(1) + ' MB. The limit is 4 MB.</span>');
        return;
    }
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
        const ratio = img.naturalWidth / img.naturalHeight;
        const warn  = Math.abs(ratio - 3) > 0.4 ? ' · <span class="text-warning">not 3:1, the app will crop it</span>' : '';
        fileInfo.innerHTML = file.name.replace(/[<>&"]/g, '') + ' · ' + img.naturalWidth + ' × ' + img.naturalHeight + ' · ' + (file.size / 1024).toFixed(0) + ' KB' + warn;
    };
    img.src = url;
    showPreview(url, file.name.replace(/[<>&"]/g, ''));
});

['dragenter', 'dragover'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.add('is-over')));
['dragleave', 'drop'].forEach(e => dropZone.addEventListener(e, () => dropZone.classList.remove('is-over')));

function resetForm(title, action, method) {
    const form = document.getElementById('bannerForm');
    form.reset();
    form.action = action;
    document.getElementById('bannerMethod').value = method;
    document.getElementById('bannerModalTitle').textContent = title;
    showPreview(null);
}

function openCreate() {
    resetForm('Upload Banner', storeUrl, 'POST');
    const tab = new URLSearchParams(location.search).get('app');
    document.querySelector('input[name=target_role][value="' + (tab || 'all') + '"]').checked = true;
    imageInput.required = true;
    document.getElementById('imgRequired').style.display = '';
    $('#bannerModal').modal('show');
}

function openEdit(row) {
    const b = JSON.parse(row.getAttribute('data-row'));
    resetForm('Edit Banner', updateBase + b.id, 'PUT');
    document.querySelector('input[name=target_role][value="' + b.target_role + '"]').checked = true;
    document.getElementById('b-title').value    = b.title_en || b.title;
    fillLocalized('b-title', b, 'title');
    document.getElementById('b-deeplink').value = b.deeplink;
    document.getElementById('b-from').value     = b.valid_from;
    document.getElementById('b-until').value    = b.valid_until;
    document.getElementById('b-order').value    = b.sort_order;
    document.getElementById('b-active').checked = !!b.active;
    imageInput.required = false;
    document.getElementById('imgRequired').style.display = 'none';
    showPreview(b.image_url, 'Current image. Pick a new file to replace it.');
    $('#bannerModal').modal('show');
}
</script>
@endpush
