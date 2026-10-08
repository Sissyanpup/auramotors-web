<?php

namespace App\Payments;

use App\Enums\TransactionPaymentStatus;
use Carbon\CarbonImmutable;

readonly class GatewayInvoice
{
    public function __construct(
        public string $reference,
        public string $url,
        public TransactionPaymentStatus $status,
        public ?CarbonImmutable $expiresAt = null,
    ) {}
}
