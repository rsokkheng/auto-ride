<?php

namespace App\Http\Controllers\Api;

use App\Models\MarketplaceCategory;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MarketplaceController extends ApiController
{
    // ── Categories ────────────────────────────────────────────────────────────

    public function categories()
    {
        $categories = MarketplaceCategory::with('children')
            ->whereNull('parent_id')
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->success(['categories' => $categories]);
    }

    // ── Products — browse ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        try {
            $query = MarketplaceProduct::with(['seller', 'category', 'images'])
                ->where('status', 'active');

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }
            if ($request->filled('seller_id')) {
                $query->where('seller_id', $request->seller_id);
            }
            if ($request->filled('listing_type')) {
                $type = $request->listing_type;
                if ($type === 'both') {
                    $query->where('listing_type', 'both');
                } else {
                    // include items listed as exactly that type OR as both
                    $query->whereIn('listing_type', [$type, 'both']);
                }
            }
            if ($request->filled('condition')) {
                $query->where('condition', $request->condition);
            }
            if ($request->filled('min_price')) {
                $query->where('price', '>=', (float) $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', (float) $request->max_price);
            }
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            $products = $query->latest()->paginate(20);

            return $this->success([
                'total'    => $products->total(),
                'products' => $products,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'debug'   => 'marketplace_products table may be missing columns — run: php artisan migrate --force',
            ], 500);
        }
    }

    public function show(MarketplaceProduct $product)
    {
        if (! in_array($product->status, ['active'])) {
            return response()->json(['success' => false, 'message' => 'This product is no longer available.'], 404);
        }
        $product->increment('views_count');
        return $this->success(['product' => $product->load(['seller', 'category', 'images', 'vehicle'])]);
    }

    // ── My products (seller) ──────────────────────────────────────────────────

    public function myProducts(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $products = MarketplaceProduct::with(['category', 'images'])
            ->where('seller_id', $user->id)
            ->latest()
            ->paginate(20);

        return $this->success(['products' => $products]);
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $isGuest = $request->input('entry_type') === 'guest';

        $data = $request->validate([
            'entry_type'         => 'nullable|in:user,guest',
            'guest_name'         => $isGuest ? 'required|string|max:100' : 'nullable|string|max:100',
            'guest_phone'        => $isGuest ? 'required|string|max:20'  : 'nullable|string|max:20',
            'title'              => 'required|string|max:200',
            'description'        => 'nullable|string',
            'category_id'        => 'nullable|exists:marketplace_categories,id',
            'vehicle_id'         => 'nullable|exists:vehicles,id',
            'condition'          => 'nullable|in:new,used,refurbished',
            'listing_type'       => 'nullable|in:sale,rent,both',
            'price'              => 'required|numeric|min:0',
            'rent_price_per_day' => 'nullable|numeric|min:0',
            'quantity'           => 'nullable|integer|min:1',
            'status'             => 'nullable|in:draft,active',
            'location_text'      => 'nullable|string|max:255',
            'location_lat'       => 'nullable|numeric|between:-90,90',
            'location_lng'       => 'nullable|numeric|between:-180,180',
            'expires_at'         => 'nullable|date',
            'images'             => 'nullable|array|max:10',
            'images.*'           => 'image|max:5120',
        ]);

        $product = MarketplaceProduct::create(array_merge(
            collect($data)->except('images')->toArray(),
            [
                'seller_id'  => $isGuest ? null : $user->id,
                'entry_type' => $isGuest ? 'guest' : 'user',
            ]
        ));

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('marketplace', 'public');
                MarketplaceProductImage::create([
                    'product_id' => $product->id,
                    'url'        => $path,
                    'disk'       => 'public',
                    'sort_order' => $i,
                ]);
            }
        }

        return $this->success(['product' => $product->load('category', 'images')], 201);
    }

    // ── Update ────────────────────────────────────────────────────────────────

    public function update(Request $request, MarketplaceProduct $product)
    {
        $user = $this->authUser($request);
        if (! $user || $product->seller_id !== $user->id) return $this->unauthorized();

        $data = $request->validate([
            'title'              => 'nullable|string|max:200',
            'description'        => 'nullable|string',
            'category_id'        => 'nullable|exists:marketplace_categories,id',
            'condition'          => 'nullable|in:new,used,refurbished',
            'listing_type'       => 'nullable|in:sale,rent,both',
            'price'              => 'nullable|numeric|min:0',
            'rent_price_per_day' => 'nullable|numeric|min:0',
            'quantity'           => 'nullable|integer|min:1',
            'status'             => 'nullable|in:draft,active,paused',
            'location_text'      => 'nullable|string|max:255',
            'location_lat'       => 'nullable|numeric|between:-90,90',
            'location_lng'       => 'nullable|numeric|between:-180,180',
            'expires_at'         => 'nullable|date',
        ]);

        $product->update(array_filter($data, fn($v) => $v !== null));

        return $this->success(['product' => $product->fresh()->load('category', 'images')]);
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(Request $request, MarketplaceProduct $product)
    {
        $user = $this->authUser($request);
        if (! $user || $product->seller_id !== $user->id) return $this->unauthorized();

        foreach ($product->images as $image) {
            Storage::disk($image->disk ?? 'public')->delete($image->url);
        }
        $product->delete();

        return $this->success(['message' => 'Product deleted']);
    }

    // ── Images ────────────────────────────────────────────────────────────────

    public function addImage(Request $request, MarketplaceProduct $product)
    {
        $user = $this->authUser($request);
        if (! $user || $product->seller_id !== $user->id) return $this->unauthorized();

        $request->validate([
            'images'   => 'required|array|min:1|max:10',
            'images.*' => 'image|max:5120',
        ]);

        $next   = ($product->images()->max('sort_order') ?? 0) + 1;
        $saved  = [];

        foreach ($request->file('images') as $i => $file) {
            $path  = $file->store('marketplace', 'public');
            $saved[] = MarketplaceProductImage::create([
                'product_id' => $product->id,
                'url'        => $path,
                'disk'       => 'public',
                'sort_order' => $next + $i,
            ]);
        }

        return $this->success(['images' => $saved], 201);
    }

    public function deleteImage(Request $request, MarketplaceProduct $product, MarketplaceProductImage $image)
    {
        $user = $this->authUser($request);
        if (! $user || $product->seller_id !== $user->id) return $this->unauthorized();

        Storage::disk($image->disk ?? 'public')->delete($image->url);
        $image->delete();

        return $this->success(['message' => 'Image deleted']);
    }

    // ── Orders ────────────────────────────────────────────────────────────────

    public function placeOrder(Request $request, MarketplaceProduct $product)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        if ($product->status === 'sold') {
            return response()->json(['success' => false, 'message' => 'This product has already been sold.'], 422);
        }
        if ($product->status === 'paused') {
            return response()->json(['success' => false, 'message' => 'This product is currently paused by the seller.'], 422);
        }
        if ($product->status === 'draft') {
            return response()->json(['success' => false, 'message' => 'This product is not available yet.'], 422);
        }
        if ($product->status !== 'active') {
            return response()->json(['success' => false, 'message' => 'This product is not available.'], 422);
        }
        if ($product->seller_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'Cannot order your own product.'], 422);
        }

        $data = $request->validate([
            'order_type'      => 'nullable|in:purchase,rent',
            'quantity'        => 'nullable|integer|min:1',
            'rent_start_date' => 'required_if:order_type,rent|nullable|date',
            'rent_end_date'   => 'required_if:order_type,rent|nullable|date|after:rent_start_date',
            'payment_method'  => 'nullable|in:cash,wallet,aba,wing,other_online',
            'notes'           => 'nullable|string',
        ]);

        $orderType = $data['order_type'] ?? 'purchase';
        $quantity  = $data['quantity']   ?? 1;

        // Block purchase if product already has a completed purchase order
        $hasSold = MarketplaceOrder::where('product_id', $product->id)
            ->where('order_type', 'purchase')
            ->where('status', 'completed')
            ->exists();
        if ($hasSold) {
            $product->update(['status' => 'sold']);
            return response()->json(['success' => false, 'message' => 'This product has already been sold and is no longer available.'], 422);
        }

        // Block if not enough stock for purchase
        if ($orderType === 'purchase' && $product->quantity !== null && $product->quantity < $quantity) {
            return response()->json(['success' => false, 'message' => 'Not enough stock. Available: ' . $product->quantity], 422);
        }

        // Block rent if dates overlap with existing active rental
        if ($orderType === 'rent') {
            $startDate = $data['rent_start_date'];
            $endDate   = $data['rent_end_date'];

            $overlap = MarketplaceOrder::where('product_id', $product->id)
                ->where('order_type', 'rent')
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('rent_start_date', '<=', $endDate)
                ->where('rent_end_date', '>=', $startDate)
                ->exists();

            if ($overlap) {
                return response()->json(['success' => false, 'message' => 'This product is already booked for the selected dates. Please choose different dates.'], 422);
            }
        }

        $days = null;

        if ($orderType === 'rent') {
            $days      = now()->parse($data['rent_start_date'])->diffInDays($data['rent_end_date']) + 1;
            $unitPrice = $product->rent_price_per_day ?? $product->price;
            $total     = $unitPrice * $days * $quantity;
        } else {
            $unitPrice = $product->price;
            $total     = $unitPrice * $quantity;
        }

        $order = MarketplaceOrder::create([
            'product_id'      => $product->id,
            'buyer_id'        => $user->id,
            'seller_id'       => $product->seller_id,
            'order_type'      => $orderType,
            'quantity'        => $quantity,
            'unit_price'      => $unitPrice,
            'total_price'     => $total,
            'rent_start_date' => $data['rent_start_date'] ?? null,
            'rent_end_date'   => $data['rent_end_date']   ?? null,
            'payment_method'  => $data['payment_method']  ?? 'cash',
            'payment_status'  => 'unpaid',
            'status'          => 'pending',
            'notes'           => $data['notes'] ?? null,
        ]);

        $order->load(['product.images', 'buyer', 'seller']);

        return $this->success([
            'order' => [
                'id'             => $order->id,
                'order_type'     => $order->order_type,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'quantity'       => $order->quantity,
                'unit_price_usd' => (float) $order->unit_price,
                'total_price_usd'=> (float) $order->total_price,
                'days'           => $orderType === 'rent' ? $days : null,
                'rent_start_date'=> $order->rent_start_date?->toDateString(),
                'rent_end_date'  => $order->rent_end_date?->toDateString(),
                'notes'          => $order->notes,
                'created_at'     => $order->created_at->toDateTimeString(),
                'product' => [
                    'id'           => $product->id,
                    'title'        => $product->title,
                    'listing_type' => $product->listing_type,
                    'condition'    => $product->condition,
                    'image'        => $product->images->first()?->full_url,
                ],
                'renter' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'phone' => $user->phone,
                ],
                'seller' => $order->seller ? [
                    'id'    => $order->seller->id,
                    'name'  => $order->seller->name,
                    'phone' => $order->seller->phone,
                ] : null,
            ],
        ], 201);
    }

    public function myOrders(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $query = MarketplaceOrder::with(['product.images', 'buyer', 'seller']);
        $type  = $request->query('type', 'buying');

        if ($type === 'selling') {
            // Orders placed on my products
            $query->where(function ($q) use ($user) {
                $q->where('seller_id', $user->id)
                  ->orWhereHas('product', fn($p) => $p->where('seller_id', $user->id));
            });
        } elseif ($type === 'rental') {
            // My rent orders only (order_type = rent)
            $query->where('buyer_id', $user->id)
                  ->where('order_type', 'rent');
        } else {
            // type=buying — purchase orders only (order_type = purchase)
            $query->where('buyer_id', $user->id)
                  ->where('order_type', 'purchase');
        }

        $orders = $query->latest()->paginate(20);

        return $this->success([
            'total'  => $orders->total(),
            'orders' => $orders->map(fn($o) => [
                'id'              => $o->id,
                'order_type'      => $o->order_type,
                'status'          => $o->status,
                'payment_status'  => $o->payment_status,
                'payment_method'  => $o->payment_method,
                'quantity'        => $o->quantity,
                'unit_price'      => (float) $o->unit_price,
                'total_price'     => (float) $o->total_price,
                'rent_start_date' => $o->rent_start_date,
                'rent_end_date'   => $o->rent_end_date,
                'notes'           => $o->notes,
                'created_at'      => $o->created_at->toDateTimeString(),
                'product' => $o->product ? [
                    'id'           => $o->product->id,
                    'title'        => $o->product->title,
                    'listing_type' => $o->product->listing_type,
                    'condition'    => $o->product->condition,
                    'image'        => $o->product->images->first()?->full_url,
                ] : null,
                'buyer'  => $o->buyer  ? ['id' => $o->buyer->id,  'name' => $o->buyer->name,  'phone' => $o->buyer->phone]  : null,
                'seller' => $o->seller ? ['id' => $o->seller->id, 'name' => $o->seller->name, 'phone' => $o->seller->phone] : null,
            ]),
        ]);
    }

    public function confirmOrder(Request $request, MarketplaceOrder $order)
    {
        $user = $this->authUser($request);
        if (! $user || $order->seller_id !== $user->id) return $this->unauthorized();

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Order cannot be confirmed'], 422);
        }

        $order->update(['status' => 'confirmed']);
        return $this->success(['order' => $order->fresh()]);
    }

    public function completeOrder(Request $request, MarketplaceOrder $order)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$order->seller_id, $order->buyer_id])) {
            return $this->unauthorized();
        }

        if ($order->status !== 'confirmed') {
            return response()->json(['message' => 'Order must be confirmed first'], 422);
        }

        $order->update(['status' => 'completed', 'payment_status' => 'paid']);

        $product   = $order->product;
        $remaining = $product->quantity - $order->quantity;
        if ($order->order_type === 'purchase') {
            $product->update([
                'quantity' => max(0, $remaining),
                'status'   => $remaining <= 0 ? 'sold' : $product->status,
            ]);
        }

        return $this->success(['order' => $order->fresh()]);
    }

    public function cancelOrder(Request $request, MarketplaceOrder $order)
    {
        $user = $this->authUser($request);
        if (! $user || ! in_array($user->id, [$order->seller_id, $order->buyer_id])) {
            return $this->unauthorized();
        }

        if (in_array($order->status, ['completed', 'cancelled'])) {
            return response()->json(['message' => 'Order cannot be cancelled'], 422);
        }

        $order->update(['status' => 'cancelled']);
        return $this->success(['order' => $order->fresh()]);
    }

    // ── Legacy MarketplaceItem (keep old endpoints working) ───────────────────

    public function legacyShow(MarketplaceItem $item)
    {
        return $this->success(['item' => $item->load(['seller', 'vehicle'])]);
    }

    public function legacyStore(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'vehicle_id'  => 'required|exists:vehicles,id',
            'title'       => 'required|string|max:120',
            'description' => 'nullable|string',
            'type'        => 'required|in:buy,rent',
            'price'       => 'required|numeric|min:0',
            'rent_rate'   => 'nullable|numeric|min:0',
            'condition'   => 'nullable|string|max:64',
        ]);

        $item = MarketplaceItem::create(array_merge($data, [
            'seller_id' => $user->id,
            'available' => true,
        ]));

        return $this->success(['item' => $item], 201);
    }

    public function purchase(Request $request, MarketplaceItem $item)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        if (! $item->available) {
            return response()->json(['message' => 'Item no longer available'], 422);
        }

        $item->update(['available' => false]);
        return $this->success(['item' => $item, 'buyer' => $user]);
    }
}
