@extends('seller.layout')
@section('title', isset($product) ? 'Edit Product' : 'Post Product')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('seller.products') }}" class="text-gray-400 hover:text-gray-600">← Back</a>
        <h2 class="text-2xl font-bold text-gray-800">{{ isset($product) ? 'Edit Product' : 'Post New Product' }}</h2>
    </div>

    <form method="POST"
          action="{{ isset($product) ? route('seller.products.update', $product) : route('seller.products.store') }}"
          enctype="multipart/form-data"
          class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
        @csrf
        @if(isset($product)) @method('PUT') @endif

        {{-- Title --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $product->title ?? '') }}" required
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="4"
                      class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description', $product->description ?? '') }}</textarea>
        </div>

        {{-- Category + Condition --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select name="category_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— Select —</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ old('category_id', $product->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Condition <span class="text-red-500">*</span></label>
                <select name="condition" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach(['new' => 'New', 'used' => 'Used', 'refurbished' => 'Refurbished'] as $val => $label)
                        <option value="{{ $val }}" {{ old('condition', $product->condition ?? 'used') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Listing type --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Listing Type <span class="text-red-500">*</span></label>
            <select name="listing_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach(['sale' => 'For Sale', 'rent' => 'For Rent', 'both' => 'Sale & Rent'] as $val => $label)
                    <option value="{{ $val }}" {{ old('listing_type', $product->listing_type ?? 'sale') === $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Price --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Price (KHR ៛) <span class="text-red-500">*</span></label>
                <input type="number" name="price" min="0" value="{{ old('price', $product->price ?? '') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Rent / Day (KHR ៛)</label>
                <input type="number" name="rent_price_per_day" min="0" value="{{ old('rent_price_per_day', $product->rent_price_per_day ?? '') }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        {{-- Quantity + Status --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input type="number" name="quantity" min="1" value="{{ old('quantity', $product->quantity ?? 1) }}"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach(isset($product) ? ['draft','active','paused'] : ['draft','active'] as $s)
                        <option value="{{ $s }}" {{ old('status', $product->status ?? 'active') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Location --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
            <input type="text" name="location_text" value="{{ old('location_text', $product->location_text ?? '') }}"
                   placeholder="e.g. BKK1, Phnom Penh"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        {{-- Expiry --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Listing Expires At</label>
            <input type="date" name="expires_at" value="{{ old('expires_at', isset($product->expires_at) ? $product->expires_at->format('Y-m-d') : '') }}"
                   class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        {{-- Existing images (edit mode) --}}
        @if(isset($product) && $product->images->isNotEmpty())
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Current Images</label>
            <div class="flex flex-wrap gap-3">
                @foreach($product->images as $img)
                <div class="relative">
                    <img src="{{ $img->full_url }}" class="w-24 h-24 object-cover rounded-lg border">
                    <form method="POST" action="{{ route('seller.products.images.destroy', [$product, $img]) }}">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center hover:bg-red-600">×</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Upload images --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ isset($product) ? 'Add More Images' : 'Product Images' }}</label>
            <input type="file" name="images[]" multiple accept="image/*"
                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="text-xs text-gray-400 mt-1">Max 5 MB each. JPG, PNG, WEBP.</p>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg text-sm">
                {{ isset($product) ? 'Save Changes' : 'Post Product' }}
            </button>
            <a href="{{ route('seller.products') }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-6 py-2 rounded-lg text-sm">
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
