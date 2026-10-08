<?php

namespace App\Policies;

use App\Enums\BuyerProfileStatus;
use App\Enums\UserRole;
use App\Models\BuyerProfile;
use App\Models\User;

class BuyerProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, BuyerProfile $buyerProfile): bool
    {
        return $user->isAdmin() || $user->id === $buyerProfile->user_id;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Buyer && $user->buyerProfile === null;
    }

    /**
     * Owner boleh resubmit selama belum approved (sama pola dengan SellerProfilePolicy).
     */
    public function update(User $user, BuyerProfile $buyerProfile): bool
    {
        return $user->id === $buyerProfile->user_id
            && $buyerProfile->status !== BuyerProfileStatus::Approved;
    }

    public function review(User $user, BuyerProfile $buyerProfile): bool
    {
        return $user->isAdmin();
    }
}
