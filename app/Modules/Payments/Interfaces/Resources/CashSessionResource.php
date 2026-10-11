<?php

namespace Modules\Payments\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'session_number' => $this->session_number,
            'status' => $this->status->value,
            'opening_amount' => (int) $this->opening_amount,  // ADR-011: integer CLP
            'closing_amount' => $this->closing_amount ? (int) $this->closing_amount : null,  // ADR-011: integer CLP
            'expected_amount' => $this->expected_amount ? (int) $this->expected_amount : null,  // ADR-011: integer CLP
            'difference' => $this->difference ? (int) $this->difference : null,  // ADR-018
            'opening_notes' => $this->opening_notes,
            'closing_notes' => $this->closing_notes,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'user' => $this->user ? [
                'uuid' => $this->user->uuid,
                'name' => $this->user->name,
            ] : null,
            
            // UX-04: Desglose detallado para explicación de diferencias
            'breakdown' => [
                'cash_sales' => (int) $this->payments()->where('status', 'completed')->where('method_code', 'CASH')->sum('amount'),
                'card_sales' => (int) $this->payments()->where('status', 'completed')->whereIn('method_code', ['CARD', 'DEBIT_CARD'])->sum('amount'),
                'transfer_sales' => (int) $this->payments()->where('status', 'completed')->where('method_code', 'TRANSFER')->sum('amount'),
                'total_tips' => (int) $this->payments()->where('status', 'completed')->sum('tip_amount'),
                'cash_tips_received' => (int) $this->payments()->where('status', 'completed')->where('method_code', 'CASH')->sum('tip_amount'),
                'movements_in' => (int) $this->movements()->where('type', 'in')->sum('amount'),
                'movements_out' => (int) $this->movements()->where('type', 'out')->sum('amount'),
                'pending_sync_payments' => (int) \Modules\Payments\Domain\Entities\Payment::where('cash_session_id', $this->id)->where('sync_status', 'pending')->count(),
            ],
        ];
    }
}
