<?php

namespace Modules\Customers\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para Customer.
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => 'customers',
            'id' => $this->uuid,
            'attributes' => [
                'phone' => $this->phone,
                'name' => $this->name,
                'email' => $this->email,
                'address' => $this->address,
                'commune' => $this->commune,
                'address_reference' => $this->address_reference,
                'full_address' => $this->full_address,
                'notes' => $this->notes,
                'orders_count' => $this->when(
                    $this->relationLoaded('orders') || $request->include_stats,
                    fn() => $this->ordersCount()
                ),
                'total_spent' => $this->when(
                    $request->include_stats,
                    fn() => $this->totalSpent()
                ),
                'created_at' => $this->created_at?->toIso8601String(),
                'updated_at' => $this->updated_at?->toIso8601String(),
            ],
        ];
    }
}
