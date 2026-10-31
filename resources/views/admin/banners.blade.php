@extends('admin.layout')
@section('title', 'Banners')
@section('page-title', 'Promotional Banners')

@section('content')

<x-admin.page-toolbar subtitle="Image banners shown at the top of the passenger and driver apps.">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Create Banner</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Total Banners" :value="number_format($stats['total'])" icon="fa-images" tone="primary"
                       :href="route('admin.banners')" :active="!request('status')" />
    <x-admin.stat-card label="Live Now" :value="number_format($stats['live'])" icon="fa-circle-play" tone="success" sub="active and in date"
                       :href="route('admin.banners', ['status' => 'live'])" :active="request('status') === 'live'" />
    <x-admin.stat-card label="Scheduled" :value="number_format($stats['scheduled'])" icon="fa-calendar-days" tone="info" sub="start in the future"
                       :href="route('admin.banners', ['status' => 'scheduled'])" :active="request('status') === 'scheduled'" />
    <x-admin.stat-card label="Expired" :value="number_format($stats['expired'])" icon="fa-hourglass-end" tone="muted" sub="past their end date"
                       :href="route('admin.banners', ['status' => 'expired'])" :active="request('status') === 'expired'" />
</x-admin.stat-grid>

@php
    $now = now();
    $audiences = ['all' => 'Everyone', 'passenger' => 'Passengers', 'driver' => 'Drivers'];
@endphp
<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.banners')" placeholder="Search title or deeplink" :count="$banners->total()" noun="banner">
            <x-admin.filter-select name="status" :options="['live' => 'Live now', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'inactive' => 'Turned off']" all="All statuses" />
            <x-admin.filter-select name="audience" :options="$audiences" all="All audiences" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Banner</th>
                    <th>Audience</th>
                    <th>Validity</th>
                    <th class="text-center">Order</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($banners as $b)
                @php
                    [$state, $tone] = match (true) {
                        ! $b->active                                  => ['Turned off', 'secondary'],
                        $b->valid_until && $b->valid_until->lt($now)  => ['Expired', 'secondary'],
                        $b->valid_from && $b->valid_from->gt($now)    => ['Scheduled', 'info'],
                        default                                       => ['Live', 'success'],
                    };
                    $aTone = ['all' => 'primary', 'passenger' => 'info', 'driver' => 'warning'][$b->target_role] ?? 'secondary';
                @endphp
                <tr data-banner="{{ json_encode([
                        'id'          => $b->id,
                        ...$b->only(['title_en', 'title_kh', 'title_zh']),
                        'title'       => $b->title,
                        'deeplink'    => $b->deeplink ?? '',
                        'target_role' => $b->target_role,
                        'sort_order'  => $b->sort_order,
                        'active'      => $b->active,
                        'valid_from'  => $b->valid_from?->format('Y-m-d\TH:i') ?? '',
                        'valid_until' => $b->valid_until?->format('Y-m-d\TH:i') ?? '',
                    ]) }}">
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            <img src="{{ asset('storage/' . $b->image) }}" alt="" loading="lazy"
                                 style="width:88px;height:48px;object-fit:cover;border-radius:8px;border:1px solid var(--ink-200);background:var(--ink-50);"
                                 onerror="this.style.visibility='hidden'">
                            <div class="tw:min-w-0">
                                <div class="text-truncate" style="max-width:280px;font-weight:600;color:var(--ink-900);" title="{{ $b->title }}">{{ $b->title }}</div>
                                <div class="text-muted text-truncate tw:text-xs" style="max-width:280px;" title="{{ $b->deeplink }}">
                                    <i class="fas fa-link mr-1"></i>{{ $b->deeplink ?: 'No link' }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-{{ $aTone }}">{{ $audiences[$b->target_role] ?? ucfirst($b->target_role) }}</span></td>
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
                    <td><span class="status-pill tone-{{ ['success' => 'success', 'info' => 'info', 'secondary' => 'muted'][$tone] }}">{{ $state }}</span></td>
                    <td class="text-center">
                        <x-admin.row-actions edit="openEdit(this.closest('tr'))"
                                             :delete="route('admin.banners.destroy', $b)" :confirm="'Delete banner “' . $b->title . '”?'" />
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="6" icon="fa-images" title="No banners yet"
                                   hint="Create one to promote offers in the app." :clear="route('admin.banners')" />
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
                <h5 class="modal-title" id="bannerModalTitle">Create Banner</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="bannerForm" method="POST" enctype="multipart/form-data"
                  action="{{ route('admin.banners.store') }}">
                @csrf
                <input type="hidden" name="_method" id="bannerMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Title <small class="text-muted">(English)</small> <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="b-title" class="form-control" required maxlength="100">
                    </div>
                    @include('admin.partials.localized-inputs', ['field' => 'title', 'label' => 'Title', 'idPrefix' => 'b-title', 'maxlength' => 100])
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Target Audience <span class="text-danger">*</span></label>
                            <select name="target_role" id="b-role" class="form-control" required>
                                <option value="all">All Users</option>
                                <option value="passenger">Passengers only</option>
                                <option value="driver">Drivers only</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Deeplink <small class="text-muted">(e.g. autoride://promo/10)</small></label>
                            <input type="text" name="deeplink" id="b-deeplink" class="form-control" maxlength="255">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Active From</label>
                            <input type="datetime-local" name="valid_from" id="b-from" class="form-control">
                        </div>
                        <div class="form-group col-md-6">
                            <label>Expires At</label>
                            <input type="datetime-local" name="valid_until" id="b-until" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Sort Order</label>
                            <input type="number" name="sort_order" id="b-order" class="form-control" value="0" min="0">
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" class="custom-control-input" name="active" id="b-active" value="1" checked>
                                <label class="custom-control-label" for="b-active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Banner Image <span class="text-danger" id="imgRequired">*</span> <small class="text-muted">JPG/PNG/WEBP, max 4 MB, recommended 1200×400</small></label>
                        <input type="file" name="image" id="b-image" class="form-control-file" accept="image/*">
                        <div id="imgPreview" class="mt-2" style="display:none;">
                            <img id="currentImg" src="" style="max-height:80px;border-radius:4px;">
                            <small class="text-muted ml-2">Current image (upload new to replace)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const storeUrl = '{{ route("admin.banners.store") }}';
const updateBase = '/admin/banners/';

function openCreate() {
    document.getElementById('bannerModalTitle').textContent = 'Create Banner';
    document.getElementById('bannerForm').action = storeUrl;
    document.getElementById('bannerMethod').value = 'POST';
    document.getElementById('bannerForm').reset();
    document.getElementById('imgPreview').style.display = 'none';
    document.getElementById('imgRequired').style.display = '';
    document.getElementById('b-image').required = true;
    $('#bannerModal').modal('show');
}

function openEdit(row) {
    const b = JSON.parse(row.getAttribute('data-banner'));
    document.getElementById('bannerModalTitle').textContent = 'Edit Banner';
    document.getElementById('bannerForm').action = updateBase + b.id;
    document.getElementById('bannerMethod').value = 'PUT';
    document.getElementById('b-title').value    = b.title_en || b.title;
    fillLocalized('b-title', b, 'title');
    document.getElementById('b-role').value     = b.target_role;
    document.getElementById('b-deeplink').value = b.deeplink;
    document.getElementById('b-from').value     = b.valid_from;
    document.getElementById('b-until').value    = b.valid_until;
    document.getElementById('b-order').value    = b.sort_order;
    document.getElementById('b-active').checked = !!b.active;
    document.getElementById('b-image').required = false;
    document.getElementById('imgRequired').style.display = 'none';
    // Show current image
    const preview = document.getElementById('imgPreview');
    document.getElementById('currentImg').src = '/storage/' + (b.image || '');
    preview.style.display = b.image ? 'block' : 'none';
    $('#bannerModal').modal('show');
}
</script>
@endpush
