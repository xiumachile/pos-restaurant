<?php

namespace Modules\Reports\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_date' => ['nullable', 'date', 'before_or_equal:to_date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Si se envían from_date y to_date, validar rango máximo de 365 días
            if ($this->has('from_date') && $this->has('to_date')) {
                $from = Carbon::parse($this->from_date);
                $to = Carbon::parse($this->to_date);
                $diffDays = $from->diffInDays($to);

                if ($diffDays > 365) {
                    $validator->errors()->add(
                        'from_date',
                        'El rango de fechas no puede exceder 365 días.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'from_date.date' => 'La fecha de inicio debe ser una fecha válida.',
            'from_date.before_or_equal' => 'La fecha de inicio debe ser anterior o igual a la fecha de fin.',
            'to_date.date' => 'La fecha de fin debe ser una fecha válida.',
            'to_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            'days.integer' => 'El parámetro days debe ser un número entero.',
            'days.min' => 'El parámetro days debe ser al menos 1.',
            'days.max' => 'El parámetro days no puede exceder 365.',
        ];
    }

    /**
     * Obtener rango de fechas normalizado.
     * Si no se envían from_date/to_date, usa days o defaults.
     */
    public function getDateRange(int $defaultDays = 7): array
    {
        if ($this->has('from_date') && $this->has('to_date')) {
            return [
                'from' => Carbon::parse($this->from_date)->startOfDay(),
                'to' => Carbon::parse($this->to_date)->endOfDay(),
            ];
        }

        $days = $this->input('days', $defaultDays);
        return [
            'from' => Carbon::now()->subDays($days)->startOfDay(),
            'to' => Carbon::now()->endOfDay(),
        ];
    }
}
