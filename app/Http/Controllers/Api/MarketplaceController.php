<?php

namespace App\Http\Controllers\Api;

use App\Models\MarketplaceItem;
use Illuminate\Http\Request;

class MarketplaceController extends ApiController
{
    public function index(Request $request)
    {
        $items = MarketplaceItem::with(['seller', 'vehicle'])->where('available', true)->paginate(20);

        return $this->success(['marketplace' => $items]);
    }

    public function show(MarketplaceItem $item)
    {
        return $this->success(['item' => $item->load(['seller', 'vehicle'])]);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'vehicle_id' => 'required|exists:vehicles,id',
            'title' => 'required|string|max:120',
            'description' => 'nullable|string',
            'type' => 'required|in:buy,rent',
            'price' => 'required|numeric|min:0',
            'rent_rate' => 'nullable|numeric|min:0',
            'condition' => 'nullable|string|max:64',
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

        if (! $user) {
            return $this->unauthorized();
        }

        if (! $item->available) {
            return response()->json(['message' => 'Item no longer available'], 422);
        }

        $item->update(['available' => false]);

        return $this->success(['item' => $item, 'buyer' => $user]);
    }
}
