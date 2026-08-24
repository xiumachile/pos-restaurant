<?php

namespace Modules\Catalog\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertProductPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prices' => 'required|array|min:1',
            'prices.*.price_list_id' => [
                'required',
                'string',
                Rule::exists('price_lists', 'uuid')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at'),
            ],
            'prices.*.price' => 'required|numeric|min:0',
        ];
    }
}
