<?php

namespace Modules\Fiscal\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'certificate_file' => ['required', 'file', 'mimes:pfx,p12', 'max:5120'], // 5MB max
            'password' => ['required', 'string', 'min:4'],
            'environment' => ['required', 'string', 'in:certification,production'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del certificado es requerido.',
            'certificate_file.required' => 'El archivo del certificado es requerido.',
            'certificate_file.mimes' => 'El archivo debe ser .pfx o .p12.',
            'certificate_file.max' => 'El archivo no debe exceder 5MB.',
            'password.required' => 'La contraseña del certificado es requerida.',
            'environment.required' => 'El ambiente es requerido.',
            'environment.in' => 'El ambiente debe ser certification o production.',
        ];
    }
}
