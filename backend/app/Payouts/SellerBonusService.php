<?php

namespace App\Payouts;

use App\Enums\TransactionPayoutStatus;
use App\Models\SellerBonus;
use App\Models\TransactionPayout;
use App\Models\User;

/**
 * Bonus penjual atas keberhasilan menjual: 0,5% dari harga per unit, plus
 * bonus tier satu kali saat jumlah penjualan sukses mencapai 3/5/10 unit.
 * Diberikan saat dana penjualan berhasil dicairkan, supaya hanya penjualan
 * yang benar-benar tuntas yang dihitung.
 */
class SellerBonusService
{
    /**
     * @return array<int, SellerBonus>
     */
    public function grantForPayout(TransactionPayout $payout): array
    {
        if ($payout->status !== TransactionPayoutStatus::Paid) {
            return [];
        }

        $transaction = $payout->transaction;
        $sellerId = (int) $transaction->seller_id;
        $rate = (float) config('payout.bonus.sale_rate');
        $vehiclePrice = (float) ($transaction->vehicle_price ?? $transaction->vehicle?->price ?? 0);

        $bonuses = [];

        if ($rate > 0 && $vehiclePrice > 0 && ! SellerBonus::query()->where('transaction_id', $transaction->id)->where('type', SellerBonus::TYPE_SALE)->exists()) {
            $bonuses[] = SellerBonus::create([
                'seller_id' => $sellerId,
                'transaction_id' => $transaction->id,
                'transaction_payout_id' => $payout->id,
                'type' => SellerBonus::TYPE_SALE,
                'amount' => round($vehiclePrice * $rate, 0),
                'basis' => rtrim(rtrim(number_format($rate * 100, 2, ',', '.'), '0'), ',').'% × harga kendaraan Rp'.number_format($vehiclePrice, 0, ',', '.'),
                'status' => 'paid',
                'paid_at' => now(),
            ]);
        }

        $completedSales = $this->completedSales($sellerId);
        $grantedTiers = SellerBonus::query()->where('seller_id', $sellerId)->whereNotNull('tier_threshold')->pluck('tier_threshold')->all();

        foreach (config('payout.bonus.tiers') as $threshold => $amount) {
            if ($completedSales >= $threshold && ! in_array($threshold, $grantedTiers, true)) {
                $bonuses[] = SellerBonus::create([
                    'seller_id' => $sellerId,
                    'transaction_id' => $transaction->id,
                    'transaction_payout_id' => $payout->id,
                    'type' => SellerBonus::TYPE_TIER,
                    'tier_threshold' => $threshold,
                    'amount' => $amount,
                    'basis' => "Bonus pencapaian {$threshold} penjualan sukses",
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }
        }

        if ($bonuses !== []) {
            $payout->update(['bonus_amount' => array_sum(array_map(fn (SellerBonus $bonus) => (float) $bonus->amount, $bonuses))]);
        }

        return $bonuses;
    }

    public function completedSales(int $sellerId): int
    {
        return TransactionPayout::query()
            ->where('status', TransactionPayoutStatus::Paid)
            ->whereHas('transaction', fn ($query) => $query->where('seller_id', $sellerId))
            ->distinct()
            ->count('transaction_id');
    }

    /**
     * Ringkasan untuk halaman bonus penjual: total, jumlah penjualan, dan
     * target tier berikutnya.
     *
     * @return array{total_bonus: float, completed_sales: int, sale_rate: float, next_tier: array{threshold: int, amount: float, remaining_sales: int}|null, tiers: array<int, array{threshold: int, amount: float, achieved: bool}>}
     */
    public function summary(User $seller): array
    {
        $completed = $this->completedSales($seller->id);
        $tiers = [];
        $next = null;

        foreach (config('payout.bonus.tiers') as $threshold => $amount) {
            $achieved = $completed >= $threshold;
            $tiers[] = ['threshold' => (int) $threshold, 'amount' => (float) $amount, 'achieved' => $achieved];
            if (! $achieved && $next === null) {
                $next = ['threshold' => (int) $threshold, 'amount' => (float) $amount, 'remaining_sales' => $threshold - $completed];
            }
        }

        return [
            'total_bonus' => (float) SellerBonus::query()->where('seller_id', $seller->id)->sum('amount'),
            'completed_sales' => $completed,
            'sale_rate' => (float) config('payout.bonus.sale_rate'),
            'next_tier' => $next,
            'tiers' => $tiers,
        ];
    }
}
