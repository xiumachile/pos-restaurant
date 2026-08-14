<?php

namespace Modules\Printers\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Printers\Domain\ValueObjects\ConnectionType;
use Modules\Printers\Domain\ValueObjects\PrinterType;

class CreatePrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()->role, ['manager', 'admin']);
    }

    public function rules(): array
    {
        $validTypes = array_column(PrinterType::cases(), 'value');
        $validConnections = array_column(ConnectionType::cases(), 'value');

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:' . implode(',', $validTypes)],
            'connection_type' => ['required', 'string', 'in:' . implode(',', $validConnections)],
            'host' => ['nullable', 'string', 'max:100', 'required_if:connection_type,tcp'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'device_path' => ['nullable', 'string', 'max:255', 'required_if:connection_type,usb,bluetooth'],
            'paper_width' => ['nullable', 'integer', 'in:58,80'],
            'auto_cut' => ['nullable', 'boolean'],
            'open_drawer_on_print' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la impresora es requerido.',
            'type.required' => 'El tipo de impresora es requerido.',
            'type.in' => 'El tipo debe ser: kitchen, bar o receipt.',
            'connection_type.required' => 'El tipo de conexión es requerido.',
            'connection_type.in' => 'La conexión debe ser: tcp, usb o bluetooth.',
            'host.required_if' => 'El host es requerido para conexiones TCP.',
            'device_path.required_if' => 'El device_path es requerido para USB/Bluetooth.',
            'port.integer' => 'El puerto debe ser un número entero.',
            'port.min' => 'El puerto debe ser mayor a 0.',
            'port.max' => 'El puerto debe ser menor a 65536.',
            'paper_width.in' => 'El ancho de papel debe ser 58mm o 80mm.',
        ];
    }
}
