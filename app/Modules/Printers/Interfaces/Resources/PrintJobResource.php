<?php

namespace Modules\Printers\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Solo exponer bytes si el cliente lo solicita explícitamente
        $includeBytes = $request->query('include_bytes', 'false') === 'true';
        
        return [
            'uuid' => $this->uuid,
            'job_type' => $this->job_type,
            'printer_uuid' => $this->printer?->uuid,
            'printer_name' => $this->printer?->name,
            'printer_connection' => [
                'type' => $this->printer?->connection_type?->value,
                'host' => $this->printer?->host,
                'port' => $this->printer?->port,
                'device_path' => $this->printer?->device_path,
            ],
            'order_uuid' => $this->order?->uuid,
            'order_number' => $this->order?->order_number,
            'status' => $this->status,
            'claimed_by' => $this->claimed_by,
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'attempts' => (int) $this->attempts,
            'max_attempts' => (int) $this->max_attempts,
            'error_message' => $this->error_message,
            'bytes_size' => strlen($this->escpos_bytes ?? ''),
            'escpos_base64' => $includeBytes ? $this->getEscposBase64() : null,
            'can_retry' => $this->canRetry(),
            'printed_at' => $this->printed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Obtiene los bytes ESC/POS en base64 de forma segura.
     * Maneja correctamente streams de PostgreSQL que pueden estar ya leídos.
     */
    private function getEscposBase64(): ?string
    {
        $bytes = $this->escpos_bytes;
        
        if (is_resource($bytes)) {
            // Rebobinar si es necesario
            if (ftell($bytes) !== 0) {
                rewind($bytes);
            }
            $bytes = stream_get_contents($bytes);
        }
        
        if (empty($bytes)) {
            return null;
        }
        
        return base64_encode($bytes);
    }
}
