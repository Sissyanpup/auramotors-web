<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view their own transaction list (buyer
     * sees purchases, seller sees sales).
     */
    public function viewAny(User $user): bool
    {
        return $user->isBuyer() || $user->isSeller();
    }

    /**
     * Determine whether the user can view the model (owning buyer, owning
     * seller, or admin).
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->buyer_id
            || $user->id === $transaction->seller_id
            || $user->isAdmin();
    }

    /**
     * Determine whether the user can access the admin escrow dashboard.
     */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Buyer boleh membatalkan transaksinya sendiri selama masih pending
     * (belum dibayar). Setelah dibayar, jalur pembatalan lewat dispute/refund.
     */
    public function cancel(User $user, Transaction $transaction): bool
    {
        return $user->id === $transaction->buyer_id;
    }
}
