<?php

namespace Modules\Printers\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'job_type' => $this->job_type,
            'printer_uuid' => $this->printer?->uuid,
            'printer_name' => $this->printer?->name,
            'order_uuid' => $this->order?->uuid,
            'order_number' => $this->order?->order_number,
            'status' => $this->status,
            'attempts' => (int) $this->attempts,
            'max_attempts' => (int) $this->max_attempts,
            'error_message' => $this->error_message,
            'bytes_size' => strlen($this->escpos_bytes ?? ''),
            'can_retry' => $this->canRetry(),
            'printed_at' => $this->printed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
