<?php

namespace Modules\Companies\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Companies\Domain\ValueObjects\CapabilityKey;

class UpdateCapabilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && in_array($this->user()->role, ['admin', 'super_admin']);
    }

    public function rules(): array
    {
        $validKeys = CapabilityKey::all();
        
        return [
            'capabilities' => 'required|array',
            'capabilities.*.key' => 'required|string|in:' . implode(',', $validKeys),
            'capabilities.*.is_enabled' => 'required|boolean',
            'capabilities.*.settings' => 'sometimes|array',
        ];
    }
}
