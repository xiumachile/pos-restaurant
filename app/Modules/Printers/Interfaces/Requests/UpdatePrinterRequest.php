<?php

namespace Modules\Printers\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Printers\Domain\ValueObjects\ConnectionType;
use Modules\Printers\Domain\ValueObjects\PrinterType;

class UpdatePrinterRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'string', 'in:' . implode(',', $validTypes)],
            'connection_type' => ['sometimes', 'string', 'in:' . implode(',', $validConnections)],
            'host' => ['nullable', 'string', 'max:100'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'device_path' => ['nullable', 'string', 'max:255'],
            'paper_width' => ['nullable', 'integer', 'in:58,80'],
            'auto_cut' => ['nullable', 'boolean'],
            'open_drawer_on_print' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
