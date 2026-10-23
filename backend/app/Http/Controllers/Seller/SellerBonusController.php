<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerBonus;
use App\Payouts\SellerBonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bonus keberhasilan menjual milik penjual yang sedang login.
 */
class SellerBonusController extends Controller
{
    public function index(Request $request, SellerBonusService $bonuses): JsonResponse
    {
        $seller = $request->user();

        $items = SellerBonus::query()
            ->with('transaction.vehicle:id,brand,model,year')
            ->where('seller_id', $seller->id)
            ->latest()
            ->get()
            ->map(fn (SellerBonus $bonus) => [
                'id' => $bonus->id,
                'type' => $bonus->type,
                'tier_threshold' => $bonus->tier_threshold,
                'amount' => $bonus->amount,
                'basis' => $bonus->basis,
                'status' => $bonus->status,
                'paid_at' => $bonus->paid_at,
                'transaction_id' => $bonus->transaction_id,
                'vehicle' => $bonus->transaction?->vehicle
                    ? "{$bonus->transaction->vehicle->brand} {$bonus->transaction->vehicle->model} {$bonus->transaction->vehicle->year}"
                    : null,
            ]);

        return response()->json([
            'data' => $items,
            'summary' => $bonuses->summary($seller),
        ]);
    }
}
