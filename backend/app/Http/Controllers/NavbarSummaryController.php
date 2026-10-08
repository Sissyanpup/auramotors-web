<?php

namespace App\Http\Controllers;

use App\Enums\EscrowStatus;
use App\Enums\SellerProfileStatus;
use App\Enums\TransactionPaymentStatus;
use App\Enums\VehicleStatus;
use App\Models\SellerProfile;
use App\Models\Transaction;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavbarSummaryController extends Controller
{
    /**
     * Ringkasan angka untuk badge navbar (cart, notifikasi, pesan, chat).
     * Perhitungan diturunkan dari state transaksi yang sudah ada supaya angka
     * realistis tanpa harus menambah tabel notifikasi/chat baru.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $cart = 0;
        $notifications = 0;

        if ($user->isBuyer()) {
            $cart = Transaction::query()
                ->where('buyer_id', $user->id)
                ->where('payment_status', TransactionPaymentStatus::Pending)
                ->count();

            $notifications = Transaction::query()
                ->where('buyer_id', $user->id)
                ->whereIn('escrow_status', [EscrowStatus::SerahTerima, EscrowStatus::PayoutRelease])
                ->whereNull('buyer_confirmed_at')
                ->count();
        }

        if ($user->isSeller()) {
            $notifications = Transaction::query()
                ->where('seller_id', $user->id)
                ->whereIn('escrow_status', [EscrowStatus::EscrowHold, EscrowStatus::SerahTerima])
                ->count();
        }

        if ($user->isAdmin()) {
            $pendingKyc = SellerProfile::query()->where('status', SellerProfileStatus::Pending)->count();
            $pendingVehicles = Vehicle::query()->where('status', VehicleStatus::PendingReview)->count();
            $openDisputes = Transaction::query()->where('escrow_status', EscrowStatus::Dispute)->count();

            $notifications = $pendingKyc + $pendingVehicles + $openDisputes;
        }

        return response()->json([
            'data' => [
                'cart' => $cart,
                'notifications' => $notifications,
                'messages' => 0,
                'chats' => 0,
            ],
        ]);
    }
}
