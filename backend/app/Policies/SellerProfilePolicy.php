<?php

namespace App\Policies;

use App\Enums\SellerProfileStatus;
use App\Enums\UserRole;
use App\Models\SellerProfile;
use App\Models\User;

class SellerProfilePolicy
{
    /**
     * Determine whether the user can view the admin KYC queue.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SellerProfile $sellerProfile): bool
    {
        return $user->isAdmin() || $user->id === $sellerProfile->user_id;
    }

    /**
     * Determine whether the user can submit a KYC profile.
     */
    public function create(User $user): bool
    {
        return $user->role === UserRole::Seller && $user->sellerProfile === null;
    }

    /**
     * Determine whether the owner can resubmit the model (only while pending/rejected).
     */
    public function update(User $user, SellerProfile $sellerProfile): bool
    {
        return $user->id === $sellerProfile->user_id
            && $sellerProfile->status !== SellerProfileStatus::Approved;
    }

    /**
     * Determine whether the user can approve/reject the model.
     */
    public function review(User $user, SellerProfile $sellerProfile): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the owner can set/update payout bank account details.
     * Unlike update() (KYC resubmission), this is allowed regardless of KYC
     * status — bank details aren't part of identity verification.
     */
    public function manageBankAccount(User $user, SellerProfile $sellerProfile): bool
    {
        return $user->id === $sellerProfile->user_id;
    }
}
