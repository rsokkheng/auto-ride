@extends('admin.layout')
@section('title', 'Companies')
@section('page-title', 'Companies')

@section('content')

<x-admin.page-toolbar subtitle="Fleet companies whose drivers work under a company commission and daily rental rate.">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Create Company</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Companies" :value="number_format($stats['total'])" icon="fa-building" tone="primary"
                       :href="route('admin.companies')" :active="!request('status')" />
    <x-admin.stat-card label="Active" :value="number_format($stats['active'])" icon="fa-circle-check" tone="success"
                       :href="route('admin.companies', ['status' => 'active'])" :active="request('status') === 'active'" />
    <x-admin.stat-card label="Fleet Drivers" :value="number_format($stats['drivers'])" icon="fa-id-card" tone="info" sub="drivers linked to a company" />
    <x-admin.stat-card label="Avg. Company Cut" :value="number_format($stats['avg_cut'], 1) . '%'" icon="fa-percent" tone="violet" sub="active companies" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.companies')" placeholder="Search name, phone or email" :count="$companies->total()" noun="company">
            <x-admin.filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" all="All statuses" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Company</th>
                    <th>Contact</th>
                    <th class="text-right">Platform Fee</th>
                    <th class="text-right">Company Cut</th>
                    <th class="text-right">Daily Rental</th>
                    <th class="text-center">Drivers</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($companies as $c)
                <tr data-company="{{ json_encode([
                        'id'                       => $c->id,
                        'name'                     => $c->name,
                        'phone'                    => $c->phone ?? '',
                        'email'                    => $c->email ?? '',
                        'address'                  => $c->address ?? '',
                        'platform_commission_rate' => $c->platform_commission_rate ?? '',
                        'company_commission_rate'  => $c->company_commission_rate,
                        'rental_daily_rate'        => $c->rental_daily_rate,
                        'active'                   => $c->active,
                    ]) }}">
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            <span class="stat-icon tone-primary" style="width:36px;height:36px;font-size:.85rem;"><i class="fas fa-building"></i></span>
                            <div class="tw:min-w-0">
                                <a href="{{ route('admin.companies.show', $c) }}" class="d-block text-truncate" style="max-width:220px;font-weight:600;">{{ $c->name }}</a>
                                <div class="text-muted text-truncate tw:text-xs" style="max-width:220px;" title="{{ $c->address }}">{{ $c->address ?: '—' }}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div>{{ $c->phone ?? '—' }}</div>
                        <div class="text-muted tw:text-xs">{{ $c->email ?? '' }}</div>
                    </td>
                    <td class="text-right">
                        @if($c->platform_commission_rate !== null)
                            {{ rtrim(rtrim(number_format($c->platform_commission_rate, 2), '0'), '.') }}%
                        @else
                            <span class="text-muted">Default</span>
                        @endif
                    </td>
                    <td class="text-right">{{ rtrim(rtrim(number_format($c->company_commission_rate, 2), '0'), '.') }}%</td>
                    <td class="text-right">{{ $c->rental_daily_rate ? number_format($c->rental_daily_rate) . ' ៛' : '—' }}</td>
                    <td class="text-center"><span class="badge badge-secondary"><i class="fas fa-id-card mr-1"></i>{{ $c->drivers_count }}</span></td>
                    <td><span class="status-pill tone-{{ $c->active ? 'success' : 'muted' }}">{{ $c->active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-center">
                        <x-admin.row-actions edit="openEdit(this.closest('tr'))"
                                             :delete="route('admin.companies.destroy', $c)" :confirm="'Delete company ' . $c->name . '?'">
                            <a href="{{ route('admin.companies.show', $c) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="View" data-toggle="tooltip"><i class="fas fa-eye"></i></a>
                        </x-admin.row-actions>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="8" icon="fa-building" title="No companies yet" :clear="route('admin.companies')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$companies" />
</div>

{{-- Modal --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create Company</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="companyForm" method="POST" action="{{ route('admin.companies.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label>Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="f-name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-4 d-flex align-items-end">
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" class="custom-control-input" name="active" id="f-active" value="1" checked>
                                <label class="custom-control-label" for="f-active">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Phone</label>
                            <input type="text" name="phone" id="f-phone" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Email</label>
                            <input type="email" name="email" id="f-email" class="form-control">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Address</label>
                            <input type="text" name="address" id="f-address" class="form-control">
                        </div>
                    </div>
                    <hr>
                    <p class="text-muted small mb-2"><i class="fas fa-percentage mr-1"></i> Commission Settings</p>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Platform Fee % <small class="text-muted">(blank = default)</small></label>
                            <input type="number" name="platform_commission_rate" id="f-platform-rate" class="form-control" min="0" max="100" step="0.5" placeholder="{{ config('commission.platform_rate.owner') }}">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Company Cut % <small class="text-muted">(rental/employee)</small></label>
                            <input type="number" name="company_commission_rate" id="f-company-rate" class="form-control" min="0" max="100" step="0.5" value="10">
                        </div>
                        <div class="form-group col-md-4">
                            <label>Daily Rental Fee (KHR ៛)</label>
                            <input type="number" name="rental_daily_rate" id="f-rental-rate" class="form-control" min="0" step="1000" value="0">
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
const storeUrl = '{{ route('admin.companies.store') }}';
const updateBase = '/admin/companies/';

function openCreate() {
    document.getElementById('modalTitle').textContent = 'Create Company';
    document.getElementById('companyForm').action = storeUrl;
    document.getElementById('formMethod').value = 'POST';
    document.getElementById('companyForm').reset();
    document.getElementById('f-active').checked = true;
    document.getElementById('f-company-rate').value = '10';
    document.getElementById('f-rental-rate').value = '0';
    $('#formModal').modal('show');
}

function openEdit(btn) {
    const c = JSON.parse(btn.getAttribute('data-company'));
    document.getElementById('modalTitle').textContent = 'Edit Company #' + c.id;
    document.getElementById('companyForm').action = updateBase + c.id;
    document.getElementById('formMethod').value = 'PUT';
    document.getElementById('f-name').value = c.name;
    document.getElementById('f-phone').value = c.phone;
    document.getElementById('f-email').value = c.email;
    document.getElementById('f-address').value = c.address;
    document.getElementById('f-platform-rate').value = c.platform_commission_rate;
    document.getElementById('f-company-rate').value = c.company_commission_rate;
    document.getElementById('f-rental-rate').value = c.rental_daily_rate;
    document.getElementById('f-active').checked = c.active;
    $('#formModal').modal('show');
}
</script>
@endpush
