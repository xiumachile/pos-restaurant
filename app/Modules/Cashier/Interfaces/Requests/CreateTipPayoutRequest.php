<?php

namespace Modules\Cashier\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateTipPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'waiter_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|string|in:cash,card,transfer',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
