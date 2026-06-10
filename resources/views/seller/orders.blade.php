@extends('seller.layout')
@section('title', 'Orders')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Orders</h2>
</div>

{{-- Filter --}}
<form method="GET" class="flex gap-3 mb-6">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All Status</option>
        @foreach(['pending','confirmed','completed','cancelled'] as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-sm px-4 py-2 rounded-lg">Filter</button>
</form>

@if($orders->isEmpty())
    <div class="bg-white rounded-xl p-12 text-center text-gray-400 shadow-sm border border-gray-100">
        <p class="text-4xl mb-3">🛒</p>
        <p>No orders yet.</p>
    </div>
@else
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-left text-gray-500 border-b">
            <tr>
                <th class="px-4 py-3">#</th>
                <th class="px-4 py-3">Product</th>
                <th class="px-4 py-3">Buyer</th>
                <th class="px-4 py-3">Type</th>
                <th class="px-4 py-3">Qty</th>
                <th class="px-4 py-3">Total (KHR)</th>
                <th class="px-4 py-3">Payment</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @foreach($orders as $order)
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-400">{{ $order->id }}</td>
                <td class="px-4 py-3 font-medium">{{ $order->product?->title }}</td>
                <td class="px-4 py-3">{{ $order->buyer?->name }}<br><span class="text-gray-400 text-xs">{{ $order->buyer?->phone }}</span></td>
                <td class="px-4 py-3">{{ ucfirst($order->order_type) }}</td>
                <td class="px-4 py-3">{{ $order->quantity }}</td>
                <td class="px-4 py-3 font-semibold">{{ number_format($order->total_price) }}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs {{ $order->payment_status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                        {{ ucfirst($order->payment_status) }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $order->status === 'completed' ? 'bg-green-100 text-green-700' :
                           ($order->status === 'confirmed' ? 'bg-blue-100 text-blue-700' :
                           ($order->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700')) }}">
                        {{ ucfirst($order->status) }}
                    </span>
                </td>
                <td class="px-4 py-3">
                    <div class="flex gap-2">
                        @if($order->status === 'pending')
                        <form method="POST" action="{{ route('seller.orders.confirm', $order) }}">
                            @csrf
                            <button class="text-xs bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded">Confirm</button>
                        </form>
                        @endif
                        @if($order->status === 'confirmed')
                        <form method="POST" action="{{ route('seller.orders.complete', $order) }}">
                            @csrf
                            <button class="text-xs bg-green-100 hover:bg-green-200 text-green-700 px-2 py-1 rounded">Complete</button>
                        </form>
                        @endif
                        @if(in_array($order->status, ['pending','confirmed']))
                        <form method="POST" action="{{ route('seller.orders.cancel', $order) }}"
                              onsubmit="return confirm('Cancel this order?')">
                            @csrf
                            <button class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-2 py-1 rounded">Cancel</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $orders->links() }}</div>
@endif
@endsection
