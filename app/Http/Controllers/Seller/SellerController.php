<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceProductImage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SellerController extends Controller
{
    // ── Auth ──────────────────────────────────────────────────────────────────

    public function showLogin()
    {
        if (Auth::guard('seller')->check()) {
            return redirect()->route('seller.dashboard');
        }
        return view('seller.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput();
        }

        Auth::guard('seller')->login($user, $request->boolean('remember'));

        return redirect()->route('seller.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('seller')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('seller.login');
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard()
    {
        $user = Auth::guard('seller')->user();

        $stats = [
            'total_products' => MarketplaceProduct::where('seller_id', $user->id)->count(),
            'active_products'=> MarketplaceProduct::where('seller_id', $user->id)->where('status', 'active')->count(),
            'sold_products'  => MarketplaceProduct::where('seller_id', $user->id)->where('status', 'sold')->count(),
            'total_orders'   => MarketplaceOrder::where('seller_id', $user->id)->count(),
            'pending_orders' => MarketplaceOrder::where('seller_id', $user->id)->where('status', 'pending')->count(),
            'total_revenue'  => MarketplaceOrder::where('seller_id', $user->id)->where('status', 'completed')->sum('total_price'),
        ];

        $recentOrders = MarketplaceOrder::with(['product', 'buyer'])
            ->where('seller_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('seller.dashboard', compact('stats', 'recentOrders', 'user'));
    }

    // ── Products ──────────────────────────────────────────────────────────────

    public function products(Request $request)
    {
        $user     = Auth::guard('seller')->user();
        $query    = MarketplaceProduct::with(['category', 'images'])->where('seller_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $products   = $query->latest()->paginate(15);
        $categories = MarketplaceCategory::where('active', true)->orderBy('name')->get();

        return view('seller.products', compact('products', 'categories'));
    }

    public function createProduct()
    {
        $categories = MarketplaceCategory::where('active', true)->orderBy('name')->get();
        return view('seller.product-form', compact('categories'));
    }

    public function storeProduct(Request $request)
    {
        $user = Auth::guard('seller')->user();

        $data = $request->validate([
            'title'              => 'required|string|max:200',
            'description'        => 'nullable|string',
            'category_id'        => 'nullable|exists:marketplace_categories,id',
            'condition'          => 'required|in:new,used,refurbished',
            'listing_type'       => 'required|in:sale,rent,both',
            'price'              => 'required|integer|min:0',
            'rent_price_per_day' => 'nullable|integer|min:0',
            'quantity'           => 'nullable|integer|min:1',
            'status'             => 'required|in:draft,active',
            'location_text'      => 'nullable|string|max:255',
            'expires_at'         => 'nullable|date',
            'images.*'           => 'nullable|image|max:5120',
        ]);

        $product = MarketplaceProduct::create(array_merge(
            collect($data)->except('images')->toArray(),
            ['seller_id' => $user->id]
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

        return redirect()->route('seller.products')->with('success', 'Product posted successfully.');
    }

    public function editProduct(MarketplaceProduct $product)
    {
        $this->authorizeProduct($product);
        $categories = MarketplaceCategory::where('active', true)->orderBy('name')->get();
        return view('seller.product-form', compact('product', 'categories'));
    }

    public function updateProduct(Request $request, MarketplaceProduct $product)
    {
        $this->authorizeProduct($product);

        $data = $request->validate([
            'title'              => 'required|string|max:200',
            'description'        => 'nullable|string',
            'category_id'        => 'nullable|exists:marketplace_categories,id',
            'condition'          => 'required|in:new,used,refurbished',
            'listing_type'       => 'required|in:sale,rent,both',
            'price'              => 'required|integer|min:0',
            'rent_price_per_day' => 'nullable|integer|min:0',
            'quantity'           => 'nullable|integer|min:1',
            'status'             => 'required|in:draft,active,paused',
            'location_text'      => 'nullable|string|max:255',
            'expires_at'         => 'nullable|date',
            'images.*'           => 'nullable|image|max:5120',
        ]);

        $product->update(collect($data)->except('images')->toArray());

        if ($request->hasFile('images')) {
            $next = ($product->images()->max('sort_order') ?? 0) + 1;
            foreach ($request->file('images') as $i => $file) {
                $path = $file->store('marketplace', 'public');
                MarketplaceProductImage::create([
                    'product_id' => $product->id,
                    'url'        => $path,
                    'disk'       => 'public',
                    'sort_order' => $next + $i,
                ]);
            }
        }

        return redirect()->route('seller.products')->with('success', 'Product updated.');
    }

    public function deleteProduct(MarketplaceProduct $product)
    {
        $this->authorizeProduct($product);

        foreach ($product->images as $image) {
            Storage::disk($image->disk ?? 'public')->delete($image->url);
        }
        $product->delete();

        return redirect()->route('seller.products')->with('success', 'Product deleted.');
    }

    public function deleteProductImage(MarketplaceProduct $product, MarketplaceProductImage $image)
    {
        $this->authorizeProduct($product);
        Storage::disk($image->disk ?? 'public')->delete($image->url);
        $image->delete();

        return back()->with('success', 'Image removed.');
    }

    // ── Orders ────────────────────────────────────────────────────────────────

    public function orders(Request $request)
    {
        $user  = Auth::guard('seller')->user();
        $query = MarketplaceOrder::with(['product', 'buyer'])->where('seller_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(15);
        return view('seller.orders', compact('orders'));
    }

    public function confirmOrder(MarketplaceOrder $order)
    {
        $this->authorizeOrder($order);

        if ($order->status !== 'pending') {
            return back()->withErrors(['order' => 'Order cannot be confirmed.']);
        }

        $order->update(['status' => 'confirmed']);
        return back()->with('success', 'Order confirmed.');
    }

    public function completeOrder(MarketplaceOrder $order)
    {
        $this->authorizeOrder($order);

        if ($order->status !== 'confirmed') {
            return back()->withErrors(['order' => 'Order must be confirmed first.']);
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

        return back()->with('success', 'Order marked as completed.');
    }

    public function cancelOrder(MarketplaceOrder $order)
    {
        $this->authorizeOrder($order);

        if (in_array($order->status, ['completed', 'cancelled'])) {
            return back()->withErrors(['order' => 'Order cannot be cancelled.']);
        }

        $order->update(['status' => 'cancelled']);
        return back()->with('success', 'Order cancelled.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function authorizeProduct(MarketplaceProduct $product): void
    {
        $user = Auth::guard('seller')->user();
        abort_if($product->seller_id !== $user->id, 403);
    }

    private function authorizeOrder(MarketplaceOrder $order): void
    {
        $user = Auth::guard('seller')->user();
        abort_if($order->seller_id !== $user->id, 403);
    }
}
