<?php

namespace App\Policies;

use App\Enums\SellerProfileStatus;
use App\Enums\VehicleStatus;
use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Determine whether the user can view the seller's own listing queue.
     */
    public function viewAny(User $user): bool
    {
        return $user->isSeller() || $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model (owner/admin can see any status).
     */
    public function view(User $user, Vehicle $vehicle): bool
    {
        return $vehicle->status === VehicleStatus::Approved
            || $user->isAdmin()
            || $user->id === $vehicle->seller_id;
    }

    /**
     * Determine whether the user can create a listing (requires approved KYC).
     */
    public function create(User $user): bool
    {
        return $user->isSeller()
            && $user->sellerProfile?->status === SellerProfileStatus::Approved;
    }

    /**
     * Determine whether the owner can edit the model (only while draft/rejected).
     */
    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->seller_id
            && in_array($vehicle->status, [VehicleStatus::Draft, VehicleStatus::Rejected], true);
    }

    /**
     * Determine whether the owner can delete the model (only while draft/rejected).
     */
    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->seller_id
            && in_array($vehicle->status, [VehicleStatus::Draft, VehicleStatus::Rejected], true);
    }

    /**
     * Determine whether the owner can submit the listing for admin review.
     */
    public function submitForReview(User $user, Vehicle $vehicle): bool
    {
        return $user->id === $vehicle->seller_id
            && in_array($vehicle->status, [VehicleStatus::Draft, VehicleStatus::Rejected], true);
    }

    /**
     * Determine whether the user can approve/reject the model.
     */
    public function review(User $user, Vehicle $vehicle): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the buyer can start a checkout (DP payment) for the model.
     */
    public function checkout(User $user, Vehicle $vehicle): bool
    {
        return $user->isBuyer() && $vehicle->status === VehicleStatus::Approved;
    }
}
