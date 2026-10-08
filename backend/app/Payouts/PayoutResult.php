<?php

namespace App\Payouts;

use App\Enums\TransactionPayoutStatus;

readonly class PayoutResult
{
    public function __construct(
        public TransactionPayoutStatus $status,
        public ?string $reference = null,
        public ?string $failureReason = null,
    ) {}
}
