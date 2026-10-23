<?php

namespace App\Promotions;

use App\Enums\InsuranceType;
use App\Enums\PaymentScheme;
use App\Models\Promo;
use App\Models\PromoRedemption;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\PriceBreakdownCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Promo pembeli (catatan dosen 16 Okt). Tiga jenis manfaat:
 * - discount     : potongan harga (persen/nominal, ada batas maksimum)
 * - free_addon   : gratis satu perluasan asuransi
 * - credit_rate  : potongan bunga kredit (poin persen flat/tahun)
 *
 * Promo "auto" berlaku otomatis dan tampil sebagai harga coret di katalog;
 * promo "voucher" butuh kode. Saat checkout berlaku paling banyak satu promo
 * auto (yang manfaatnya paling besar) + satu voucher. Manfaat ditanggung
 * platform — hak penjual tidak berkurang.
 */
class PromoService
{
    /** @var Collection<int, Promo>|null */
    private ?Collection $runningAuto = null;

    /**
     * @return Collection<int, Promo>
     */
    public function runningAutoPromos(): Collection
    {
        return $this->runningAuto ??= Promo::query()->running()->where('kind', Promo::KIND_AUTO)->get();
    }

    public function findVoucher(?string $code): ?Promo
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        return Promo::query()->running()
            ->where('kind', Promo::KIND_VOUCHER)
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])
            ->first();
    }

    /**
     * Alasan voucher tidak bisa dipakai, atau null bila bisa.
     */
    public function voucherError(?string $code, Vehicle $vehicle, ?PaymentScheme $scheme, ?InsuranceType $insuranceType): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        $promo = $this->findVoucher($code);

        if ($promo === null) {
            return 'Kode promo tidak ditemukan, sudah berakhir, atau kuotanya habis.';
        }

        return $this->ineligibilityReason($promo, $vehicle, $scheme, $insuranceType);
    }

    public function ineligibilityReason(Promo $promo, Vehicle $vehicle, ?PaymentScheme $scheme, ?InsuranceType $insuranceType): ?string
    {
        $price = (float) $vehicle->price;

        return match (true) {
            $promo->min_vehicle_price !== null && $price < (float) $promo->min_vehicle_price => 'Promo ini berlaku untuk kendaraan seharga minimal Rp'.number_format((float) $promo->min_vehicle_price, 0, ',', '.').'.',
            $promo->brand !== null && mb_strtolower($promo->brand) !== mb_strtolower((string) $vehicle->brand) => "Promo ini khusus kendaraan merek {$promo->brand}.",
            $promo->benefit === Promo::BENEFIT_CREDIT_RATE && $scheme !== null && $scheme !== PaymentScheme::Credit => 'Promo bunga spesial hanya berlaku untuk pembelian dengan kredit leasing.',
            $promo->benefit === Promo::BENEFIT_FREE_ADDON && $insuranceType !== null
                && ! app(PriceBreakdownCalculator::class)->addonAllowedFor((string) $promo->addon_code, $insuranceType) => 'Promo gratis perluasan asuransi ini butuh jenis asuransi yang mendukung perluasan tersebut.',
            default => null,
        };
    }

    /**
     * Promo yang berlaku untuk satu checkout/simulasi harga.
     *
     * @return array<int, Promo>
     */
    public function resolveForCheckout(Vehicle $vehicle, PaymentScheme $scheme, InsuranceType $insuranceType, ?string $voucherCode): array
    {
        $best = $this->runningAutoPromos()
            ->filter(fn (Promo $promo) => $this->ineligibilityReason($promo, $vehicle, $scheme, $insuranceType) === null)
            ->sortByDesc(fn (Promo $promo) => $this->estimatedValue($promo, $vehicle))
            ->first();

        $voucher = $this->findVoucher($voucherCode);
        if ($voucher !== null && $this->ineligibilityReason($voucher, $vehicle, $scheme, $insuranceType) !== null) {
            $voucher = null;
        }

        return array_values(array_filter([$best, $voucher]));
    }

    /**
     * Promo potongan harga terbaik untuk ditampilkan sebagai harga coret.
     *
     * @return array{id: int, name: string, badge: string|null, description: string|null, discount_amount: float, final_price: float, ends_at: string|null}|null
     */
    public function catalogPromo(Vehicle $vehicle): ?array
    {
        $promo = $this->runningAutoPromos()
            ->filter(fn (Promo $promo) => $promo->benefit === Promo::BENEFIT_DISCOUNT && $this->ineligibilityReason($promo, $vehicle, null, null) === null)
            ->sortByDesc(fn (Promo $promo) => $this->discountAmount($promo, (float) $vehicle->price))
            ->first();

        if ($promo === null) {
            return null;
        }

        $discount = $this->discountAmount($promo, (float) $vehicle->price);

        return [
            'id' => $promo->id,
            'name' => $promo->name,
            'badge' => $promo->badge,
            'description' => $promo->description,
            'discount_amount' => $discount,
            'final_price' => round((float) $vehicle->price - $discount, 0),
            'ends_at' => $promo->ends_at?->toIso8601String(),
        ];
    }

    public function discountAmount(Promo $promo, float $price): float
    {
        if ($promo->benefit !== Promo::BENEFIT_DISCOUNT) {
            return 0.0;
        }

        $amount = $promo->discount_type === 'percent'
            ? $price * (float) $promo->discount_value / 100
            : (float) $promo->discount_value;

        if ($promo->max_discount !== null) {
            $amount = min($amount, (float) $promo->max_discount);
        }

        return round(max(0.0, $amount), 0);
    }

    /**
     * Bentuk netral untuk PriceBreakdownCalculator (tanpa bergantung model).
     *
     * @param  array<int, Promo>  $promos
     * @return array<int, array<string, mixed>>
     */
    public function calculatorInput(array $promos): array
    {
        return array_map(fn (Promo $promo) => [
            'id' => $promo->id,
            'name' => $promo->name,
            'code' => $promo->code,
            'kind' => $promo->kind,
            'badge' => $promo->badge,
            'benefit' => $promo->benefit,
            'discount_type' => $promo->discount_type,
            'discount_value' => $promo->discount_value !== null ? (float) $promo->discount_value : null,
            'max_discount' => $promo->max_discount !== null ? (float) $promo->max_discount : null,
            'addon_code' => $promo->addon_code,
            'rate_cut' => $promo->rate_cut !== null ? (float) $promo->rate_cut : null,
        ], $promos);
    }

    /**
     * Catat pemakaian promo dari snapshot rincian harga & kurangi kuota.
     *
     * @param  array<string, mixed>  $breakdown
     */
    public function redeem(Transaction $transaction, array $breakdown, User $buyer): void
    {
        $lines = $breakdown['promotions']['lines'] ?? [];

        DB::transaction(function () use ($transaction, $lines, $buyer) {
            foreach ($lines as $line) {
                if (! ($line['applied'] ?? false)) {
                    continue;
                }

                PromoRedemption::create([
                    'promo_id' => $line['promo_id'],
                    'transaction_id' => $transaction->id,
                    'user_id' => $buyer->id,
                    'benefit_amount' => $line['saving'],
                ]);
                Promo::query()->whereKey($line['promo_id'])->increment('used_count');
            }
        });
    }

    private function estimatedValue(Promo $promo, Vehicle $vehicle): float
    {
        $price = (float) $vehicle->price;

        return match ($promo->benefit) {
            Promo::BENEFIT_DISCOUNT => $this->discountAmount($promo, $price),
            Promo::BENEFIT_FREE_ADDON => (float) (config("pricing.insurance.addons.{$promo->addon_code}.flat_premium")
                ?? $price * (float) config("pricing.insurance.addons.{$promo->addon_code}.rate_percent", 0) / 100),
            // Perkiraan kasar: potongan bunga × 80% harga × 3 tahun.
            Promo::BENEFIT_CREDIT_RATE => (float) $promo->rate_cut * $price * 0.8 * 3,
            default => 0.0,
        };
    }
}
