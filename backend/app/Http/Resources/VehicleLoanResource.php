<?php

namespace App\Http\Resources;

use App\Credit\CreditService;
use App\Models\LoanInstallment;
use App\Models\VehicleLoan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VehicleLoan */
class VehicleLoanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $credit = app(CreditService::class);

        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'transaction' => $this->whenLoaded('transaction', fn () => [
                'id' => $this->transaction->id,
                'invoice_number' => $this->transaction->invoice_number,
                'vehicle' => $this->transaction->relationLoaded('vehicle')
                    ? "{$this->transaction->vehicle->brand} {$this->transaction->vehicle->model} {$this->transaction->vehicle->year}"
                    : null,
                'buyer' => $this->transaction->relationLoaded('buyer') ? $this->transaction->buyer?->name : null,
                'seller' => $this->transaction->relationLoaded('seller') ? $this->transaction->seller?->name : null,
                'vehicle_price' => $this->transaction->vehicle_price,
            ]),
            'lender_name' => $this->lender_name,
            'contract_number' => $this->contract_number,
            'status' => $this->status,
            'status_label' => $this->status->label(),
            'vehicle_financed' => $this->vehicle_financed,
            'insurance_financed' => $this->insurance_financed,
            'principal' => $this->principal,
            'flat_rate' => $this->flat_rate,
            'effective_rate' => $this->effective_rate,
            'tenor_months' => $this->tenor_months,
            'interest_total' => $this->interest_total,
            'monthly_installment' => $this->monthly_installment,
            'total_payable' => $this->total_payable,
            'submitted_at' => $this->submitted_at,
            'auto_approve_at' => $this->auto_approve_at,
            'approved_at' => $this->approved_at,
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'lender_disbursed_at' => $this->lender_disbursed_at,
            'paid_off_at' => $this->paid_off_at,
            'clock_offset_months' => $this->clock_offset_months,
            'simulated_today' => $this->today()->toDateString(),
            'summary' => $this->whenLoaded('installments', fn () => $credit->summary($this->resource)),
            'installments' => $this->whenLoaded('installments', fn () => LoanInstallmentResource::collection(
                $this->installments->each(fn (LoanInstallment $installment) => $installment->setRelation('loan', $this->resource))
            )),
        ];
    }
}
