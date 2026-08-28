<?php

namespace App\Shared\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clase base para FormRequests del sistema POS Restaurant.
 * 
 * Establecida en S4 para consolidar patrones comunes:
 * - Reglas reutilizables para UUID, idempotency keys, etc.
 * - Mensajes comunes en español
 * - Authorization por defecto true (cada hijo override si necesita)
 * 
 * Uso:
 *   class CreateProductRequest extends BaseFormRequest
 *   {
 *       public function rules(): array
 *       {
 *           return [
 *               'uuid' => $this->uuidRule(),
 *               'idempotency_key' => $this->idempotencyKeyRule(),
 *               'name' => ['required', 'string', 'max:200'],
 *           ];
 *       }
 *   }
 */
abstract class BaseFormRequest extends FormRequest
{
    /**
     * Authorization por defecto: true.
     * Hijos pueden override para validaciones de rol específicas.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regla para UUIDs estándar.
     */
    protected function uuidRule(bool $required = true): array
    {
        $rules = ['uuid'];
        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }
        return $rules;
    }

    /**
     * Regla para UUID que debe existir en una tabla.
     * 
     * @param string $table Nombre de la tabla
     * @param string $column Columna a validar (default: uuid)
     */
    protected function uuidExistsRule(string $table, string $column = 'uuid', bool $required = true): array
    {
        $rules = ['uuid', "exists:{$table},{$column}"];
        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }
        return $rules;
    }

    /**
     * Regla para idempotency_key (UUID usado para prevenir duplicados).
     */
    protected function idempotencyKeyRule(): array
    {
        return ['required', 'uuid'];
    }

    /**
     * Regla para amount (monto monetario, mínimo 0.01).
     */
    protected function amountRule(bool $required = true, float $min = 0.01): array
    {
        $rules = ['numeric', "min:{$min}"];
        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }
        return $rules;
    }

    /**
     * Regla para tip_amount (propina, mínimo 0).
     */
    protected function tipAmountRule(): array
    {
        return ['nullable', 'numeric', 'min:0'];
    }

    /**
     * Regla para reference_code (código de referencia de pago).
     */
    protected function referenceCodeRule(): array
    {
        return ['nullable', 'string', 'max:100'];
    }

    /**
     * Regla para notes (notas libres).
     */
    protected function notesRule(int $maxLength = 500): array
    {
        return ['nullable', 'string', "max:{$maxLength}"];
    }

    /**
     * Regla para company_id (debe existir).
     */
    protected function companyIdRule(bool $required = true): array
    {
        $rules = ['integer', 'exists:companies,id'];
        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }
        return $rules;
    }

    /**
     * Regla para branch_id (debe existir).
     */
    protected function branchIdRule(bool $required = true): array
    {
        $rules = ['integer', 'exists:branches,id'];
        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }
        return $rules;
    }

    /**
     * Regla para ambiente fiscal (certification/production).
     */
    protected function environmentRule(bool $required = true): array
    {
        $rules = ['string', 'in:certification,production'];
        if ($required) {
            array_unshift($rules, 'required');
        } else {
            array_unshift($rules, 'nullable');
        }
        return $rules;
    }

    /**
     * Mensajes comunes en español para las reglas base.
     * Hijos pueden extender con array_merge(parent::messages(), [...]).
     */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es requerido.',
            'uuid' => 'El campo :attribute debe ser un UUID válido.',
            'exists' => 'El :attribute no existe.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'max' => 'El campo :attribute no debe exceder :max.',
            'string' => 'El campo :attribute debe ser texto.',
            'integer' => 'El campo :attribute debe ser entero.',
            'in' => 'El campo :attribute debe ser uno de: :values.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'file' => 'El campo :attribute debe ser un archivo.',
        ];
    }
}
