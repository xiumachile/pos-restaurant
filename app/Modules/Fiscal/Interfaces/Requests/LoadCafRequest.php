<?php

namespace Modules\Fiscal\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoadCafRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'dte_type' => ['required', 'integer', 'in:33,34,39,41,52,56,61'],
            'folio_initial' => ['required', 'integer', 'min:1'],
            'folio_final' => ['required', 'integer', 'min:1', 'gte:folio_initial'],
            'caf_xml' => ['required', 'string'],
            'authorization_date' => ['required', 'date'],
            'authorized_rut' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'dte_type.required' => 'El tipo de DTE es requerido.',
            'dte_type.in' => 'El tipo de DTE debe ser válido (33, 34, 39, 41, 52, 56, 61).',
            'folio_initial.required' => 'El folio inicial es requerido.',
            'folio_final.required' => 'El folio final es requerido.',
            'folio_final.gte' => 'El folio final debe ser mayor o igual al inicial.',
            'caf_xml.required' => 'El XML del CAF es requerido.',
            'authorization_date.required' => 'La fecha de autorización es requerida.',
        ];
    }
}
