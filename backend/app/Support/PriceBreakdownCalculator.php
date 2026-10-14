<?php

namespace App\Support;

use App\Enums\InsuranceType;
use App\Enums\PaymentScheme;
use App\Models\Transaction;
use App\Models\Vehicle;

/**
 * Satu-satunya sumber perhitungan rincian harga transaksi: komposisi pajak di
 * harga kendaraan, premi + perluasan asuransi, tagihan yang dibayar sekarang,
 * biaya balik nama Samsat, hak bersih penjual, total biaya kepemilikan, dan
 * simulasi kredit. Hasilnya di-snapshot ke `transactions.price_breakdown` saat
 * checkout, lalu dibaca ulang oleh API, halaman frontend, dan semua PDF — jadi
 * angka yang dilihat pembeli, penjual, dan yang tercetak di kontrak selalu sama.
 *
 * Setiap baris punya `basis` (rumus dalam bahasa manusia) supaya pembeli &
 * penjual paham kenapa angkanya segitu, bukan sekadar melihat total.
 */
class PriceBreakdownCalculator
{
    /**
     * @param  array<int, string>  $addons
     * @return array<string, mixed>
     */
    public function forVehicle(Vehicle $vehicle, PaymentScheme $scheme, float $dpPercent, InsuranceType $insuranceType, array $addons = []): array
    {
        $basePremium = 0.0;
        $insuranceLabel = $insuranceType->label();
        $basePercent = null;

        foreach ($vehicle->effectiveInsuranceOptions() as $option) {
            if ($option['type'] === $insuranceType->value) {
                $basePremium = (float) $option['premium'];
                $insuranceLabel = $option['label'];
                $basePercent = (float) $vehicle->price > 0 ? $basePremium / (float) $vehicle->price * 100 : null;
            }
        }

        return $this->calculate(
            vehiclePrice: (float) $vehicle->price,
            scheme: $scheme,
            dpPercent: $dpPercent,
            insuranceType: $insuranceType,
            insuranceLabel: $insuranceLabel,
            basePremium: $basePremium,
            basePremiumPercent: $basePercent,
            addons: $addons,
        );
    }

    /**
     * Snapshot tersimpan dipakai apa adanya. Transaksi lama (sebelum fitur ini)
     * dihitung ulang dari kolom yang ada, dengan premi tersimpan dianggap premi
     * dasar tanpa biaya polis — supaya total tetap sama dengan `amount`.
     *
     * @return array<string, mixed>
     */
    public function forTransaction(Transaction $transaction): array
    {
        if (is_array($transaction->price_breakdown) && $transaction->price_breakdown !== []) {
            return $transaction->price_breakdown;
        }

        $vehiclePrice = (float) ($transaction->vehicle_price ?? $transaction->vehicle?->price ?? 0);
        $insuranceType = $transaction->insurance_type ?? InsuranceType::None;
        $insurancePremium = (float) ($transaction->insurance_premium ?? 0);

        $scheme = $transaction->payment_scheme ?? PaymentScheme::DownPayment;
        $dpPercent = (float) ($transaction->dp_percent ?? 0);
        if ($transaction->payment_scheme === null && $vehiclePrice > 0) {
            $dpPercent = round(((float) $transaction->amount - $insurancePremium) / $vehiclePrice * 100, 2);
        }

        return $this->calculate(
            vehiclePrice: $vehiclePrice,
            scheme: $scheme,
            dpPercent: $dpPercent,
            insuranceType: $insuranceType,
            insuranceLabel: $insuranceType->label(),
            basePremium: $insurancePremium,
            basePremiumPercent: $vehiclePrice > 0 && $insurancePremium > 0 ? $insurancePremium / $vehiclePrice * 100 : null,
            addons: [],
            includeInsuranceFees: false,
        );
    }

    /**
     * Daftar perluasan jaminan yang boleh dipilih untuk jenis asuransi tertentu.
     *
     * @return array<int, array{code: string, label: string, description: string, basis: string, premium: float}>
     */
    public function availableAddons(InsuranceType $insuranceType, float $sumInsured): array
    {
        if ($insuranceType === InsuranceType::None) {
            return [];
        }

        $result = [];
        foreach (config('pricing.insurance.addons') as $code => $addon) {
            if (! $this->addonAllowedFor($code, $insuranceType)) {
                continue;
            }

            [$premium, $basis] = $this->addonPremium($addon, $sumInsured);
            $result[] = [
                'code' => $code,
                'label' => $addon['label'],
                'description' => $addon['description'],
                'basis' => $basis,
                'premium' => $premium,
            ];
        }

        return $result;
    }

    public function addonAllowedFor(string $code, InsuranceType $insuranceType): bool
    {
        $addon = config("pricing.insurance.addons.{$code}");

        if ($addon === null || $insuranceType === InsuranceType::None) {
            return false;
        }

        return ! isset($addon['only_for']) || in_array($insuranceType->value, $addon['only_for'], true);
    }

    /**
     * @param  array<int, string>  $addons
     * @return array<string, mixed>
     */
    public function calculate(
        float $vehiclePrice,
        PaymentScheme $scheme,
        float $dpPercent,
        InsuranceType $insuranceType,
        string $insuranceLabel,
        float $basePremium,
        ?float $basePremiumPercent,
        array $addons,
        bool $includeInsuranceFees = true,
    ): array {
        $isFull = $scheme === PaymentScheme::Full;
        $dpPercent = $isFull ? 100.0 : $dpPercent;
        $dpAmount = $isFull ? $vehiclePrice : round($vehiclePrice * $dpPercent / 100, 2);
        $remaining = max(0.0, round($vehiclePrice - $dpAmount, 2));

        $insurance = $this->insurance($vehiclePrice, $insuranceType, $insuranceLabel, $basePremium, $basePremiumPercent, $addons, $includeInsuranceFees);
        $registration = $this->registration($vehiclePrice);

        $dueNowLines = [[
            'label' => $isFull ? 'Pelunasan harga kendaraan (bayar penuh)' : 'Uang muka (DP) kendaraan',
            'basis' => $this->percent($dpPercent).' × '.$this->rupiah($vehiclePrice),
            'amount' => $dpAmount,
        ]];
        if ($insurance['total'] > 0) {
            $dueNowLines[] = [
                'label' => 'Premi & biaya polis asuransi tahun pertama',
                'basis' => 'Rincian di bagian Asuransi',
                'amount' => $insurance['total'],
            ];
        }
        $dueNowTotal = round($dpAmount + $insurance['total'], 2);

        $commissionRate = (float) config('payout.commission_rate');
        $commission = round($dpAmount * $commissionRate, 2);

        return [
            'version' => 1,
            'vehicle_price' => $vehiclePrice,
            'payment' => [
                'scheme' => $scheme->value,
                'dp_percent' => $dpPercent,
                'dp_amount' => $dpAmount,
                'remaining_amount' => $remaining,
            ],
            'price_composition' => $this->priceComposition($vehiclePrice),
            'insurance' => $insurance,
            'due_now' => [
                'lines' => $dueNowLines,
                'total' => $dueNowTotal,
            ],
            'registration' => $registration,
            'seller_settlement' => [
                'escrow_vehicle_portion' => $dpAmount,
                'commission_rate' => $commissionRate,
                'commission' => $commission,
                'commission_basis' => $this->percent($commissionRate * 100).' × '.$this->rupiah($dpAmount).' (porsi kendaraan di escrow)',
                'net_from_escrow' => round($dpAmount - $commission, 2),
                'direct_remaining' => $remaining,
                'total_net' => round($vehiclePrice - $commission, 2),
                'note' => 'Premi asuransi tidak masuk ke penjual — diteruskan ke perusahaan asuransi.',
            ],
            'total_cost_of_ownership' => [
                'lines' => [
                    ['label' => 'Harga kendaraan', 'amount' => $vehiclePrice],
                    ['label' => 'Asuransi tahun pertama', 'amount' => $insurance['total']],
                    ['label' => 'Estimasi balik nama & registrasi (Samsat)', 'amount' => $registration['total']],
                ],
                'total' => round($vehiclePrice + $insurance['total'] + $registration['total'], 2),
            ],
            'credit_simulation' => $this->creditSimulation($remaining, $insurance['total']),
        ];
    }

    /**
     * Harga listing dianggap sudah termasuk PPN & PPnBM (seperti faktur
     * kendaraan baru). Breakdown terbalik: DPP = harga / (1 + PPN + PPnBM).
     *
     * @return array<string, mixed>
     */
    private function priceComposition(float $vehiclePrice): array
    {
        $ppnRate = (float) config('pricing.vehicle_tax.ppn_rate');
        $ppnbmRate = (float) config('pricing.vehicle_tax.ppnbm_rate');
        $divider = 1 + $ppnRate + $ppnbmRate;

        $dpp = round($vehiclePrice / $divider, 0);
        $ppn = round($dpp * $ppnRate, 0);
        $ppnbm = round($vehiclePrice - $dpp - $ppn, 0);

        return [
            'lines' => [
                [
                    'label' => 'Dasar Pengenaan Pajak (DPP)',
                    'basis' => $this->rupiah($vehiclePrice).' ÷ (1 + '.$this->percent($ppnRate * 100).' + '.$this->percent($ppnbmRate * 100).')',
                    'amount' => $dpp,
                ],
                [
                    'label' => 'PPN '.$this->percent($ppnRate * 100),
                    'basis' => $this->percent($ppnRate * 100).' × DPP',
                    'amount' => $ppn,
                ],
                [
                    'label' => 'PPnBM '.$this->percent($ppnbmRate * 100),
                    'basis' => $this->percent($ppnbmRate * 100).' × DPP (kendaraan kategori mewah)',
                    'amount' => $ppnbm,
                ],
            ],
            'total' => $vehiclePrice,
            'note' => 'Pajak ini sudah terkandung di harga kendaraan — tidak ditagih lagi.',
        ];
    }

    /**
     * @param  array<int, string>  $addons
     * @return array<string, mixed>
     */
    private function insurance(
        float $sumInsured,
        InsuranceType $type,
        string $label,
        float $basePremium,
        ?float $basePremiumPercent,
        array $addons,
        bool $includeFees,
    ): array {
        if ($type === InsuranceType::None) {
            return [
                'type' => $type->value,
                'label' => $label,
                'insurer' => null,
                'sum_insured' => 0.0,
                'addons' => [],
                'lines' => [],
                'total' => 0.0,
                'deductible_per_claim' => null,
                'theft_settlement_percent' => null,
                'total_loss_threshold_percent' => null,
            ];
        }

        $lines = [[
            'code' => 'base',
            'label' => 'Premi dasar '.$label,
            'basis' => $basePremiumPercent !== null
                ? $this->percent($basePremiumPercent).' × '.$this->rupiah($sumInsured).' (harga pertanggungan)'
                : 'Premi tetap dari penjual',
            'amount' => round($basePremium, 0),
        ]];

        $selectedAddons = [];
        foreach (array_unique($addons) as $code) {
            if (! $this->addonAllowedFor($code, $type)) {
                continue;
            }
            $addon = config("pricing.insurance.addons.{$code}");
            [$premium, $basis] = $this->addonPremium($addon, $sumInsured);
            $selectedAddons[] = $code;
            $lines[] = [
                'code' => $code,
                'label' => 'Perluasan: '.$addon['label'],
                'basis' => $basis,
                'amount' => $premium,
            ];
        }

        if ($includeFees) {
            $lines[] = [
                'code' => 'policy_fee',
                'label' => 'Biaya polis & administrasi',
                'basis' => 'Biaya tetap penerbitan polis',
                'amount' => (float) config('pricing.insurance.policy_fee'),
            ];
            $lines[] = [
                'code' => 'stamp_duty',
                'label' => 'Bea meterai polis',
                'basis' => 'Meterai Rp10.000 (UU No. 10/2020)',
                'amount' => (float) config('pricing.insurance.stamp_duty'),
            ];
        }

        $isAllRisk = $type === InsuranceType::AllRisk;

        return [
            'type' => $type->value,
            'label' => $label,
            'insurer' => config('pricing.insurance.insurer_name'),
            'sum_insured' => $sumInsured,
            'addons' => $selectedAddons,
            'lines' => $lines,
            'total' => round(array_sum(array_column($lines, 'amount')), 0),
            'deductible_per_claim' => $isAllRisk ? (float) config('pricing.insurance.deductible_per_claim') : null,
            'theft_settlement_percent' => (int) config('pricing.insurance.theft_settlement_percent'),
            'total_loss_threshold_percent' => (int) config('pricing.insurance.total_loss_threshold_percent'),
        ];
    }

    /**
     * @param  array<string, mixed>  $addon
     * @return array{0: float, 1: string}
     */
    private function addonPremium(array $addon, float $sumInsured): array
    {
        if (isset($addon['flat_premium'])) {
            return [(float) $addon['flat_premium'], 'Premi tetap per tahun'];
        }

        $rate = (float) $addon['rate_percent'];

        return [
            round($sumInsured * $rate / 100, 0),
            $this->percent($rate).' × '.$this->rupiah($sumInsured),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registration(float $vehiclePrice): array
    {
        $cfg = config('pricing.registration');
        $njkb = round($vehiclePrice * (float) $cfg['njkb_ratio'], 0);

        $lines = [
            [
                'label' => 'BBN-KB (Bea Balik Nama Kendaraan Bermotor)',
                'basis' => $this->percent($cfg['bbnkb_rate'] * 100).' × NJKB '.$this->rupiah($njkb),
                'amount' => round($njkb * (float) $cfg['bbnkb_rate'], 0),
                'payee' => 'Bapenda / Samsat',
            ],
            [
                'label' => 'PKB (Pajak Kendaraan Bermotor) 1 tahun',
                'basis' => $this->percent($cfg['pkb_rate'] * 100).' × NJKB '.$this->rupiah($njkb),
                'amount' => round($njkb * (float) $cfg['pkb_rate'], 0),
                'payee' => 'Bapenda / Samsat',
            ],
            [
                'label' => 'SWDKLLJ (Jasa Raharja)',
                'basis' => 'Tarif tetap mobil penumpang',
                'amount' => (float) $cfg['swdkllj'],
                'payee' => 'Jasa Raharja',
            ],
            [
                'label' => 'PNBP penerbitan STNK',
                'basis' => 'Tarif tetap roda 4',
                'amount' => (float) $cfg['stnk_fee'],
                'payee' => 'Polri (PNBP)',
            ],
            [
                'label' => 'PNBP TNKB (pelat nomor)',
                'basis' => 'Tarif tetap roda 4',
                'amount' => (float) $cfg['tnkb_fee'],
                'payee' => 'Polri (PNBP)',
            ],
            [
                'label' => 'PNBP penerbitan BPKB atas nama baru',
                'basis' => 'Tarif tetap roda 4',
                'amount' => (float) $cfg['bpkb_fee'],
                'payee' => 'Polri (PNBP)',
            ],
            [
                'label' => 'Cek fisik & administrasi balik nama',
                'basis' => 'Estimasi biaya layanan',
                'amount' => (float) $cfg['physical_check_fee'],
                'payee' => 'Samsat / biro jasa',
            ],
        ];

        return [
            'njkb' => $njkb,
            'njkb_basis' => $this->percent($cfg['njkb_ratio'] * 100).' × harga kendaraan (simulasi NJKB)',
            'lines' => $lines,
            'total' => round(array_sum(array_column($lines, 'amount')), 0),
            'note' => 'Dibayar pembeli langsung ke Samsat saat balik nama — tidak ditagih melalui escrow.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creditSimulation(float $principal, float $insuranceTotal): array
    {
        $cfg = config('pricing.credit');

        if ($principal <= 0) {
            return [
                'applicable' => false,
                'lender' => $cfg['lender_name'],
                'note' => 'Tidak ada sisa pelunasan (bayar penuh), simulasi kredit tidak berlaku.',
                'rows' => [],
            ];
        }

        $rows = [];
        foreach ($cfg['flat_rates'] as $months => $rate) {
            $years = $months / 12;
            $interest = round($principal * (float) $rate * $years, 0);
            $monthly = round(($principal + $interest) / $months, 0);

            $principalWithInsurance = $principal + $insuranceTotal;
            $monthlyWithInsurance = round(($principalWithInsurance + $principalWithInsurance * (float) $rate * $years) / $months, 0);

            $rows[] = [
                'tenor_months' => (int) $months,
                'flat_rate' => (float) $rate,
                'interest_total' => $interest,
                'monthly_installment' => $monthly,
                'monthly_installment_with_insurance' => $monthlyWithInsurance,
                'total_payment' => round($principal + $interest, 0),
            ];
        }

        return [
            'applicable' => true,
            'lender' => $cfg['lender_name'],
            'principal' => $principal,
            'provision_rate' => (float) $cfg['provision_rate'],
            'provision_amount' => round($principal * (float) $cfg['provision_rate'], 0),
            'admin_fee' => (float) $cfg['admin_fee'],
            'note' => 'Ilustrasi pembiayaan sisa pelunasan dengan bunga flat. Kolom "+ asuransi" = premi tahun pertama ikut dicicil.',
            'rows' => $rows,
        ];
    }

    private function rupiah(float $value): string
    {
        return 'Rp'.number_format($value, 0, ',', '.');
    }

    private function percent(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 3, ',', '.'), '0'), ',');

        return $formatted.'%';
    }
}
