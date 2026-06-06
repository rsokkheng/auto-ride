@extends('admin.layout')
@section('title', 'Deliveries')
@section('page-title', 'Deliveries')

@section('content')

{{-- ── Tabs ─────────────────────────────────────────────────────────────── --}}
<ul class="nav nav-tabs mb-3" style="border-bottom:2px solid #e2e8f0;">
    @foreach([
        ['key'=>'all',      'label'=>'All Orders',  'icon'=>'fa-layer-group',  'color'=>'#64748b'],
        ['key'=>'delivery', 'label'=>'Delivery',     'icon'=>'fa-box',          'color'=>'#3b82f6'],
        ['key'=>'moving',   'label'=>'Moving',       'icon'=>'fa-truck-moving', 'color'=>'#f59e0b'],
    ] as $tab)
    <li class="nav-item">
        <a href="{{ route('admin.deliveries', ['type' => $tab['key']]) }}"
           class="nav-link d-flex align-items-center {{ $activeType === $tab['key'] ? 'active font-weight-bold' : '' }}"
           style="{{ $activeType === $tab['key'] ? 'color:'.$tab['color'].';border-bottom:3px solid '.$tab['color'].';' : 'color:#64748b;' }}">
            <i class="fas {{ $tab['icon'] }} mr-1" style="font-size:.8rem;"></i>
            {{ $tab['label'] }}
            <span class="badge badge-secondary ml-1"
                  style="{{ $activeType === $tab['key'] ? 'background:'.$tab['color'].';' : '' }}">
                {{ $counts[$tab['key']] }}
            </span>
        </a>
    </li>
    @endforeach
</ul>

{{-- ── Table ────────────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            @if($activeType === 'moving')
                <i class="fas fa-truck-moving text-warning mr-2"></i>Moving Orders
            @elseif($activeType === 'delivery')
                <i class="fas fa-box text-primary mr-2"></i>Delivery Orders
            @else
                <i class="fas fa-layer-group text-muted mr-2"></i>All Orders
            @endif
        </h3>
        <button class="btn btn-sm btn-primary"
                onclick="openCreate('{{ $activeType === 'all' ? 'delivery' : $activeType }}')">
            <i class="fas fa-plus mr-1"></i>
            Add {{ $activeType === 'moving' ? 'Moving Order' : 'Delivery' }}
        </button>
    </div>

    <div class="card-body table-responsive p-0">
        <table class="table table-hover text-nowrap mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type</th>
                    <th>Sender</th>
                    <th>Recipient</th>
                    <th>Phone</th>
                    <th>Details</th>
                    <th>Driver</th>
                    <th>Pickup</th>
                    <th>Dropoff</th>
                    <th>Status</th>
                    <th>Fee</th>
                    <th>Paid By</th>
                    <th>Method</th>
                    <th>Pay Status</th>
                    <th>Scheduled</th>
                    <th>Assigned</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($deliveries as $d)
                @php
                    $isMoving = ($d->service_type ?? 'delivery') === 'moving';
                    $sc = ['requested'=>'secondary','pending'=>'warning','accepted'=>'info','in_progress'=>'primary','completed'=>'success','cancelled'=>'danger'];
                @endphp
                <tr>
                    <td>{{ $d->id }}</td>
                    <td>
                        @if($isMoving)
                            <span class="badge" style="background:#fef3c7;color:#d97706;border:1px solid #fde68a;">
                                <i class="fas fa-truck-moving mr-1"></i>Moving
                            </span>
                        @else
                            <span class="badge badge-light" style="border:1px solid #e2e8f0;">
                                <i class="fas fa-box mr-1 text-primary"></i>Delivery
                            </span>
                        @endif
                    </td>
                    <td>
                        <div>{{ $d->sender_name ?? $d->sender?->name ?? '—' }}</div>
                        <small class="text-muted">{{ $d->sender?->email }}</small>
                    </td>
                    <td>{{ $d->recipient_name ?? '—' }}</td>
                    <td>{{ $d->recipient_phone ?? '—' }}</td>
                    <td>
                        @if($isMoving)
                            @php $maxFloor = max($d->floor_pickup ?? 0, $d->floor_dropoff ?? 0); @endphp
                            <small>
                                <i class="fas fa-building text-warning mr-1"></i>
                                F{{ $d->floor_pickup ?? 0 }} → F{{ $d->floor_dropoff ?? 0 }}
                                @if($d->has_elevator)
                                    <span class="badge badge-success ml-1" style="font-size:.65rem;">Lift</span>
                                @else
                                    <span class="badge badge-secondary ml-1" style="font-size:.65rem;">Stairs</span>
                                @endif
                            </small><br>
                            <small>
                                <i class="fas fa-users text-muted mr-1"></i>{{ $d->requires_helpers ?? 0 }} helper(s)
                                @if($d->heavy_items)
                                    &nbsp;<span class="badge badge-danger" style="font-size:.65rem;">Heavy</span>
                                @endif
                            </small>
                        @else
                            @php $pc = ['small'=>'success','medium'=>'warning','large'=>'danger']; @endphp
                            <span class="badge badge-{{ $pc[$d->package_size] ?? 'secondary' }}">
                                {{ ucfirst($d->package_size ?? '—') }}
                            </span>
                        @endif
                    </td>
                    <td>
                        @if($d->driver)
                            <div><i class="fas fa-user-check text-success mr-1"></i>{{ $d->driver->name }}</div>
                            @if($d->driver->phone)
                                <small class="text-muted">{{ $d->driver->phone }}</small>
                            @endif
                        @else
                            <span class="text-muted mr-1">Unassigned</span>
                            <button class="btn btn-xs btn-success"
                                data-id="{{ $d->id }}"
                                data-driver=""
                                data-label="#{{ $d->id }} — {{ Str::limit($d->pickup_address, 22) }}"
                                onclick="openAssign(this)">
                                <i class="fas fa-user-plus mr-1"></i>Assign
                            </button>
                        @endif
                    </td>
                    <td>{{ Str::limit($d->pickup_address, 20) }}</td>
                    <td>{{ Str::limit($d->dropoff_address, 20) }}</td>
                    <td>
                        <span class="badge badge-{{ $sc[$d->status] ?? 'secondary' }}">
                            {{ ucfirst(str_replace('_', ' ', $d->status)) }}
                        </span>
                    </td>
                    <td>
                        @if($d->fee)
                            {{ number_format($d->fee) }} ៛
                            @if($isMoving && ($d->helper_fee || $d->floor_fee))
                                <br>
                                @if($d->helper_fee)
                                    <small class="text-muted">Helpers: {{ number_format($d->helper_fee) }} ៛</small><br>
                                @endif
                                @if($d->floor_fee)
                                    <small class="text-muted">Floor: {{ number_format($d->floor_fee) }} ៛</small>
                                @endif
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($d->payment_by === 'recipient')
                            <span class="badge badge-warning">Recipient (COD)</span>
                        @else
                            <span class="badge badge-info">Sender</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $mc=['cash'=>'secondary','wallet'=>'primary','aba'=>'success','wing'=>'info','other_online'=>'warning'];
                            $ml=['cash'=>'Cash','wallet'=>'Wallet','aba'=>'ABA','wing'=>'Wing','other_online'=>'Online'];
                        @endphp
                        <span class="badge badge-{{ $mc[$d->payment_method ?? 'cash'] ?? 'secondary' }}">
                            {{ $ml[$d->payment_method ?? 'cash'] ?? ucfirst($d->payment_method) }}
                        </span>
                    </td>
                    <td>
                        @php $ps=['unpaid'=>'secondary','pending'=>'warning','paid'=>'success','refunded'=>'danger']; @endphp
                        <span class="badge badge-{{ $ps[$d->payment_status ?? 'unpaid'] ?? 'secondary' }}">
                            {{ ucfirst($d->payment_status ?? 'unpaid') }}
                        </span>
                    </td>
                    <td>{{ $d->scheduled_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        @if($d->assigned_at)
                            <span class="text-success">
                                <i class="fas fa-check-circle mr-1"></i>{{ $d->assigned_at->format('Y-m-d H:i') }}
                            </span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $d->created_at->format('Y-m-d') }}</td>
                    <td>
                        <button class="btn btn-xs btn-info mr-1"
                            data-delivery="{{ e(json_encode([
                                'id'                => $d->id,
                                'service_type'      => $d->service_type ?? 'delivery',
                                'sender_id'         => $d->sender_id,
                                'sender_name'       => $d->sender_name ?? '',
                                'recipient_name'    => $d->recipient_name ?? '',
                                'recipient_phone'   => $d->recipient_phone ?? '',
                                'package_size'      => $d->package_size ?? 'small',
                                'driver_id'         => $d->driver_id,
                                'pickup_address'    => $d->pickup_address,
                                'dropoff_address'   => $d->dropoff_address,
                                'status'            => $d->status,
                                'fee'               => $d->fee ?? '',
                                'payment_by'        => $d->payment_by ?? 'sender',
                                'payment_method'    => $d->payment_method ?? 'cash',
                                'scheduled_at'      => $d->scheduled_at?->format('Y-m-d\TH:i') ?? '',
                                'package_details'   => $d->package_details ?? '',
                                'notes'             => $d->notes ?? '',
                                'floor_pickup'      => $d->floor_pickup ?? 0,
                                'floor_dropoff'     => $d->floor_dropoff ?? 0,
                                'has_elevator'      => (bool)$d->has_elevator,
                                'needs_stairs_carry'=> (bool)$d->needs_stairs_carry,
                                'heavy_items'       => (bool)$d->heavy_items,
                                'requires_helpers'  => $d->requires_helpers ?? 0,
                                'helper_type'       => $d->helper_type ?? 'normal_carry',
                                'helper_fee'        => $d->helper_fee ?? '',
                                'floor_fee'         => $d->floor_fee ?? '',
                                'payment_model'     => $d->payment_model ?? 'customer_pays',
                                'split_pct_customer'=> $d->split_pct_customer ?? 50,
                                'partner_reference' => $d->partner_reference ?? '',
                            ])) }}"
                            onclick="openEdit(this)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" action="{{ route('admin.deliveries.destroy', $d) }}" class="d-inline"
                              onsubmit="return confirm('Delete order #{{ $d->id }}?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="18" class="text-center text-muted py-4">No orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer clearfix">{{ $deliveries->links() }}</div>
</div>

{{-- ════════════════ Create / Edit Modal ════════════════ --}}
<div class="modal fade" id="formModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header" id="modalHeader">
                <h5 class="modal-title" id="modalTitle">Add Order</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <form id="deliveryForm" method="POST" action="{{ route('admin.deliveries.store') }}">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                <input type="hidden" name="service_type" id="f-service-type" value="delivery">

                {{-- ── Service-type selector ── --}}
                <div class="modal-body pb-1">
                    <div class="form-group mb-3">
                        <label class="font-weight-bold d-block mb-2">Service Type <span class="text-danger">*</span></label>
                        <div class="d-flex" style="gap:10px;">
                            <div id="opt-delivery" onclick="setServiceType('delivery')"
                                 style="flex:1;cursor:pointer;border:2px solid #3b82f6;border-radius:10px;padding:12px 14px;background:#eff6ff;display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-box" style="font-size:1.3rem;color:#3b82f6;"></i>
                                <div>
                                    <div class="font-weight-bold" style="color:#1e293b;">Delivery</div>
                                    <small class="text-muted">Package, parcel, documents</small>
                                </div>
                            </div>
                            <div id="opt-moving" onclick="setServiceType('moving')"
                                 style="flex:1;cursor:pointer;border:2px solid #e2e8f0;border-radius:10px;padding:12px 14px;background:#fff;display:flex;align-items:center;gap:10px;">
                                <i class="fas fa-truck-moving" style="font-size:1.3rem;color:#f59e0b;"></i>
                                <div>
                                    <div class="font-weight-bold" style="color:#1e293b;">Moving</div>
                                    <small class="text-muted">Furniture &amp; household items</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-body pt-0">

                    {{-- Sender --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Sender Account <span class="text-danger">*</span></label>
                            <select name="sender_id" id="f-sender" class="form-control" required>
                                <option value="">— Select account —</option>
                                @foreach($senders as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Sender Name <span class="text-danger">*</span></label>
                            <input type="text" name="sender_name" id="f-sender-name" class="form-control" required>
                        </div>
                    </div>

                    {{-- Recipient --}}
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Recipient Name <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_name" id="f-recipient-name" class="form-control" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Recipient Phone <span class="text-danger">*</span></label>
                            <input type="text" name="recipient_phone" id="f-recipient-phone" class="form-control" required>
                        </div>
                    </div>

                    {{-- ── Delivery-only section ── --}}
                    <div id="delivery-fields">
                        <div class="form-group">
                            <label>Package Size <span class="text-danger">*</span></label>
                            <select name="package_size" id="f-package-size" class="form-control">
                                <option value="small">Small — fits in a backpack</option>
                                <option value="medium">Medium — fits in a car boot</option>
                                <option value="large">Large — requires a van</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Package Details</label>
                            <input type="text" name="package_details" id="f-package-details" class="form-control"
                                   placeholder="e.g. Fragile, electronics, documents…" maxlength="500">
                        </div>
                    </div>

                    {{-- ── Moving-only section ── --}}
                    <div id="moving-fields" style="display:none;">

                        {{-- Banner --}}
                        <div class="alert d-flex align-items-center mb-3"
                             style="background:#fffbeb;border:1.5px solid #fde68a;border-radius:8px;padding:10px 14px;color:#92400e;gap:10px;">
                            <i class="fas fa-truck-moving" style="color:#f59e0b;font-size:1.1rem;flex-shrink:0;"></i>
                            <div><strong>Moving Service</strong> — transporting furniture &amp; household items.</div>
                        </div>

                        {{-- Building Info --}}
                        <div class="card mb-3" style="border:1.5px solid #fde68a;border-radius:8px;">
                            <div class="card-header py-2" style="background:#fef3c7;border-bottom:1px solid #fde68a;font-weight:600;font-size:.85rem;">
                                <i class="fas fa-building mr-2" style="color:#f59e0b;"></i>Building Info
                            </div>
                            <div class="card-body py-3">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Pickup Floor</label>
                                        <select name="floor_pickup" id="f-floor-pickup" class="form-control">
                                            @for($i = 0; $i <= 20; $i++)
                                                <option value="{{ $i }}">{{ $i === 0 ? 'Ground / 1st Floor' : ordinal($i + 1).' Floor' }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Dropoff Floor</label>
                                        <select name="floor_dropoff" id="f-floor-dropoff" class="form-control">
                                            @for($i = 0; $i <= 20; $i++)
                                                <option value="{{ $i }}">{{ $i === 0 ? 'Ground / 1st Floor' : ordinal($i + 1).' Floor' }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label class="d-block">Elevator Available?</label>
                                        <div class="d-flex" style="gap:8px;margin-top:6px;">
                                            <button type="button" id="elevator-yes"
                                                    onclick="setElevator(true)"
                                                    class="btn btn-sm"
                                                    style="flex:1;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;">
                                                <i class="fas fa-check-circle mr-1"></i>Yes
                                            </button>
                                            <button type="button" id="elevator-no"
                                                    onclick="setElevator(false)"
                                                    class="btn btn-sm"
                                                    style="flex:1;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;">
                                                <i class="fas fa-times-circle mr-1"></i>No
                                            </button>
                                        </div>
                                        <input type="hidden" name="has_elevator" id="f-has-elevator" value="0">
                                        <small id="elevator-note" class="text-muted d-block mt-1" style="font-size:.75rem;"></small>
                                    </div>
                                </div>
                                <div class="form-row mt-1">
                                    <div class="col-md-6">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="f-stairs-carry" name="needs_stairs_carry" value="1">
                                            <label class="custom-control-label" for="f-stairs-carry">
                                                Needs stairs carry assistance
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Helpers & Items --}}
                        <div class="card mb-3" style="border:1.5px solid #d1fae5;border-radius:8px;">
                            <div class="card-header py-2" style="background:#ecfdf5;border-bottom:1px solid #d1fae5;font-weight:600;font-size:.85rem;">
                                <i class="fas fa-users mr-2" style="color:#10b981;"></i>Helpers &amp; Items
                            </div>
                            <div class="card-body py-3">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Number of Helpers</label>
                                        <select name="requires_helpers" id="f-requires-helpers" class="form-control">
                                            <option value="0">No helpers needed</option>
                                            <option value="1">1 helper</option>
                                            <option value="2">2 helpers</option>
                                            <option value="3">3 helpers</option>
                                            <option value="4">4 helpers</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>Helper Type</label>
                                        <select name="helper_type" id="f-helper-type" class="form-control">
                                            <option value="normal_carry">Normal Carry</option>
                                            <option value="heavy_carry">Heavy Carry (fridge, sofa, bed)</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4 d-flex align-items-end">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="f-heavy-items" name="heavy_items" value="1">
                                            <label class="custom-control-label" for="f-heavy-items">
                                                <strong>Has heavy items</strong>
                                                <small class="d-block text-muted">Fridge, sofa, bed, piano…</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Payment Model --}}
                        <div class="card mb-3" style="border:1.5px solid #ddd6fe;border-radius:8px;">
                            <div class="card-header py-2" style="background:#f5f3ff;border-bottom:1px solid #ddd6fe;font-weight:600;font-size:.85rem;">
                                <i class="fas fa-hand-holding-usd mr-2" style="color:#7c3aed;"></i>Payment Model
                            </div>
                            <div class="card-body py-3">

                                {{-- 4 option cards --}}
                                <div class="d-flex flex-wrap mb-3" style="gap:8px;" id="pm-options">

                                    <div id="pm-customer_pays" onclick="setPaymentModel('customer_pays')"
                                         style="flex:1;min-width:140px;cursor:pointer;border:2px solid #7c3aed;border-radius:10px;padding:10px 12px;background:#f5f3ff;text-align:center;">
                                        <div style="font-size:1.4rem;">🙋</div>
                                        <div class="font-weight-bold" style="font-size:.82rem;color:#1e293b;">Customer Pays</div>
                                        <small class="text-muted" style="font-size:.72rem;">អ្នកបង់ខ្លួនឯង</small>
                                    </div>

                                    <div id="pm-partner_pays" onclick="setPaymentModel('partner_pays')"
                                         style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e2e8f0;border-radius:10px;padding:10px 12px;background:#fff;text-align:center;">
                                        <div style="font-size:1.4rem;">🤝</div>
                                        <div class="font-weight-bold" style="font-size:.82rem;color:#1e293b;">Partner Pays</div>
                                        <small class="text-muted" style="font-size:.72rem;">ដៃគូបង់</small>
                                    </div>

                                    <div id="pm-split_payment" onclick="setPaymentModel('split_payment')"
                                         style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e2e8f0;border-radius:10px;padding:10px 12px;background:#fff;text-align:center;">
                                        <div style="font-size:1.4rem;">✂️</div>
                                        <div class="font-weight-bold" style="font-size:.82rem;color:#1e293b;">Split Payment</div>
                                        <small class="text-muted" style="font-size:.72rem;">បែងចែក</small>
                                    </div>

                                    <div id="pm-sponsored" onclick="setPaymentModel('sponsored')"
                                         style="flex:1;min-width:140px;cursor:pointer;border:2px solid #e2e8f0;border-radius:10px;padding:10px 12px;background:#fff;text-align:center;">
                                        <div style="font-size:1.4rem;">🎁</div>
                                        <div class="font-weight-bold" style="font-size:.82rem;color:#1e293b;">Sponsored</div>
                                        <small class="text-muted" style="font-size:.72rem;">ខាងគេបង់</small>
                                    </div>

                                </div>
                                <input type="hidden" name="payment_model" id="f-payment-model-type" value="customer_pays">

                                {{-- Conditional: Split % --}}
                                <div id="pm-split-row" style="display:none;">
                                    <label class="font-weight-semibold" style="font-size:.85rem;">
                                        Customer pays <span id="pm-split-display" style="color:#7c3aed;font-weight:700;">50%</span>
                                        — Partner pays <span id="pm-split-partner-display" style="color:#10b981;font-weight:700;">50%</span>
                                    </label>
                                    <input type="range" name="split_pct_customer" id="f-split-pct"
                                           class="custom-range" min="0" max="100" step="5" value="50"
                                           oninput="updateSplitDisplay(this.value)">
                                    <div class="d-flex justify-content-between" style="font-size:.72rem;color:#94a3b8;margin-top:2px;">
                                        <span>0% (Partner pays all)</span>
                                        <span>50 / 50</span>
                                        <span>100% (Customer pays all)</span>
                                    </div>
                                </div>

                                {{-- Conditional: Partner / Sponsor reference --}}
                                <div id="pm-reference-row" style="display:none;margin-top:8px;">
                                    <label id="pm-reference-label" class="font-weight-semibold" style="font-size:.85rem;">Partner / Sponsor Name</label>
                                    <input type="text" name="partner_reference" id="f-partner-reference"
                                           class="form-control form-control-sm"
                                           placeholder="Company name, partner code, or sponsor note…" maxlength="150">
                                </div>

                                {{-- Info pill --}}
                                <div id="pm-info" class="mt-2" style="font-size:.78rem;color:#64748b;"></div>

                            </div>
                        </div>

                        {{-- Fee Breakdown --}}
                        <div class="card mb-3" style="border:1.5px solid #e0e7ff;border-radius:8px;">
                            <div class="card-header py-2" style="background:#eef2ff;border-bottom:1px solid #e0e7ff;font-weight:600;font-size:.85rem;">
                                <i class="fas fa-calculator mr-2" style="color:#6366f1;"></i>Fee Breakdown (KHR ៛)
                            </div>
                            <div class="card-body py-3">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Helper Fee (KHR ៛)</label>
                                        <input type="number" name="helper_fee" id="f-helper-fee" class="form-control" min="0" step="1000" placeholder="Auto-calculated">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Floor Carry Fee (KHR ៛)</label>
                                        <input type="number" name="floor_fee" id="f-floor-fee" class="form-control" min="0" step="1000" placeholder="Auto-calculated">
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>{{-- /moving-fields --}}

                    {{-- Driver --}}
                    <div class="form-group">
                        <label>Assign Driver</label>
                        <select name="driver_id" id="f-driver" class="form-control">
                            <option value="">— Unassigned —</option>
                            @foreach($drivers as $dr)
                                <option value="{{ $dr->id }}">{{ $dr->name }}{{ $dr->phone ? ' — '.$dr->phone : '' }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Addresses --}}
                    <div class="form-group">
                        <label>Pickup Address <span class="text-danger">*</span></label>
                        <input type="text" name="pickup_address" id="f-pickup" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Dropoff Address <span class="text-danger">*</span></label>
                        <input type="text" name="dropoff_address" id="f-dropoff" class="form-control" required>
                    </div>

                    {{-- Status, Fee, Payment, Schedule --}}
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="f-status" class="form-control" required>
                                <option value="requested">Requested</option>
                                <option value="pending">Pending</option>
                                <option value="accepted">Accepted</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Total Fee (KHR ៛)</label>
                            <input type="number" name="fee" id="f-fee" class="form-control" min="0" step="100">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Payment By <span class="text-danger">*</span></label>
                            <select name="payment_by" id="f-payment-by" class="form-control" required>
                                <option value="sender">Sender (pays upfront)</option>
                                <option value="recipient">Recipient (COD)</option>
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Scheduled Date</label>
                            <input type="datetime-local" name="scheduled_at" id="f-scheduled-at" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Payment Method <span class="text-danger">*</span></label>
                        <select name="payment_method" id="f-payment-method" class="form-control" required>
                            <option value="cash">💵 Cash</option>
                            <option value="wallet">👛 Wallet (In-app)</option>
                            <option value="aba">🏦 ABA Bank</option>
                            <option value="wing">📱 Wing Money</option>
                            <option value="other_online">🌐 Other Online</option>
                        </select>
                    </div>

                    {{-- Notes --}}
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="f-notes" class="form-control" rows="2"
                                  placeholder="Special instructions…"></textarea>
                    </div>

                </div>{{-- /modal-body --}}

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ════════════════ Assign Driver Modal ════════════════ --}}
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Assign Driver</h5>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="assignForm" method="POST" action="">
                @csrf
                <div class="modal-body">
                    <p class="text-muted mb-3" id="assign-delivery-label"></p>
                    <div class="form-group mb-0">
                        <label class="font-weight-bold">Select Driver <span class="text-danger">*</span></label>
                        <select name="driver_id" id="assign-driver" class="form-control" required>
                            <option value="">— Choose a driver —</option>
                            @foreach($drivers as $dr)
                                <option value="{{ $dr->id }}">{{ $dr->name }}{{ $dr->phone ? ' — '.$dr->phone : '' }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted mt-1 d-block">
                            Status changes to <strong>Accepted</strong> if order is Requested or Pending.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-check mr-1"></i>Assign
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@php
function ordinal(int $n): string {
    $s = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
}
@endphp
<script>
const storeUrl   = "{{ route('admin.deliveries.store') }}";
const updateBase = '/admin/deliveries/';

// ── Elevator toggle ──────────────────────────────────────────────────────────
function setElevator(val) {
    document.getElementById('f-has-elevator').value = val ? '1' : '0';
    const yBtn = document.getElementById('elevator-yes');
    const nBtn = document.getElementById('elevator-no');
    const note = document.getElementById('elevator-note');
    if (val) {
        yBtn.style.cssText = 'flex:1;border:1.5px solid #10b981;background:#ecfdf5;color:#065f46;font-weight:600;';
        nBtn.style.cssText = 'flex:1;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;';
        note.textContent   = 'Elevator available — standard floor fee.';
    } else {
        yBtn.style.cssText = 'flex:1;border:1.5px solid #e2e8f0;background:#fff;color:#64748b;';
        nBtn.style.cssText = 'flex:1;border:1.5px solid #ef4444;background:#fef2f2;color:#991b1b;font-weight:600;';
        note.textContent   = 'No elevator — floor fee ×1.5 (stairs penalty).';
    }
}

// ── Service-type toggle ──────────────────────────────────────────────────────
function setServiceType(type) {
    document.getElementById('f-service-type').value = type;

    const dOpt = document.getElementById('opt-delivery');
    const mOpt = document.getElementById('opt-moving');
    const dFld = document.getElementById('delivery-fields');
    const mFld = document.getElementById('moving-fields');
    const hdr  = document.getElementById('modalHeader');
    const pkg  = document.getElementById('f-package-size');

    if (type === 'moving') {
        dOpt.style.border     = '2px solid #e2e8f0';
        dOpt.style.background = '#fff';
        mOpt.style.border     = '2px solid #f59e0b';
        mOpt.style.background = '#fffbeb';
        dFld.style.display    = 'none';
        mFld.style.display    = '';
        hdr.style.background  = 'linear-gradient(to right,#fef3c7,#fffbeb)';
        hdr.style.borderBottom= '2px solid #fde68a';
        pkg.removeAttribute('required');
    } else {
        dOpt.style.border     = '2px solid #3b82f6';
        dOpt.style.background = '#eff6ff';
        mOpt.style.border     = '2px solid #e2e8f0';
        mOpt.style.background = '#fff';
        dFld.style.display    = '';
        mFld.style.display    = 'none';
        hdr.style.background  = '';
        hdr.style.borderBottom= '';
        pkg.setAttribute('required', 'required');
    }
}

// ── Payment model toggle ─────────────────────────────────────────────────────
const PM_INFO = {
    customer_pays: 'ភ្ញៀវបង់ផ្ទាល់ — standard billing.',
    partner_pays:  'ដៃគូបង់ទាំងអស់ — enter partner name / code below.',
    split_payment: 'ចំណែកបង់ — adjust the slider to set customer %.',
    sponsored:     'ខាងគេបង់ — enter sponsor name below.',
};
function setPaymentModel(model) {
    document.getElementById('f-payment-model-type').value = model;

    ['customer_pays','partner_pays','split_payment','sponsored'].forEach(function(m) {
        const el = document.getElementById('pm-' + m);
        if (!el) return;
        const active = m === model;
        el.style.border     = active ? '2px solid #7c3aed' : '2px solid #e2e8f0';
        el.style.background = active ? '#f5f3ff' : '#fff';
    });

    document.getElementById('pm-split-row').style.display = model === 'split_payment' ? '' : 'none';
    const refRow = document.getElementById('pm-reference-row');
    refRow.style.display = (model === 'partner_pays' || model === 'split_payment' || model === 'sponsored') ? '' : 'none';
    const lbl = document.getElementById('pm-reference-label');
    if (lbl) lbl.textContent = model === 'sponsored' ? 'Sponsor Name / Note' : 'Partner / Sponsor Name';
    document.getElementById('pm-info').textContent = PM_INFO[model] || '';
}

function updateSplitDisplay(val) {
    val = parseInt(val, 10);
    document.getElementById('pm-split-display').textContent         = val + '%';
    document.getElementById('pm-split-partner-display').textContent = (100 - val) + '%';
}

// ── Assign ───────────────────────────────────────────────────────────────────
function openAssign(btn) {
    document.getElementById('assignForm').action         = '/admin/deliveries/' + btn.dataset.id + '/assign';
    document.getElementById('assign-delivery-label').textContent = 'Order ' + btn.dataset.label;
    document.getElementById('assign-driver').value       = btn.dataset.driver || '';
    $('#assignModal').modal('show');
}

// ── Create ───────────────────────────────────────────────────────────────────
function openCreate(defaultType) {
    document.getElementById('modalTitle').textContent = defaultType === 'moving' ? 'Add Moving Order' : 'Add Delivery';
    document.getElementById('deliveryForm').action    = storeUrl;
    document.getElementById('formMethod').value       = 'POST';
    document.getElementById('deliveryForm').reset();
    setServiceType(defaultType || 'delivery');
    setElevator(false);
    setPaymentModel('customer_pays');
    document.getElementById('f-split-pct').value = 50;
    updateSplitDisplay(50);
    document.getElementById('f-partner-reference').value = '';
    $('#formModal').modal('show');
}

// ── Edit ─────────────────────────────────────────────────────────────────────
function openEdit(btn) {
    const d = JSON.parse(btn.getAttribute('data-delivery'));

    document.getElementById('modalTitle').textContent =
        (d.service_type === 'moving' ? 'Edit Moving Order' : 'Edit Delivery') + ' #' + d.id;
    document.getElementById('deliveryForm').action = updateBase + d.id;
    document.getElementById('formMethod').value    = 'PUT';

    setServiceType(d.service_type || 'delivery');

    document.getElementById('f-sender').value           = d.sender_id;
    document.getElementById('f-sender-name').value      = d.sender_name;
    document.getElementById('f-recipient-name').value   = d.recipient_name;
    document.getElementById('f-recipient-phone').value  = d.recipient_phone;
    document.getElementById('f-package-size').value     = d.package_size;
    document.getElementById('f-driver').value           = d.driver_id || '';
    document.getElementById('f-pickup').value           = d.pickup_address;
    document.getElementById('f-dropoff').value          = d.dropoff_address;
    document.getElementById('f-status').value           = d.status;
    document.getElementById('f-fee').value              = d.fee;
    document.getElementById('f-payment-by').value       = d.payment_by     || 'sender';
    document.getElementById('f-payment-method').value   = d.payment_method || 'cash';
    document.getElementById('f-scheduled-at').value     = d.scheduled_at;
    document.getElementById('f-package-details').value  = d.package_details;
    document.getElementById('f-notes').value            = d.notes;
    // Moving fields
    document.getElementById('f-floor-pickup').value     = d.floor_pickup    || 0;
    document.getElementById('f-floor-dropoff').value    = d.floor_dropoff   || 0;
    document.getElementById('f-requires-helpers').value = d.requires_helpers || 0;
    document.getElementById('f-helper-type').value      = d.helper_type     || 'normal_carry';
    document.getElementById('f-helper-fee').value       = d.helper_fee      || '';
    document.getElementById('f-floor-fee').value        = d.floor_fee       || '';
    document.getElementById('f-stairs-carry').checked   = !!d.needs_stairs_carry;
    document.getElementById('f-heavy-items').checked    = !!d.heavy_items;
    setElevator(!!d.has_elevator);
    // Payment model fields
    const pm = d.payment_model || 'customer_pays';
    setPaymentModel(pm);
    const splitPct = d.split_pct_customer != null ? d.split_pct_customer : 50;
    document.getElementById('f-split-pct').value = splitPct;
    updateSplitDisplay(splitPct);
    document.getElementById('f-partner-reference').value = d.partner_reference || '';

    $('#formModal').modal('show');
}
</script>
@endpush
