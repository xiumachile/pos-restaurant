<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignMenuProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => 'required|array|min:1',
            'products.*.product_uuid' => 'required|string',
            'products.*.position' => 'integer|min:0',
            'products.*.is_available' => 'boolean',
        ];
    }
}
