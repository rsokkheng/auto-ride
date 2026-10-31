@extends('admin.layout')
@section('title', 'Promo Events')
@section('page-title', 'Promo Events')

@section('content')

<x-admin.page-toolbar subtitle="Announcements pushed to app users. Saving an active event sends the push straight away, in each user's language.">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Create Event</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Total Events" :value="number_format($stats['total'])" icon="fa-bullhorn" tone="primary"
                       :href="route('admin.promo-events')" :active="!request('status')" />
    <x-admin.stat-card label="Active" :value="number_format($stats['active'])" icon="fa-toggle-on" tone="success"
                       :href="route('admin.promo-events', ['status' => 'active'])" :active="request('status') === 'active'" />
    <x-admin.stat-card label="Pushed" :value="number_format($stats['sent'])" icon="fa-paper-plane" tone="info" sub="sent at least once"
                       :href="route('admin.promo-events', ['status' => 'sent'])" :active="request('status') === 'sent'" />
    <x-admin.stat-card label="Pushed This Month" :value="number_format($stats['this_month'])" icon="fa-calendar-check" tone="violet"
                       :sub="now()->format('F Y')" />
</x-admin.stat-grid>

@php $audiences = ['all' => 'Everyone', 'passenger' => 'Passengers', 'driver' => 'Drivers']; @endphp
<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.promo-events')" placeholder="Search title or message" :count="$events->total()" noun="event">
            <x-admin.filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive', 'sent' => 'Pushed', 'unsent' => 'Not pushed yet']" all="All statuses" />
            <x-admin.filter-select name="audience" :options="$audiences" all="All audiences" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Audience</th>
                    <th>Status</th>
                    <th>Pushed</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($events as $e)
                @php $aTone = ['all' => 'primary', 'passenger' => 'info', 'driver' => 'warning'][$e->target_role] ?? 'secondary'; @endphp
                <tr data-event="{{ json_encode([
                        'id'          => $e->id,
                        ...$e->only(['title_en', 'title_kh', 'title_zh', 'body_en', 'body_kh', 'body_zh']),
                        'title'       => $e->title,
                        'body'        => $e->body,
                        'target_role' => $e->target_role,
                        'active'      => $e->active,
                        'image_url'   => $e->image_url,
                    ]) }}">
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            @if($e->image)
                                <img src="{{ $e->image_url }}" alt="" loading="lazy" class="thumb" style="width:64px;"
                                     onerror="this.outerHTML='<span class=&quot;thumb-placeholder&quot; style=&quot;width:64px&quot;><i class=&quot;fas fa-bullhorn&quot;></i></span>'">
                            @else
                                <span class="thumb-placeholder" style="width:64px;"><i class="fas fa-bullhorn"></i></span>
                            @endif
                            <div class="tw:min-w-0">
                                <div class="text-truncate" style="max-width:320px;font-weight:600;color:var(--ink-900);" title="{{ $e->title }}">{{ $e->title }}</div>
                                <div class="text-muted text-truncate tw:text-xs" style="max-width:320px;" title="{{ $e->body }}">{{ $e->body }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-{{ $aTone }}">{{ $audiences[$e->target_role] ?? ucfirst($e->target_role) }}</span></td>
                    <td><span class="status-pill tone-{{ $e->active ? 'success' : 'muted' }}">{{ $e->active ? 'Active' : 'Inactive' }}</span></td>
                    <td>
                        @if($e->sent_at)
                            <div title="{{ $e->sent_at->format('d M Y H:i') }}"><i class="fas fa-paper-plane mr-1" style="color:var(--success);"></i>{{ $e->sent_at->diffForHumans() }}</div>
                        @else
                            <span class="text-muted">Not pushed yet</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <x-admin.row-actions edit="openEdit(this.closest('tr'))"
                                             :delete="route('admin.promo-events.destroy', $e)" :confirm="'Delete event “' . $e->title . '”?'" />
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="5" icon="fa-bullhorn" title="No promo events yet"
                                   hint="Create one to announce an offer to your users." :clear="route('admin.promo-events')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$events" />
</div>

<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="eventModalTitle">Create Event</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="eventForm" method="POST" enctype="multipart/form-data"
                  action="{{ route('admin.promo-events.store') }}">
                @csrf
                <input type="hidden" name="_method" id="eventMethod" value="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Title <small class="text-muted">(English)</small> <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="e-title" class="form-control" required maxlength="100"
                               placeholder="e.g. Friday Night Party Discount">
                    </div>
                    @include('admin.partials.localized-inputs', ['field' => 'title', 'label' => 'Title', 'idPrefix' => 'e-title', 'maxlength' => 100])
                    <div class="form-group">
                        <label>Body <small class="text-muted">(English)</small> <span class="text-danger">*</span></label>
                        <textarea name="body" id="e-body" class="form-control" rows="3" required maxlength="1000"
                                  placeholder="e.g. 20% off all rides this Friday 6pm–midnight!"></textarea>
                    </div>
                    @include('admin.partials.localized-inputs', ['field' => 'body', 'label' => 'Body', 'idPrefix' => 'e-body', 'textarea' => true, 'rows' => 3, 'maxlength' => 1000])
                    <small class="text-muted d-block mb-3">Each user gets the push in their app language; empty languages fall back to English.</small>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Target Audience <span class="text-danger">*</span></label>
                            <select name="target_role" id="e-role" class="form-control" required>
                                <option value="all">All Users</option>
                                <option value="passenger">Passengers only</option>
                                <option value="driver">Drivers only</option>
                            </select>
                        </div>
                        <div class="form-group col-md-6 d-flex align-items-end">
                            <div class="custom-control custom-switch">
                                <input type="hidden" name="active" value="0">
                                <input type="checkbox" class="custom-control-input" name="active" id="e-active" value="1" checked>
                                <label class="custom-control-label" for="e-active">Active (push immediately on save)</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group" id="e-resend-wrap" style="display:none;">
                        <div class="custom-control custom-switch">
                            <input type="hidden" name="resend" value="0">
                            <input type="checkbox" class="custom-control-input" name="resend" id="e-resend" value="1">
                            <label class="custom-control-label" for="e-resend">Re-send push notification with these changes</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Image <small class="text-muted">(optional — JPG/PNG/WEBP, max 4 MB)</small></label>
                        <input type="file" name="image" id="e-image" class="form-control-file" accept="image/*">
                        <div id="e-imgPreview" class="mt-2" style="display:none;">
                            <img id="e-currentImg" src="" style="max-height:70px;border-radius:4px;">
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
const eStoreUrl = '{{ route("admin.promo-events.store") }}';
const eUpdateBase = '/admin/promo-events/';

function openCreate() {
    document.getElementById('eventModalTitle').textContent = 'Create Event';
    document.getElementById('eventForm').action = eStoreUrl;
    document.getElementById('eventMethod').value = 'POST';
    document.getElementById('eventForm').reset();
    document.getElementById('e-resend-wrap').style.display = 'none';
    document.getElementById('e-imgPreview').style.display = 'none';
    $('#eventModal').modal('show');
}

function openEdit(row) {
    const ev = JSON.parse(row.getAttribute('data-event'));
    document.getElementById('eventModalTitle').textContent = 'Edit Event';
    document.getElementById('eventForm').action = eUpdateBase + ev.id;
    document.getElementById('eventMethod').value = 'PUT';
    document.getElementById('e-title').value  = ev.title_en || ev.title;
    document.getElementById('e-body').value   = ev.body_en || ev.body;
    fillLocalized('e-title', ev, 'title');
    fillLocalized('e-body', ev, 'body');
    document.getElementById('e-role').value   = ev.target_role;
    document.getElementById('e-active').checked = !!ev.active;
    document.getElementById('e-resend').checked = false;
    document.getElementById('e-resend-wrap').style.display = 'block';
    const preview = document.getElementById('e-imgPreview');
    document.getElementById('e-currentImg').src = ev.image_url || '';
    preview.style.display = ev.image_url ? 'block' : 'none';
    $('#eventModal').modal('show');
}
</script>
@endpush
