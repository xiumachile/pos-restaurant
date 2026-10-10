<?php

namespace Modules\Orders\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Modules\Orders\Domain\Entities\Order;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // HALLAZGO 11: Autorización centralizada en OrderPolicy
        $order = Order::where('uuid', $this->route('uuid') ?? $this->route('order'))->first();
        
        if (!$order) {
            return true; // Dejar que el controlador maneje 404
        }
        
        return Gate::allows('cancel', $order);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
