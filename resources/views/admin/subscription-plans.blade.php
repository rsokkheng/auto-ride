@extends('admin.layout')
@section('title', 'Subscription Plans')
@section('page-title', 'Subscription Plans')

@section('content')

{{-- Stats row --}}
<div class="row mb-3">
    <div class="col-md-4">
        <div class="info-box bg-gradient-primary">
            <span class="info-box-icon"><i class="fas fa-users"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Active Subscribers</span>
                <span class="info-box-number">{{ number_format($stats['total_active']) }}</span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-box bg-gradient-success">
            <span class="info-box-icon"><i class="fas fa-dollar-sign"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Revenue This Month</span>
                <span class="info-box-number">{{ number_format($stats['revenue_month']) }} ៛</span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="info-box bg-gradient-warning">
            <span class="info-box-icon"><i class="fas fa-times-circle"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Cancellations This Month</span>
                <span class="info-box-number">{{ number_format($stats['cancelled_month']) }}</span>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-layer-group text-primary mr-2"></i> Plans</h3>
        <button class="btn btn-sm btn-primary" onclick="openCreate()"><i class="fas fa-plus mr-1"></i> New Plan</button>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Plan</th>
                    <th>Price</th>
                    <th>Cycle</th>
                    <th>Ride Credit</th>
                    <th>Discount</th>
                    <th>Perks</th>
                    <th>Subscribers</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                <tr>
                    <td>{{ $plan->sort_order }}</td>
                    <td>
                        <span class="badge" style="background:{{ $plan->badge_color }};color:#fff;font-size:.8rem;padding:.4em .7em">
                            <i class="{{ $plan->icon }} mr-1"></i>{{ $plan->name }}
                        </span>
                        <br><small class="text-muted">{{ $plan->description }}</small>
                    </td>
                    <td><strong>{{ number_format($plan->price_khr) }} ៛</strong></td>
                    <td>{{ ucfirst($plan->billing_cycle) }}</td>
                    <td>{{ $plan->ride_credit_khr ? number_format($plan->ride_credit_khr) . ' ៛' : '—' }}</td>
                    <td>
                        @if($plan->ride_discount_pct) <span class="badge badge-info">{{ $plan->ride_discount_pct }}% ride</span> @endif
                        @if($plan->delivery_discount_pct) <span class="badge badge-secondary">{{ $plan->delivery_discount_pct }}% delivery</span> @endif
                        @if(!$plan->ride_discount_pct && !$plan->delivery_discount_pct) — @endif
                    </td>
                    <td>
                        @if($plan->surge_waived) <span class="badge badge-warning">No Surge</span> @endif
                        @if($plan->priority_matching) <span class="badge badge-primary">Priority</span> @endif
                        @if($plan->bonus_points_pct) <span class="badge badge-success">+{{ $plan->bonus_points_pct }}% pts</span> @endif
                        @if($plan->free_cancellations === 0) <span class="badge badge-light border">∞ Cancel</span>
                        @elseif($plan->free_cancellations > 0) <span class="badge badge-light border">{{ $plan->free_cancellations }} Cancel</span> @endif
                    </td>
                    <td class="text-center">
                        <a href="{{ route('admin.subscription-plans.subscribers', $plan) }}" class="badge badge-secondary">
                            {{ rescue(fn() => \App\Models\UserSubscription::where('subscription_plan_id',$plan->id)->where('status','active')->count(), 0, false) }}
                        </a>
                    </td>
                    <td>
                        <span class="badge badge-{{ $plan->active ? 'success' : 'secondary' }}">
                            {{ $plan->active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-xs btn-outline-primary" onclick="openEdit({{ $plan->id }})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form action="{{ route('admin.subscription-plans.destroy', $plan) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('Deactivate this plan?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-outline-danger"><i class="fas fa-ban"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No subscription plans yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($plans->hasPages())
    <div class="card-footer">{{ $plans->links() }}</div>
    @endif
</div>

{{-- Create Modal --}}
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.subscription-plans.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus mr-2"></i>New Subscription Plan</h5>
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
