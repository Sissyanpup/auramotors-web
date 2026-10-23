<?php

namespace App\Http\Resources;

use App\Credit\CreditService;
use App\Models\LoanInstallment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LoanInstallment */
class LoanInstallmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = app(CreditService::class)->installmentState($this->resource, $this->loan);

        return [
            'id' => $this->id,
            'vehicle_loan_id' => $this->vehicle_loan_id,
            'sequence' => $this->sequence,
            'due_date' => $this->due_date?->toDateString(),
            'principal_amount' => $this->principal_amount,
            'interest_amount' => $this->interest_amount,
            'amount' => $this->amount,
            'status' => $state['status'],
            'days_late' => $state['days_late'],
            'late_fee' => $state['late_fee'],
            'amount_due' => $state['amount_due'],
            'paid_at' => $this->paid_at,
            'paid_amount' => $this->paid_amount,
            'payment_method' => $this->payment_method,
            'payment_channel' => $this->payment_channel,
            'payment_account_masked' => $this->payment_account_masked,
            'payment_approval_code' => $this->payment_approval_code,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
            'note' => $this->note,
        ];
    }
}
