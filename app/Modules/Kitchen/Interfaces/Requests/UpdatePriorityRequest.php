<?php

namespace Modules\Kitchen\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Orders\Domain\ValueObjects\OrderPriority;

class UpdatePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()->role;
        return in_array($role, ['kitchen', 'admin', 'manager', 'waiter']);
    }

    public function rules(): array
    {
        $validPriorities = implode(',', array_column(OrderPriority::cases(), 'value'));
        return [
            'priority' => ['required', 'string', "in:{$validPriorities}"],
        ];
    }

    public function messages(): array
    {
        return [
            'priority.required' => 'La prioridad es requerida.',
            'priority.in' => 'La prioridad debe ser: normal, rush o vip.',
        ];
    }
}
