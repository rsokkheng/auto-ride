<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends ApiController
{
    public function index(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $payments = Payment::where('user_id', $user->id)->orderByDesc('created_at')->get();

        return $this->success(['payments' => $payments]);
    }

    public function store(Request $request)
    {
        $user = $this->authUser($request);

        if (! $user) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:64',
            'ride_id' => 'nullable|exists:rides,id',
            'delivery_id' => 'nullable|exists:deliveries,id',
            'description' => 'nullable|string|max:500',
        ]);

        $payment = Payment::create(array_merge($data, [
            'user_id' => $user->id,
            'status' => 'completed',
            'transaction_id' => 'txn_' . bin2hex(random_bytes(8)),
        ]));

        return $this->success(['payment' => $payment], 201);
    }
}
