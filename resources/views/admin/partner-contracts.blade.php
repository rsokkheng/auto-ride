@extends('admin.layout')
@section('title', 'Partner Contracts')
@section('page-title', 'Partner Contracts')

@section('content')

<x-admin.page-toolbar subtitle="Custom delivery prices agreed with partner businesses. Partners without a contract pay the default prices.">
    <button class="btn btn-primary" data-toggle="modal" data-target="#addModal"><i class="fas fa-plus mr-1"></i> Create Contract</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Contracts" :value="number_format($stats['contracts'])" icon="fa-file-contract" tone="primary"
                       :href="route('admin.partner-contracts')" :active="!request('status')" />
    <x-admin.stat-card label="Active Contracts" :value="number_format($stats['active'])" icon="fa-file-circle-check" tone="success"
                       :href="route('admin.partner-contracts', ['status' => 'active'])" :active="request('status') === 'active'" />
    <x-admin.stat-card label="Partners" :value="number_format($stats['partners'])" icon="fa-handshake" tone="info" />
    <x-admin.stat-card label="On Default Prices" :value="number_format($stats['defaults'])" icon="fa-tags" tone="warning" sub="partners without a contract" />
</x-admin.stat-grid>

<div class="card">
            <div class="card-header">
                <x-admin.filter-bar :action="route('admin.partner-contracts')" placeholder="Search partner name, phone or email" :count="$contracts->total()" noun="contract">
                    <x-admin.filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" all="All statuses" />
                </x-admin.filter-bar>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover text-nowrap">
                    <thead>
                        <tr>
                            <th>Partner</th>
                            <th class="text-right">Normal</th>
                            <th class="text-right">Express</th>
                            <th class="text-right">Large / XL</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($contracts as $c)
                        <tr data-id="{{ $c->id }}"
                            data-partner="{{ $c->partner->name ?? '' }}"
                            data-normal="{{ $c->normal_fee }}"
                            data-express="{{ $c->express_fee }}"
                            data-large="{{ $c->surcharge_large }}"
                            data-xl="{{ $c->surcharge_extra_large }}"
                            data-active="{{ $c->is_active ? '1' : '0' }}"
                            data-notes="{{ $c->notes }}">
                            <td>
                                <div style="font-weight:600;color:var(--ink-900);">{{ $c->partner->name ?? '—' }}</div>
                                <div class="text-muted tw:text-xs">{{ $c->partner->phone ?? '' }} · updated {{ $c->updated_at->format('d M Y') }}</div>
                            </td>
                            <td class="text-right" style="font-weight:600;color:var(--ink-900);">{{ number_format($c->normal_fee) }} ៛</td>
                            <td class="text-right" style="font-weight:600;color:var(--danger);">{{ number_format($c->express_fee) }} ៛</td>
                            <td class="text-right text-muted">+{{ number_format($c->surcharge_large) }} / +{{ number_format($c->surcharge_extra_large) }} ៛</td>
                            <td><span class="status-pill tone-{{ $c->is_active ? 'success' : 'muted' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-center">
                                <x-admin.row-actions edit="openEdit(this.closest('tr'))"
                                                     :delete="route('admin.partner-contracts.destroy', $c)"
                                                     :confirm="'Delete the contract for ' . ($c->partner->name ?? 'this partner') . '?'" />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty-row colspan="6" icon="fa-file-contract" title="No contracts yet"
                                           hint="Create one to give a partner custom delivery prices." :clear="route('admin.partner-contracts')" />
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-admin.pagination :paginator="$contracts" />
        </div>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0"><i class="fas fa-tags mr-2" style="color:var(--primary);"></i>Default Prices</h3>
                <a href="{{ route('admin.ride-pricing') }}" class="tw:text-xs">Edit defaults</a>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm">
                    <thead><tr><th>Package</th><th class="text-right">Normal</th><th class="text-right">Express</th></tr></thead>
                    <tbody>
                        @foreach(['Small / Medium' => 0, 'Large' => $defaults['surcharge_large'], 'Extra Large' => $defaults['surcharge_extra_large']] as $size => $extra)
                            <tr>
                                <td>{{ $size }}</td>
                                <td class="text-right" style="font-weight:600;color:var(--ink-900);">{{ number_format($defaults['normal_fee'] + $extra) }} ៛</td>
                                <td class="text-right" style="font-weight:600;color:var(--danger);">{{ number_format($defaults['express_fee'] + $extra) }} ៛</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer text-muted tw:text-xs">Used for partners without an active contract.</div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-lightbulb mr-2" style="color:var(--warning);"></i>How it works</h3></div>
            <div class="card-body tw:text-sm">
                <ul class="pl-3 mb-0" style="line-height:1.8;">
                    <li>Each partner has <strong>one active contract</strong>; creating a new one deactivates the old one.</li>
                    <li>Fee = <code>Normal or Express</code> + size surcharge. Small and medium packages have no surcharge.</li>
                    <li>No contract → the default prices above apply.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

{{-- Add Modal --}}
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-contract mr-2"></i>Create Partner Contract</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="{{ route('admin.partner-contracts.store') }}">
                @csrf
                <div class="modal-body">
                    @include('admin.partials.contract-form', ['contract' => null, 'partners' => $partners, 'defaults' => $defaults, 'formId' => 'add'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Save Contract</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Modal --}}
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Edit Contract — <span id="edit-partner-name"></span></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" id="editForm">
                @csrf @method('PUT')
                <div class="modal-body">
                    @include('admin.partials.contract-form', ['contract' => null, 'defaults' => $defaults, 'formId' => 'edit'])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-save mr-1"></i>Update Contract</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openEdit(btn) {
    document.getElementById('edit-partner-name').textContent = btn.dataset.partner;
    document.getElementById('editForm').action = '/admin/partner-contracts/' + btn.dataset.id;

    var modal = document.getElementById('editModal');
    modal.querySelector('[name=normal_fee]').value            = btn.dataset.normal;
    modal.querySelector('[name=express_fee]').value           = btn.dataset.express;
    modal.querySelector('[name=surcharge_large]').value       = btn.dataset.large;
    modal.querySelector('[name=surcharge_extra_large]').value = btn.dataset.xl;
    modal.querySelector('[name=is_active]').checked           = btn.dataset.active === '1';
    modal.querySelector('[name=notes]').value                 = btn.dataset.notes || '';

    modal.querySelector('[name=normal_fee]').dispatchEvent(new Event('input', {bubbles: true}));
    $('#editModal').modal('show');
}
</script>
@endpush
@endsection
