<?php

namespace App\Http\Controllers\Api;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends ApiController
{
    /**
     * GET /v1/payment-methods
     */
    public function index(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $methods = PaymentMethod::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return $this->success(['payment_methods' => $methods]);
    }

    /**
     * POST /v1/payment-methods
     * Body: type, label, account_number, account_name, bank_name, token, is_default
     */
    public function store(Request $request)
    {
        $user = $this->authUser($request);
        if (! $user) return $this->unauthorized();

        $data = $request->validate([
            'type'           => 'required|in:card,aba,acleda,wing,payway,pi_pay,other',
            'label'          => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:64',
            'account_name'   => 'nullable|string|max:100',
            'bank_name'      => 'nullable|string|max:100',
            'token'          => 'nullable|string|max:255',
            'is_default'     => 'nullable|boolean',
        ]);

        // Clear existing default if this one is set as default
        if (! empty($data['is_default'])) {
            PaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $method = PaymentMethod::create(array_merge($data, ['user_id' => $user->id]));

        return $this->success(['payment_method' => $method], 201);
    }

    /**
     * PUT /v1/payment-methods/{method}
     */
    public function update(Request $request, PaymentMethod $method)
    {
        $user = $this->authUser($request);
        if (! $user || $method->user_id !== $user->id) return $this->unauthorized();

        $data = $request->validate([
            'label'          => 'sometimes|string|max:100',
            'account_number' => 'sometimes|string|max:64',
            'account_name'   => 'sometimes|string|max:100',
            'bank_name'      => 'sometimes|string|max:100',
            'is_default'     => 'sometimes|boolean',
        ]);

        if (! empty($data['is_default'])) {
            PaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        }

        $method->update($data);

        return $this->success(['payment_method' => $method->fresh()]);
    }

    /**
     * DELETE /v1/payment-methods/{method}
     */
    public function destroy(Request $request, PaymentMethod $method)
    {
        $user = $this->authUser($request);
        if (! $user || $method->user_id !== $user->id) return $this->unauthorized();

        $method->delete();

        return $this->success(['deleted' => true]);
    }

    /**
     * POST /v1/payment-methods/{method}/set-default
     */
    public function setDefault(Request $request, PaymentMethod $method)
    {
        $user = $this->authUser($request);
        if (! $user || $method->user_id !== $user->id) return $this->unauthorized();

        PaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);
        $method->update(['is_default' => true]);

        return $this->success(['payment_method' => $method->fresh()]);
    }
}
