@extends('seller.layout')
@section('title', 'My Products')

@section('content')
<div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold text-gray-800">My Products</h2>
    <a href="{{ route('seller.products.create') }}"
       class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
        + Post Product
    </a>
</div>

{{-- Filter --}}
<form method="GET" class="flex gap-3 mb-6">
    <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">All Status</option>
        @foreach(['active','draft','paused','sold'] as $s)
            <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
    <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-sm px-4 py-2 rounded-lg">Filter</button>
</form>

@if($products->isEmpty())
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center text-gray-400">
        <p class="text-4xl mb-3">📦</p>
        <p class="font-medium">No products yet.</p>
        <a href="{{ route('seller.products.create') }}" class="mt-3 inline-block text-blue-600 hover:underline text-sm">Post your first product</a>
    </div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($products as $product)
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        {{-- Thumbnail --}}
        @if($product->images->isNotEmpty())
            <img src="{{ $product->images->first()->full_url }}" class="w-full h-40 object-cover">
        @else
            <div class="w-full h-40 bg-gray-100 flex items-center justify-center text-gray-400 text-3xl">📷</div>
        @endif

        <div class="p-4">
            <div class="flex items-start justify-between gap-2 mb-1">
                <h3 class="font-semibold text-gray-800 text-sm leading-tight">{{ $product->title }}</h3>
                <span class="shrink-0 px-2 py-0.5 rounded-full text-xs font-medium
                    {{ $product->status === 'active' ? 'bg-green-100 text-green-700' :
                       ($product->status === 'sold'  ? 'bg-gray-200 text-gray-600' :
                       ($product->status === 'paused'? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700')) }}">
                    {{ ucfirst($product->status) }}
                </span>
            </div>
            <p class="text-blue-600 font-bold text-sm mb-1">{{ number_format($product->price) }} ៛</p>
            <p class="text-gray-400 text-xs mb-3">{{ $product->category?->name ?? '—' }} · {{ ucfirst($product->condition) }}</p>

            <div class="flex gap-2">
                <a href="{{ route('seller.products.edit', $product) }}"
                   class="flex-1 text-center text-sm bg-gray-100 hover:bg-gray-200 py-1.5 rounded-lg">Edit</a>
                <form method="POST" action="{{ route('seller.products.destroy', $product) }}"
                      onsubmit="return confirm('Delete this product?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-lg">Delete</button>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="mt-6">{{ $products->links() }}</div>
@endif
@endsection
