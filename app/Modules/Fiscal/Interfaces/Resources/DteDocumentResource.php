<?php

namespace Modules\Fiscal\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DteDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'identifier' => $this->identifier(),
            'dte_type' => $this->dte_type?->value,
            'dte_type_label' => $this->dte_type?->label(),
            'folio' => $this->folio,
            'folio_formatted' => $this->formattedFolio(),
            'order_uuid' => $this->order?->uuid,
            'order_number' => $this->order?->order_number,
            'receiver_rut' => $this->receiver_rut,
            'receiver_business_name' => $this->receiver_business_name,
            'net_amount' => (float) $this->net_amount,
            'tax_amount' => (float) $this->tax_amount,
            'exempt_amount' => (float) $this->exempt_amount,
            'total_amount' => (float) $this->total_amount,
            'sii_status' => $this->sii_status?->value,
            'sii_status_label' => $this->sii_status?->label(),
            'sii_status_description' => $this->sii_status_description,
            'track_id' => $this->track_id,
            'issue_date' => $this->issue_date?->toDateString(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'has_timbre' => !empty($this->timbre_xml),
            'can_be_cancelled' => $this->sii_status?->canBeCancelled() ?? false,
            'can_be_resent' => $this->sii_status?->canBeResent() ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
