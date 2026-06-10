@extends('seller.layout')
@section('title', 'Dashboard')

@section('content')
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Welcome, {{ $user->name }}</h2>
    <p class="text-gray-500 text-sm">Here's an overview of your store.</p>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    @php
    $cards = [
        ['label' => 'Total Products', 'value' => $stats['total_products'],  'color' => 'blue'],
        ['label' => 'Active',         'value' => $stats['active_products'], 'color' => 'green'],
        ['label' => 'Sold',           'value' => $stats['sold_products'],   'color' => 'gray'],
        ['label' => 'Total Orders',   'value' => $stats['total_orders'],    'color' => 'purple'],
        ['label' => 'Pending Orders', 'value' => $stats['pending_orders'],  'color' => 'yellow'],
        ['label' => 'Revenue (KHR)',  'value' => number_format($stats['total_revenue']), 'color' => 'emerald'],
    ];
    @endphp
    @foreach($cards as $card)
    <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
        <p class="text-xs text-gray-500 mb-1">{{ $card['label'] }}</p>
        <p class="text-2xl font-bold text-gray-800">{{ $card['value'] }}</p>
    </div>
    @endforeach
</div>

{{-- Recent Orders --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-semibold text-gray-800">Recent Orders</h3>
        <a href="{{ route('seller.orders') }}" class="text-sm text-blue-600 hover:underline">View all</a>
    </div>
    @if($recentOrders->isEmpty())
        <p class="text-gray-400 text-sm">No orders yet.</p>
    @else
    <table class="w-full text-sm">
        <thead class="text-left text-gray-500 border-b">
            <tr>
                <th class="pb-2">Product</th>
                <th class="pb-2">Buyer</th>
                <th class="pb-2">Amount</th>
                <th class="pb-2">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($recentOrders as $order)
            <tr>
                <td class="py-2">{{ $order->product?->title }}</td>
                <td class="py-2">{{ $order->buyer?->name }}</td>
                <td class="py-2">{{ number_format($order->total_price) }} ៛</td>
                <td class="py-2">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $order->status === 'completed' ? 'bg-green-100 text-green-700' :
                           ($order->status === 'pending'   ? 'bg-yellow-100 text-yellow-700' :
                           ($order->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700')) }}">
                        {{ ucfirst($order->status) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
