@extends('admin.layout')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="row">
        @foreach([
            ['count' => $metrics['users'], 'label' => 'Users', 'icon' => 'fas fa-users', 'color' => 'bg-info'],
            ['count' => $metrics['drivers'], 'label' => 'Drivers', 'icon' => 'fas fa-user-tie', 'color' => 'bg-success'],
            ['count' => $metrics['vehicles'], 'label' => 'Vehicles', 'icon' => 'fas fa-car', 'color' => 'bg-warning'],
            ['count' => $metrics['rides'], 'label' => 'Rides', 'icon' => 'fas fa-route', 'color' => 'bg-danger'],
            ['count' => $metrics['deliveries'], 'label' => 'Deliveries', 'icon' => 'fas fa-box', 'color' => 'bg-primary'],
            ['count' => $metrics['marketplace'], 'label' => 'Marketplace', 'icon' => 'fas fa-store', 'color' => 'bg-indigo'],
            ['count' => $metrics['charging_stations'], 'label' => 'Charging Stations', 'icon' => 'fas fa-charging-station', 'color' => 'bg-teal'],
            ['count' => $metrics['support_tickets'], 'label' => 'Support Tickets', 'icon' => 'fas fa-headset', 'color' => 'bg-gray'],
            ['count' => $metrics['safety_incidents'], 'label' => 'Safety Reports', 'icon' => 'fas fa-shield-alt', 'color' => 'bg-secondary'],
        ] as $item)
            <div class="col-lg-4 col-6">
                <div class="small-box {{ $item['color'] }}">
                    <div class="inner">
                        <h3>{{ $item['count'] }}</h3>
                        <p>{{ $item['label'] }}</p>
                    </div>
                    <div class="icon">
                        <i class="{{ $item['icon'] }}"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Quick Manage</h3>
                </div>
                <div class="card-body">
                    <div class="btn-group flex-wrap">
                        <a href="{{ route('admin.users') }}" class="btn btn-sm btn-outline-primary mb-2">Manage Users</a>
                        <a href="{{ route('admin.vehicles') }}" class="btn btn-sm btn-outline-primary mb-2">Manage Vehicles</a>
                        <a href="{{ route('admin.rides') }}" class="btn btn-sm btn-outline-primary mb-2">Manage Rides</a>
                        <a href="{{ route('admin.deliveries') }}" class="btn btn-sm btn-outline-primary mb-2">Manage Deliveries</a>
                        <a href="{{ route('admin.marketplace') }}" class="btn btn-sm btn-outline-primary mb-2">Manage Marketplace</a>
                        <a href="{{ route('admin.charging-stations') }}" class="btn btn-sm btn-outline-primary mb-2">Manage Charging Stations</a>
                        <a href="{{ route('admin.support') }}" class="btn btn-sm btn-outline-primary mb-2">Manage Support</a>
                        <a href="{{ route('admin.safety') }}" class="btn btn-sm btn-outline-primary mb-2">Manage Safety</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Latest Users</h3>
                </div>
                <div class="card-body table-responsive p-0" style="max-height: 350px;">
                    <table class="table table-head-fixed text-nowrap">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latestUsers as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ ucfirst($user->role ?? 'passenger') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Recent Rides</h3>
                </div>
                <div class="card-body table-responsive p-0" style="max-height: 350px;">
                    <table class="table table-head-fixed text-nowrap">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Pickup</th>
                                <th>Dropoff</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latestRides as $ride)
                                <tr>
                                    <td>{{ $ride->id }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($ride->pickup_address, 20) }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($ride->dropoff_address, 20) }}</td>
                                    <td>{{ ucfirst($ride->status) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
