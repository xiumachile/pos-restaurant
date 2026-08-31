<?php

namespace Modules\Payments\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para dividir una cuenta (bill) en múltiples sub-cuentas.
 * 
 * Este endpoint permite dividir el total de una mesa entre varios clientes.
 * Soporta 3 modalidades de división, cada una con sus propios campos requeridos.
 * 
 * ## Modalidades disponibles:
 * 
 * ### 1. equal_split (División equitativa)
 * Divide el total en partes iguales. Útil cuando todos los comensales pagaron lo mismo.
 * 
 * ```json
 * {
 *   "type": "equal_split",
 *   "parts": 4
 * }
 * ```
 * 
 * ### 2. by_items (División por items consumidos)
 * Agrupa items específicos en sub-cuentas separadas. Útil cuando cada persona pide cosas diferentes.
 * 
 * ```json
 * {
 *   "type": "by_items",
 *   "groups": [
 *     {
 *       "item_ids": [1, 2, 3],
 *       "guest_count": 2
 *     },
 *     {
 *       "item_ids": [4, 5],
 *       "guest_count": 1
 *     }
 *   ]
 * }
 * ```
 * 
 * ### 3. custom_amount (Montos personalizados)
 * Define montos exactos para cada sub-cuenta. Útil cuando los clientes acuerdan montos específicos.
 * 
 * ```json
 * {
 *   "type": "custom_amount",
 *   "amounts": [15000, 20000, 25000]
 * }
 * ```
 * 
 * ## Restricciones:
 * - Solo puede dividirse una bill que NO esté completamente pagada
 * - La suma de las sub-cuentas debe igualar el total original
 * - No se puede dividir una bill que ya tiene pagos registrados
 * 
 * @see \Modules\Payments\Interfaces\Controllers\BillController::split()
 */
class SplitBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['waiter', 'cashier', 'admin', 'manager']);
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $rules = [
            'type' => ['required', 'string', 'in:equal_split,by_items,custom_amount'],
        ];

        if ($type === 'equal_split') {
            $rules['parts'] = ['required', 'integer', 'min:2', 'max:50'];
        } elseif ($type === 'by_items') {
            $rules['groups'] = ['required', 'array', 'min:1'];
            $rules['groups.*.item_ids'] = ['required', 'array', 'min:1'];
            $rules['groups.*.item_ids.*'] = ['integer'];
            $rules['groups.*.guest_count'] = ['nullable', 'integer', 'min:1'];
        } elseif ($type === 'custom_amount') {
            $rules['amounts'] = ['required', 'array', 'min:1'];
            $rules['amounts.*'] = ['numeric', 'min:0.01'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de split es requerido.',
            'type.in' => 'El tipo debe ser: equal_split, by_items o custom_amount.',
            'parts.required' => 'El numero de partes es requerido para equal_split.',
            'parts.min' => 'Se requieren al menos 2 partes.',
            'groups.required' => 'Los grupos son requeridos para by_items.',
            'amounts.required' => 'Los montos son requeridos para custom_amount.',
        ];
    }
}
