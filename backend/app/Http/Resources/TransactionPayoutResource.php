<?php

namespace App\Http\Resources;

use App\Models\TransactionPayout;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TransactionPayout */
class TransactionPayoutResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'transaction' => $this->whenLoaded('transaction', fn () => [
                'id' => $this->transaction->id,
                'vehicle' => $this->transaction->relationLoaded('vehicle')
                    ? "{$this->transaction->vehicle->brand} {$this->transaction->vehicle->model} {$this->transaction->vehicle->year}"
                    : null,
                'seller' => $this->transaction->relationLoaded('seller') ? $this->transaction->seller->name : null,
                'amount' => $this->transaction->amount,
            ]),
            'method' => $this->method,
            'status' => $this->status,
            'commission_rate' => $this->commission_rate,
            'commission_amount' => $this->commission_amount,
            'payout_amount' => $this->payout_amount,
            'reference' => $this->reference,
            'failure_reason' => $this->failure_reason,
            'initiated_by' => $this->initiatedBy?->name,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
