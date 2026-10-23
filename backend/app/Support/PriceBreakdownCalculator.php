<?php

namespace App\Support;

use App\Enums\InsuranceType;
use App\Enums\PaymentScheme;
use App\Models\Transaction;
use App\Models\Vehicle;

/**
 * Satu-satunya sumber perhitungan rincian harga transaksi: komposisi pajak di
 * harga kendaraan, premi + perluasan asuransi, tagihan yang dibayar sekarang
 * (DP + asuransi + biaya balik nama), hak bersih penjual, total biaya kepemilikan, dan
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
     * @param  array<int, array<string, mixed>>  $promotions  dari PromoService::calculatorInput()
     * @return array<string, mixed>
     */
    public function forVehicle(Vehicle $vehicle, PaymentScheme $scheme, float $dpPercent, InsuranceType $insuranceType, array $addons = [], ?int $tenorMonths = null, array $promotions = []): array
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
            tenorMonths: $tenorMonths,
            vehicleYear: $vehicle->year ? (int) $vehicle->year : null,
            promotions: $promotions,
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
            includeRegistrationInDueNow: false,
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
        bool $includeRegistrationInDueNow = true,
        ?int $tenorMonths = null,
        ?int $vehicleYear = null,
        array $promotions = [],
    ): array {
        $isFull = $scheme === PaymentScheme::Full;
        $isCredit = $scheme === PaymentScheme::Credit;
        $dpPercent = $isFull ? 100.0 : $dpPercent;
        $dpAmount = $isFull ? $vehiclePrice : round($vehiclePrice * $dpPercent / 100, 2);
        $remaining = max(0.0, round($vehiclePrice - $dpAmount, 2));

        // Promo gratis perluasan: perluasannya ikut dipasang di polis, lalu premi
        // tahun pertamanya disubsidi platform (baris negatif di tagihan).
        foreach ($promotions as $promo) {
            if ($promo['benefit'] === 'free_addon' && $this->addonAllowedFor((string) $promo['addon_code'], $insuranceType)) {
                $addons[] = $promo['addon_code'];
            }
        }
        $rateCut = $isCredit
            ? array_sum(array_map(fn (array $promo) => $promo['benefit'] === 'credit_rate' ? (float) $promo['rate_cut'] : 0.0, $promotions))
            : 0.0;

        $insurance = $this->insurance($vehiclePrice, $insuranceType, $insuranceLabel, $basePremium, $basePremiumPercent, $addons, $includeInsuranceFees);
        $registration = $this->registration($vehiclePrice, $includeRegistrationInDueNow);
        $tenor = $tenorMonths ?? array_key_first(config('pricing.credit.flat_rates'));
        $credit = $isCredit && $remaining > 0
            ? $this->creditPlan($remaining, $insurance['total'], $tenor, $vehicleYear, $rateCut)
            : null;
        $creditWithoutPromo = $credit !== null && $rateCut > 0
            ? $this->creditPlan($remaining, $insurance['total'], $tenor, $vehicleYear)
            : $credit;
        $promoSection = $this->promotions($promotions, $vehiclePrice, $dpAmount, $insurance, $credit, $creditWithoutPromo);

        $dueNowLines = [[
            'label' => match (true) {
                $isFull => 'Pelunasan harga kendaraan (bayar penuh)',
                $isCredit => 'Uang muka (DP) kredit kendaraan',
                default => 'Uang muka (DP) kendaraan',
            },
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
        // Biaya balik nama ditagih bersama DP (catatan dosen 16 Okt), bukan dicicil:
        // escrow meneruskannya ke Samsat/biro jasa, tidak ikut ke penjual.
        if ($includeRegistrationInDueNow) {
            $dueNowLines[] = [
                'label' => 'Biaya balik nama & registrasi kendaraan',
                'basis' => 'Rincian di bagian Biaya Balik Nama — diteruskan escrow ke Samsat',
                'amount' => $registration['total'],
            ];
        }
        // Biaya kredit di muka (provisi, administrasi, fidusia) milik leasing — ikut
        // dibayar ke escrow bersama DP lalu diteruskan, bukan ke penjual.
        foreach ($credit['upfront']['lines'] ?? [] as $line) {
            $dueNowLines[] = $line;
        }
        foreach ($promoSection['lines'] as $line) {
            if ($line['applied'] && $line['in_due_now']) {
                $dueNowLines[] = ['label' => 'Promo: '.$line['label'], 'basis' => $line['basis'], 'amount' => -$line['saving']];
            }
        }
        $dueNowTotal = round(array_sum(array_column($dueNowLines, 'amount')), 2);

        $commissionRate = (float) config('payout.commission_rate');
        $commission = round($dpAmount * $commissionRate, 2);

        $ownershipLines = [
            ['label' => 'Harga kendaraan', 'amount' => $vehiclePrice],
            ['label' => 'Asuransi tahun pertama', 'amount' => $insurance['total']],
            ['label' => 'Estimasi balik nama & registrasi (Samsat)', 'amount' => $registration['total']],
        ];
        if ($credit !== null) {
            $ownershipLines[] = ['label' => 'Asuransi tahun ke-2 dst. (ikut dicicil)', 'amount' => $credit['insurance_financed']];
            $ownershipLines[] = ['label' => 'Total bunga kredit '.$credit['tenor_months'].' bulan', 'amount' => $credit['interest_total']];
            $ownershipLines[] = ['label' => 'Biaya kredit di muka (provisi, admin, fidusia)', 'amount' => $credit['upfront']['total']];
        }
        if ($promoSection['due_now_saving'] > 0) {
            $ownershipLines[] = ['label' => 'Hemat dari promo (subsidi platform)', 'amount' => -$promoSection['due_now_saving']];
        }

        return [
            'version' => 2,
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
                // Siapa yang melunasi sisa harga ke penjual: pembeli langsung (skema DP) atau leasing (kredit).
                'remaining_payer' => $remaining <= 0 ? null : ($isCredit ? 'lender' : 'buyer'),
                'total_net' => round($vehiclePrice - $commission, 2),
                'note' => $isCredit
                    ? 'Sisa harga dilunasi sekaligus oleh leasing setelah kredit disetujui — penjual tidak menanggung cicilan. Bunga, biaya kredit, premi asuransi, dan biaya balik nama bukan hak penjual.'
                    : 'Premi asuransi dan biaya balik nama tidak masuk ke penjual — diteruskan ke perusahaan asuransi dan Samsat.',
            ],
            'total_cost_of_ownership' => [
                'lines' => $ownershipLines,
                'total' => round(array_sum(array_column($ownershipLines, 'amount')), 2),
            ],
            'credit' => $credit,
            'promotions' => $promoSection,
            'credit_simulation' => $this->creditSimulation($remaining, $insurance['total'], $vehicleYear),
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
    private function registration(float $vehiclePrice, bool $inEscrow): array
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
            'in_escrow' => $inEscrow,
            'note' => $inEscrow
                ? 'Ditagih bersama uang muka (DP) dan ditahan di escrow, lalu disetorkan ke Samsat/biro jasa saat proses balik nama. Tidak ikut dicicil.'
                : 'Dibayar pembeli langsung ke Samsat saat balik nama — tidak ditagih melalui escrow.',
        ];
    }

    /**
     * Rencana kredit yang dipilih pembeli (skema kredit). Premi asuransi tahun
     * pertama dibayar di muka; premi tahun ke-2 dst. dibiayai leasing (asuransi
     * wajib selama tenor). Angsuran dibulatkan ke atas ke ratusan rupiah.
     *
     * @return array<string, mixed>
     */
    public function creditPlan(float $vehicleFinanced, float $annualInsurance, int $tenorMonths, ?int $vehicleYear, float $promoRateCut = 0.0): array
    {
        $cfg = config('pricing.credit');
        $years = $tenorMonths / 12;
        [$baseRate, $surcharge, $age] = $this->creditRate($tenorMonths, $vehicleYear);
        $promoRateCut = min($promoRateCut, $baseRate + $surcharge);
        $rate = $baseRate + $surcharge - $promoRateCut;

        $insuranceFinanced = round($annualInsurance * max(0, ceil($years) - 1), 0);
        $principal = round($vehicleFinanced + $insuranceFinanced, 0);
        $monthly = $this->roundUpToHundreds(($principal + $principal * $rate * $years) / $tenorMonths);
        $totalPayable = $monthly * $tenorMonths;

        $provision = round($principal * (float) $cfg['provision_rate'], 0);
        $fiducia = $this->fiduciaFee($principal);
        $upfrontLines = [
            [
                'label' => 'Provisi kredit',
                'basis' => $this->percent((float) $cfg['provision_rate'] * 100).' × pokok pinjaman '.$this->rupiah($principal),
                'amount' => $provision,
            ],
            [
                'label' => 'Biaya administrasi leasing',
                'basis' => 'Biaya tetap per kontrak',
                'amount' => (float) $cfg['admin_fee'],
            ],
            [
                'label' => 'Biaya jaminan fidusia',
                'basis' => 'Honor notaris '.$this->rupiah((float) $cfg['fiducia']['notary_fee']).' + PNBP pendaftaran '.$this->rupiah($fiducia - (float) $cfg['fiducia']['notary_fee']),
                'amount' => $fiducia,
            ],
        ];

        return [
            'lender' => $cfg['lender_name'],
            'tenor_months' => $tenorMonths,
            'vehicle_age_years' => $age,
            'base_flat_rate' => $baseRate,
            'age_surcharge_rate' => $surcharge,
            'flat_rate' => $rate,
            'rate_basis' => $this->percent($baseRate * 100).' flat/tahun (tenor '.$tenorMonths.' bln)'
                .($surcharge > 0 ? ' + '.$this->percent($surcharge * 100).' unit berumur '.$age.' tahun' : '')
                .($promoRateCut > 0 ? ' − '.$this->percent($promoRateCut * 100).' promo bunga spesial' : ''),
            'promo_rate_cut' => $promoRateCut,
            'vehicle_financed' => $vehicleFinanced,
            'insurance_financed' => $insuranceFinanced,
            'principal' => $principal,
            'interest_total' => round($totalPayable - $principal, 0),
            'monthly_installment' => $monthly,
            'total_payable' => $totalPayable,
            'effective_rate' => $this->effectiveAnnualRate($principal, $monthly, $tenorMonths),
            'upfront' => [
                'lines' => $upfrontLines,
                'total' => round(array_sum(array_column($upfrontLines, 'amount')), 0),
            ],
            'late_fee_daily_rate' => (float) $cfg['late_fee_daily_rate'],
            'note' => 'Leasing melunasi sisa harga kendaraan ke penjual setelah kredit disetujui. Angsuran pertama jatuh tempo satu bulan setelah persetujuan (dibayar di belakang). Denda keterlambatan '
                .$this->percent((float) $cfg['late_fee_daily_rate'] * 100).' per hari dari nilai angsuran. BPKB ditahan leasing sampai lunas.',
        ];
    }

    /**
     * Manfaat promo yang berlaku. Potongan harga & gratis perluasan mengurangi
     * tagihan sekarang (disubsidi platform); potongan bunga mengurangi total
     * cicilan ke leasing. Hak bersih penjual tidak berubah.
     *
     * @param  array<int, array<string, mixed>>  $promotions
     * @param  array<string, mixed>  $insurance
     * @param  array<string, mixed>|null  $credit
     * @param  array<string, mixed>|null  $creditWithoutPromo
     * @return array{lines: array<int, array<string, mixed>>, due_now_saving: float, total_saving: float, note: string}
     */
    private function promotions(array $promotions, float $vehiclePrice, float $dpAmount, array $insurance, ?array $credit, ?array $creditWithoutPromo): array
    {
        $lines = [];

        foreach ($promotions as $promo) {
            $line = [
                'promo_id' => $promo['id'],
                'name' => $promo['name'],
                'code' => $promo['code'],
                'kind' => $promo['kind'],
                'badge' => $promo['badge'],
                'benefit' => $promo['benefit'],
                'label' => $promo['name'],
                'basis' => '',
                'saving' => 0.0,
                'in_due_now' => false,
                'applied' => false,
            ];

            if ($promo['benefit'] === 'discount') {
                $amount = $promo['discount_type'] === 'percent'
                    ? $vehiclePrice * (float) $promo['discount_value'] / 100
                    : (float) $promo['discount_value'];
                if ($promo['max_discount'] !== null) {
                    $amount = min($amount, (float) $promo['max_discount']);
                }
                $amount = round(min(max(0.0, $amount), $dpAmount), 0);
                $line['basis'] = ($promo['discount_type'] === 'percent'
                    ? $this->percent((float) $promo['discount_value']).' × '.$this->rupiah($vehiclePrice)
                    : 'Potongan tetap')
                    .($promo['max_discount'] !== null ? ', maks. '.$this->rupiah((float) $promo['max_discount']) : '');
                $line['saving'] = $amount;
                $line['in_due_now'] = true;
                $line['applied'] = $amount > 0;
            }

            if ($promo['benefit'] === 'free_addon') {
                $addonLine = collect($insurance['lines'])->firstWhere('code', $promo['addon_code']);
                if ($addonLine !== null) {
                    $line['label'] = $promo['name'].' — gratis '.mb_strtolower(str_replace('Perluasan: ', '', $addonLine['label']));
                    $line['basis'] = 'Premi perluasan tahun pertama ditanggung platform';
                    $line['saving'] = (float) $addonLine['amount'];
                    $line['in_due_now'] = true;
                    $line['applied'] = true;
                }
            }

            if ($promo['benefit'] === 'credit_rate' && $credit !== null && $creditWithoutPromo !== null) {
                $line['basis'] = 'Bunga flat turun '.$this->percent((float) $promo['rate_cut'] * 100).' per tahun selama tenor';
                $line['saving'] = round((float) $creditWithoutPromo['total_payable'] - (float) $credit['total_payable'], 0);
                $line['applied'] = true;
            }

            $lines[] = $line;
        }

        $dueNowSaving = array_sum(array_map(fn (array $line) => $line['applied'] && $line['in_due_now'] ? $line['saving'] : 0.0, $lines));

        return [
            'lines' => $lines,
            'due_now_saving' => round($dueNowSaving, 0),
            'total_saving' => round(array_sum(array_map(fn (array $line) => $line['applied'] ? $line['saving'] : 0.0, $lines)), 0),
            'note' => 'Potongan promo ditanggung AuraMotors (subsidi platform) atau leasing untuk bunga spesial — dana yang diterima penjual tidak berkurang.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function creditSimulation(float $principal, float $insuranceTotal, ?int $vehicleYear): array
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
        foreach (array_keys($cfg['flat_rates']) as $months) {
            $plan = $this->creditPlan($principal, $insuranceTotal, (int) $months, $vehicleYear);
            $vehicleOnly = $this->roundUpToHundreds(($principal + $principal * $plan['flat_rate'] * $months / 12) / $months);

            $rows[] = [
                'tenor_months' => (int) $months,
                'flat_rate' => $plan['flat_rate'],
                'effective_rate' => $this->effectiveAnnualRate($principal, $vehicleOnly, (int) $months),
                'interest_total' => round($vehicleOnly * $months - $principal, 0),
                'monthly_installment' => $vehicleOnly,
                'monthly_installment_with_insurance' => $plan['monthly_installment'],
                'total_payment' => round($vehicleOnly * $months, 0),
            ];
        }

        [, $surcharge, $age] = $this->creditRate((int) array_key_first($cfg['flat_rates']), $vehicleYear);

        return [
            'applicable' => true,
            'lender' => $cfg['lender_name'],
            'principal' => $principal,
            'min_dp_percent' => (int) $cfg['min_dp_percent'],
            'age_surcharge_rate' => $surcharge,
            'vehicle_age_years' => $age,
            'provision_rate' => (float) $cfg['provision_rate'],
            'provision_amount' => round($principal * (float) $cfg['provision_rate'], 0),
            'admin_fee' => (float) $cfg['admin_fee'],
            'fiducia_fee' => $this->fiduciaFee($principal),
            'note' => 'Ilustrasi kredit leasing untuk sisa harga dengan bunga flat (bunga efektif ditampilkan sebagai pembanding). Kolom "+ asuransi" = premi tahun ke-2 dst. ikut dicicil karena asuransi wajib selama tenor. DP minimal kredit '.$cfg['min_dp_percent'].'%.',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{0: float, 1: float, 2: int|null} [bunga dasar, tambahan umur, umur unit]
     */
    private function creditRate(int $tenorMonths, ?int $vehicleYear): array
    {
        $cfg = config('pricing.credit');
        $baseRate = (float) ($cfg['flat_rates'][$tenorMonths] ?? end($cfg['flat_rates']));
        $age = $vehicleYear !== null ? max(0, (int) now()->year - $vehicleYear) : null;

        $surcharge = 0.0;
        foreach ($cfg['age_surcharges'] as $rule) {
            if ($age !== null && $age >= $rule['min_age']) {
                $surcharge = (float) $rule['rate'];
                break;
            }
        }

        return [$baseRate, $surcharge, $age];
    }

    private function fiduciaFee(float $principal): float
    {
        $cfg = config('pricing.credit.fiducia');
        $pnbp = (float) $cfg['pnbp_max'];

        foreach ($cfg['pnbp_tiers'] as $ceiling => $fee) {
            if ($principal <= $ceiling) {
                $pnbp = (float) $fee;
                break;
            }
        }

        return (float) $cfg['notary_fee'] + $pnbp;
    }

    /**
     * Bunga efektif tahunan (anuitas) yang setara dengan angsuran flat —
     * dicari dengan bisection supaya pembeli bisa membandingkan dengan bank.
     */
    private function effectiveAnnualRate(float $principal, float $monthly, int $months): float
    {
        if ($principal <= 0 || $monthly * $months <= $principal) {
            return 0.0;
        }

        $low = 0.0;
        $high = 0.1;
        $mid = 0.0;
        for ($i = 0; $i < 100; $i++) {
            $mid = ($low + $high) / 2;
            $presentValue = $monthly * (1 - (1 + $mid) ** -$months) / $mid;
            if ($presentValue > $principal) {
                $low = $mid;
            } else {
                $high = $mid;
            }
        }

        return round($mid * 12, 4);
    }

    private function roundUpToHundreds(float $value): float
    {
        return (float) (ceil($value / 100) * 100);
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
