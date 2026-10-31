@extends('admin.layout')
@section('title', 'Subscription Plans')
@section('page-title', 'Subscription Plans')

@section('content')

<x-admin.page-toolbar subtitle="Paid memberships passengers can buy for ride credit, discounts and perks.">
    <button class="btn btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> Create Plan</button>
</x-admin.page-toolbar>

<x-admin.stat-grid :cols="4">
    <x-admin.stat-card label="Plans" :value="number_format($stats['plans'])" icon="fa-layer-group" tone="primary"
                       :sub="$stats['plans_active'] . ' active'" :href="route('admin.subscription-plans')" :active="!request('status')" />
    <x-admin.stat-card label="Active Subscribers" :value="number_format($stats['total_active'])" icon="fa-users" tone="success" />
    <x-admin.stat-card label="Revenue This Month" :value="number_format($stats['revenue_month']) . ' ៛'" icon="fa-sack-dollar" tone="violet" :sub="now()->format('F Y')" />
    <x-admin.stat-card label="Cancellations" :value="number_format($stats['cancelled_month'])" icon="fa-user-xmark" tone="danger" sub="this month" />
</x-admin.stat-grid>

<div class="card">
    <div class="card-header">
        <x-admin.filter-bar :action="route('admin.subscription-plans')" placeholder="Search plan name" :count="$plans->total()" noun="plan">
            <x-admin.filter-select name="status" :options="['active' => 'Active', 'inactive' => 'Inactive']" all="All statuses" />
            <x-admin.filter-select name="cycle" :options="['weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly']" all="All billing cycles" />
        </x-admin.filter-bar>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap">
            <thead>
                <tr>
                    <th>Plan</th>
                    <th class="text-right">Price</th>
                    <th>Benefits</th>
                    <th class="text-center">Subscribers</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse($plans as $plan)
                <tr>
                    <td>
                        <div class="tw:flex tw:items-center tw:gap-3">
                            <span class="stat-icon" style="width:38px;height:38px;font-size:.9rem;background:{{ $plan->badge_color }}1f;color:{{ $plan->badge_color }};">
                                <i class="{{ $plan->icon ?: 'fas fa-crown' }}"></i>
                            </span>
                            <div class="tw:min-w-0">
                                <div style="font-weight:600;color:var(--ink-900);">{{ $plan->name }}</div>
                                <div class="text-muted text-truncate tw:text-xs" style="max-width:240px;" title="{{ $plan->description }}">{{ $plan->description ?: $plan->slug }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="text-right">
                        <div style="font-weight:600;color:var(--ink-900);">{{ number_format($plan->price_khr) }} ៛</div>
                        <div class="text-muted tw:text-xs">per {{ ['weekly' => 'week', 'monthly' => 'month', 'yearly' => 'year'][$plan->billing_cycle] ?? $plan->billing_cycle }}</div>
                    </td>
                    <td style="white-space:normal;min-width:240px;">
                        <div class="tw:flex tw:flex-wrap tw:gap-1">
                            @if($plan->ride_credit_khr)<span class="badge badge-primary">{{ number_format($plan->ride_credit_khr) }} ៛ credit</span>@endif
                            @if($plan->ride_discount_pct)<span class="badge badge-info">{{ $plan->ride_discount_pct }}% off rides</span>@endif
                            @if($plan->delivery_discount_pct)<span class="badge badge-info">{{ $plan->delivery_discount_pct }}% off delivery</span>@endif
                            @if($plan->surge_waived)<span class="badge badge-warning">No surge</span>@endif
                            @if($plan->priority_matching)<span class="badge badge-success">Priority matching</span>@endif
                            @if($plan->bonus_points_pct)<span class="badge badge-success">+{{ $plan->bonus_points_pct }}% points</span>@endif
                            @if($plan->free_cancellations === 0)<span class="badge badge-light">Unlimited cancels</span>
                            @elseif($plan->free_cancellations > 0)<span class="badge badge-light">{{ $plan->free_cancellations }} free cancels</span>@endif
                        </div>
                    </td>
                    <td class="text-center">
                        <a href="{{ route('admin.subscription-plans.subscribers', $plan) }}" class="badge badge-secondary" style="font-size:.78rem;">
                            <i class="fas fa-users mr-1"></i>{{ number_format($plan->active_subscribers ?? 0) }}
                        </a>
                    </td>
                    <td><span class="status-pill tone-{{ $plan->active ? 'success' : 'muted' }}">{{ $plan->active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="text-center">
                        <x-admin.row-actions :edit="'openEdit(' . $plan->id . ')'">
                            @if($plan->active)
                                <form action="{{ route('admin.subscription-plans.destroy', $plan) }}" method="POST" class="m-0"
                                      onsubmit="return confirm({{ Illuminate\Support\Js::from('Deactivate plan ' . $plan->name . '? New customers will no longer see it.') }})">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Deactivate" data-toggle="tooltip"><i class="fas fa-ban"></i></button>
                                </form>
                            @endif
                        </x-admin.row-actions>
                    </td>
                </tr>
            @empty
                <x-admin.empty-row colspan="6" icon="fa-layer-group" title="No subscription plans yet"
                                   hint="Create a plan customers can subscribe to." :clear="route('admin.subscription-plans')" />
            @endforelse
            </tbody>
        </table>
    </div>
    <x-admin.pagination :paginator="$plans" />
</div>

{{-- Create Modal --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.subscription-plans.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus mr-2"></i>Create Subscription Plan</h5>
                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    @include('admin._partials.subscription-plan-fields')
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Plan</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modals --}}
@foreach($plans as $plan)
<div class="modal fade" id="editModal{{ $plan->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.subscription-plans.update', $plan) }}" method="POST">
            @csrf @method('PUT')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit — {{ $plan->name }}</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    @include('admin._partials.subscription-plan-fields', ['plan' => $plan])
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endforeach

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show position-fixed" style="bottom:20px;right:20px;z-index:9999">
    {{ session('success') }}<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show position-fixed" style="bottom:20px;right:20px;z-index:9999">
    {{ session('error') }}<button type="button" class="close" data-dismiss="alert">&times;</button>
</div>
@endif
@endsection

@push('scripts')
<script>
function openCreate() { $('#createModal').modal('show'); }
function openEdit(id)  { $('#editModal' + id).modal('show'); }
</script>
@endpush
