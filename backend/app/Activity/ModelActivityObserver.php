<?php

namespace App\Activity;

use App\Enums\ActivityCategory as Category;
use App\Enums\EscrowStatus;
use App\Enums\LoanStatus;
use App\Enums\ShipmentStatus;
use App\Enums\TransactionDocumentType;
use App\Enums\TransactionPaymentStatus;
use App\Enums\TransactionPayoutStatus;
use App\Enums\VehicleDocumentType;
use App\Enums\VehicleStatus;
use App\Models\BuyerProfile;
use App\Models\LoanInstallment;
use App\Models\Promo;
use App\Models\PromoRedemption;
use App\Models\SellerBonus;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\ShipmentDocument;
use App\Models\Transaction;
use App\Models\TransactionDocument;
use App\Models\TransactionDocumentSignature;
use App\Models\TransactionPayout;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Models\VehicleLoan;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * Menerjemahkan perubahan model menjadi baris log aktivitas berbahasa manusia.
 * Satu observer untuk semua model yang dipantau (didaftarkan di
 * AppServiceProvider) supaya setiap proses — dari verifikasi KYC sampai
 * cicilan — tercatat dari jalur mana pun perubahan itu terjadi.
 *
 * Path file, nomor identitas, dan nomor rekening sengaja tidak disalin ke
 * properties log.
 */
class ModelActivityObserver
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function created(Model $model): void
    {
        match (true) {
            $model instanceof User => $this->record(Category::Account, 'user.registered', "Registrasi akun {$this->roleLabel($model)} baru: {$model->name}", $model),
            $model instanceof SellerProfile => $this->record(Category::Verification, 'seller_kyc.submitted', "Pengajuan KYC penjual dikirim oleh {$this->userName($model->user_id)}", $model),
            $model instanceof BuyerProfile => $this->record(Category::Verification, 'buyer_kyc.submitted', "Pengajuan KYC pembeli dikirim oleh {$this->userName($model->user_id)}", $model),
            $model instanceof Vehicle => $this->record(Category::Verification, 'vehicle.created', "Listing {$this->vehicleName($model)} dibuat oleh {$this->userName($model->seller_id)} (status: {$this->enumValue($model->status)})", $model),
            $model instanceof VehicleDocument => $this->record(Category::Verification, 'vehicle_document.uploaded', 'Dokumen kendaraan '.$this->vehicleDocumentLabel($model)." diunggah untuk listing #{$model->vehicle_id}", $model),
            $model instanceof Transaction => $this->record(Category::Transaction, 'transaction.checkout', "Checkout {$this->transactionVehicle($model)} oleh {$this->userName($model->buyer_id)} — tagihan {$this->rupiah($model->amount)} ({$this->schemeLabel($model)})", $model, $model->id),
            $model instanceof TransactionDocument => $this->record(Category::Document, 'document.generated', $this->documentLabel($model->type).' diterbitkan', $model, $model->transaction_id),
            $model instanceof TransactionDocumentSignature => $this->record(Category::Document, 'document.signed', ($model->role === 'seller' ? 'Penjual' : 'Pembeli')." ({$this->userName($model->user_id)}) menandatangani ".$this->documentLabel($model->document_type), $model, $model->transaction_id),
            $model instanceof TransactionPayout => $this->record(Category::Payout, 'payout.initiated', "Pencairan {$this->rupiah($model->payout_amount)} ke penjual dimulai (komisi {$this->rupiah($model->commission_amount)})", $model, $model->transaction_id),
            $model instanceof Shipment => $this->record(Category::Shipment, 'shipment.created', "Pengiriman dibuat: {$model->origin_country} → {$model->destination_country} via {$this->enumValue($model->shipping_mode)}", $model, $model->transaction_id),
            $model instanceof ShipmentDocument => $this->record(Category::Shipment, 'shipment_document.uploaded', 'Dokumen pengiriman '.$this->enumLabel($model->type).' diunggah', $model, $model->shipment?->transaction_id),
            $model instanceof VehicleLoan => $this->record(Category::Credit, 'loan.submitted', "Pengajuan kredit {$model->contract_number} dikirim ke {$model->lender_name}: pokok {$this->rupiah($model->principal)}, {$model->tenor_months} bulan", $model, $model->transaction_id),
            $model instanceof SellerBonus => $this->record(Category::Bonus, 'bonus.granted', "Bonus penjual {$this->rupiah($model->amount)} untuk {$this->userName($model->seller_id)} — {$model->basis}", $model, $model->transaction_id),
            $model instanceof Promo => $this->record(Category::Promo, 'promo.created', "Promo \"{$model->name}\" dibuat".($model->code ? " (kode {$model->code})" : ''), $model),
            $model instanceof PromoRedemption => $this->record(Category::Promo, 'promo.redeemed', "Promo \"{$model->promo?->name}\" dipakai oleh {$this->userName($model->user_id)} — manfaat {$this->rupiah($model->benefit_amount)}", $model, $model->transaction_id),
            default => null,
        };
    }

    public function updated(Model $model): void
    {
        match (true) {
            $model instanceof User => $this->userUpdated($model),
            $model instanceof SellerProfile => $this->kycUpdated($model, 'seller_kyc', 'KYC penjual'),
            $model instanceof BuyerProfile => $this->kycUpdated($model, 'buyer_kyc', 'KYC pembeli'),
            $model instanceof Vehicle => $this->vehicleUpdated($model),
            $model instanceof Transaction => $this->transactionUpdated($model),
            $model instanceof TransactionDocument => $this->record(Category::Document, 'document.regenerated', $this->documentLabel($model->type).' diperbarui', $model, $model->transaction_id),
            $model instanceof TransactionPayout => $this->payoutUpdated($model),
            $model instanceof Shipment => $this->shipmentUpdated($model),
            $model instanceof VehicleLoan => $this->loanUpdated($model),
            $model instanceof LoanInstallment => $this->installmentUpdated($model),
            $model instanceof SellerBonus && $model->wasChanged('status') => $this->record(Category::Bonus, 'bonus.'.$model->status, "Bonus {$this->rupiah($model->amount)} untuk {$this->userName($model->seller_id)} berstatus {$model->status}", $model, $model->transaction_id),
            $model instanceof Promo => $this->record(Category::Promo, 'promo.updated', "Promo \"{$model->name}\" diperbarui", $model, null, ['fields' => $this->changedKeys($model)]),
            default => null,
        };
    }

    public function deleted(Model $model): void
    {
        match (true) {
            $model instanceof Vehicle => $this->record(Category::Verification, 'vehicle.deleted', "Listing {$this->vehicleName($model)} dihapus", $model),
            $model instanceof Promo => $this->record(Category::Promo, 'promo.deleted', "Promo \"{$model->name}\" dihapus", $model),
            default => null,
        };
    }

    private function userUpdated(User $user): void
    {
        if ($user->wasChanged('ktp_number') && $user->getOriginal('ktp_number') === null) {
            $this->record(Category::Verification, 'user.ktp_completed', "Data KTP dilengkapi oleh {$user->name}", $user);
        }

        if ($user->wasChanged('ktp_verified_at') && $user->ktp_verified_at !== null) {
            $this->record(Category::Verification, 'user.ktp_verified', "KTP {$user->name} terverifikasi", $user);
        }
    }

    private function kycUpdated(SellerProfile|BuyerProfile $profile, string $prefix, string $label): void
    {
        $owner = $this->userName($profile->user_id);

        if ($profile->wasChanged('status')) {
            $status = $this->enumValue($profile->status);
            $description = match ($status) {
                'approved' => "{$label} {$owner} disetujui",
                'rejected' => "{$label} {$owner} ditolak".($profile->rejection_reason ? ": {$profile->rejection_reason}" : ''),
                default => "{$label} {$owner} diajukan ulang untuk ditinjau",
            };
            $this->record(Category::Verification, "{$prefix}.{$status}", $description, $profile, null, [
                'from' => $this->enumValue($profile->getOriginal('status')),
                'to' => $status,
            ]);

            return;
        }

        $fields = $this->changedKeys($profile);
        if ($fields !== []) {
            $this->record(Category::Account, "{$prefix}.updated", "Data {$label} {$owner} diperbarui", $profile, null, ['fields' => $fields]);
        }
    }

    private function vehicleUpdated(Vehicle $vehicle): void
    {
        $name = $this->vehicleName($vehicle);

        if ($vehicle->wasChanged('status')) {
            $status = $vehicle->status;
            [$category, $description] = match ($status) {
                VehicleStatus::PendingReview => [Category::Verification, "Listing {$name} diajukan untuk review admin"],
                VehicleStatus::Approved => [Category::Verification, "Listing {$name} disetujui dan tampil di katalog"],
                VehicleStatus::Rejected => [Category::Verification, "Listing {$name} ditolak".($vehicle->rejection_reason ? ": {$vehicle->rejection_reason}" : '')],
                VehicleStatus::Sold => [Category::Transaction, "Listing {$name} ditandai terjual"],
                default => [Category::Verification, "Status listing {$name} menjadi {$this->enumValue($status)}"],
            };
            $this->record($category, 'vehicle.'.$this->enumValue($status), $description, $vehicle, null, [
                'from' => $this->enumValue($vehicle->getOriginal('status')),
                'to' => $this->enumValue($status),
            ]);

            return;
        }

        $fields = $this->changedKeys($vehicle);
        if ($fields !== []) {
            $this->record(Category::Verification, 'vehicle.updated', "Listing {$name} diperbarui", $vehicle, null, ['fields' => $fields]);
        }
    }

    private function transactionUpdated(Transaction $transaction): void
    {
        $vehicle = $this->transactionVehicle($transaction);
        $id = $transaction->id;

        if ($transaction->wasChanged('payment_status')) {
            $status = $transaction->payment_status;
            $description = match ($status) {
                TransactionPaymentStatus::Paid => "Pembayaran {$this->rupiah($transaction->amount)} untuk {$vehicle} diterima".($transaction->payment_channel ? " via {$transaction->payment_channel}" : ''),
                TransactionPaymentStatus::Cancelled => "Pesanan {$vehicle} dibatalkan".($transaction->cancellation_reason ? ": {$transaction->cancellation_reason}" : ''),
                TransactionPaymentStatus::Failed => "Pembayaran {$vehicle} gagal",
                TransactionPaymentStatus::Expired => "Tagihan {$vehicle} kedaluwarsa",
                default => "Status pembayaran {$vehicle} menjadi {$this->enumValue($status)}",
            };
            $this->record(Category::Payment, 'payment.'.$this->enumValue($status), $description, $transaction, $id);
        }

        if ($transaction->wasChanged('handover_requested_at') && $transaction->handover_requested_at !== null) {
            $this->record(Category::Handover, 'handover.requested', "Penjual mengajukan serah terima {$vehicle}", $transaction, $id);
        }

        if ($transaction->wasChanged('escrow_status') && $transaction->escrow_status !== null) {
            $to = $transaction->escrow_status;
            $from = $transaction->getOriginal('escrow_status');
            [$category, $description] = match ($to) {
                EscrowStatus::EscrowHold => [Category::Payment, "Dana pembeli ditahan di rekening escrow ({$vehicle})"],
                EscrowStatus::SerahTerima => [Category::Handover, $from === EscrowStatus::Dispute ? "Sengketa {$vehicle} diselesaikan, transaksi dilanjutkan" : "Admin menyetujui serah terima {$vehicle} — kendaraan boleh diserahkan"],
                EscrowStatus::PayoutRelease => [Category::Payout, "Admin menyetujui pencairan dana {$vehicle}"],
                EscrowStatus::Selesai => [Category::Transaction, "Transaksi {$vehicle} selesai"],
                EscrowStatus::Dispute => [Category::Transaction, "Sengketa dibuka untuk {$vehicle}".($transaction->dispute_reason ? ": {$transaction->dispute_reason}" : '')],
                EscrowStatus::Refunded => [Category::Payment, "Dana escrow {$vehicle} dikembalikan ke pembeli"],
            };
            $this->record($category, 'escrow.'.$to->value, $description, $transaction, $id, [
                'from' => $this->enumValue($from),
                'to' => $to->value,
            ]);
        }

        if ($transaction->wasChanged('seller_confirmed_at') && $transaction->seller_confirmed_at !== null) {
            $this->record(Category::Handover, 'handover.seller_confirmed', "Penjual menyerahkan/memberangkatkan {$vehicle}", $transaction, $id);
        }

        if ($transaction->wasChanged('buyer_confirmed_at') && $transaction->buyer_confirmed_at !== null) {
            $this->record(Category::Handover, 'handover.buyer_confirmed', "Pembeli mengonfirmasi penerimaan {$vehicle}", $transaction, $id);
        }
    }

    private function payoutUpdated(TransactionPayout $payout): void
    {
        if (! $payout->wasChanged('status')) {
            return;
        }

        $description = match ($payout->status) {
            TransactionPayoutStatus::Paid => "Dana {$this->rupiah($payout->payout_amount)} dicairkan ke penjual".($payout->reference ? " (ref {$payout->reference})" : ''),
            TransactionPayoutStatus::Failed => 'Pencairan dana gagal'.($payout->failure_reason ? ": {$payout->failure_reason}" : ''),
            default => 'Status pencairan dana: '.$this->enumValue($payout->status),
        };
        $this->record(Category::Payout, 'payout.'.$this->enumValue($payout->status), $description, $payout, $payout->transaction_id);
    }

    private function shipmentUpdated(Shipment $shipment): void
    {
        if ($shipment->wasChanged('status')) {
            $status = $shipment->status;
            $label = $status instanceof ShipmentStatus ? $status->label() : (string) $status;
            $this->record(Category::Shipment, 'shipment.'.$this->enumValue($status), "Status pengiriman menjadi {$label}", $shipment, $shipment->transaction_id);

            return;
        }

        $fields = $this->changedKeys($shipment);
        if ($fields !== []) {
            $this->record(Category::Shipment, 'shipment.updated', 'Data pengiriman diperbarui', $shipment, $shipment->transaction_id, ['fields' => $fields]);
        }
    }

    private function loanUpdated(VehicleLoan $loan): void
    {
        if ($loan->wasChanged('status')) {
            $description = match ($loan->status) {
                LoanStatus::Approved => "Kredit {$loan->contract_number} disetujui — {$loan->lender_name} melunasi {$this->rupiah($loan->vehicle_financed)} ke penjual, {$loan->tenor_months} angsuran terbit",
                LoanStatus::PaidOff => "Kredit {$loan->contract_number} lunas",
                default => "Status kredit {$loan->contract_number}: {$loan->status->value}",
            };
            $this->record(Category::Credit, 'loan.'.$loan->status->value, $description, $loan, $loan->transaction_id);
        }

        if ($loan->wasChanged('clock_offset_months')) {
            $this->record(Category::Credit, 'loan.clock_advanced', "Jam simulasi kredit {$loan->contract_number} dimajukan menjadi +{$loan->clock_offset_months} bulan", $loan, $loan->transaction_id);
        }
    }

    private function installmentUpdated(LoanInstallment $installment): void
    {
        if (! $installment->wasChanged('paid_at') || $installment->paid_at === null) {
            return;
        }

        $lateFee = (float) $installment->late_fee;
        $via = $installment->payment_method === 'manual' ? "setoran manual ({$installment->payment_channel})" : ($installment->payment_channel ?? 'portal bank');
        $this->record(
            Category::Credit,
            'installment.paid',
            "Angsuran ke-{$installment->sequence} dibayar {$this->rupiah($installment->paid_amount)} via {$via}".($lateFee > 0 ? " termasuk denda {$this->rupiah($lateFee)}" : ''),
            $installment,
            $installment->loan?->transaction_id,
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function record(Category $category, string $action, string $description, Model $subject, ?int $transactionId = null, array $properties = []): void
    {
        $this->logger->log($category, $action, $description, $subject, $transactionId, $properties);
    }

    /**
     * @return array<int, string>
     */
    private function changedKeys(Model $model): array
    {
        $ignored = ['updated_at', 'created_at', 'remember_token', 'password'];

        return array_values(array_filter(
            array_keys($model->getChanges()),
            fn (string $key) => ! in_array($key, $ignored, true) && ! str_ends_with($key, '_path') && ! str_contains($key, 'account_number') && $key !== 'id_number',
        ));
    }

    private function userName(?int $userId): string
    {
        return $userId ? (User::query()->whereKey($userId)->value('name') ?? "User #{$userId}") : '-';
    }

    private function vehicleName(Vehicle $vehicle): string
    {
        return trim("{$vehicle->brand} {$vehicle->model} {$vehicle->year}");
    }

    private function transactionVehicle(Transaction $transaction): string
    {
        $vehicle = Vehicle::query()->select(['id', 'brand', 'model', 'year'])->find($transaction->vehicle_id);

        return $vehicle ? $this->vehicleName($vehicle)." (#{$transaction->id})" : "transaksi #{$transaction->id}";
    }

    private function schemeLabel(Transaction $transaction): string
    {
        return match ($this->enumValue($transaction->payment_scheme)) {
            'full' => 'bayar penuh',
            'credit' => 'kredit leasing, DP '.(float) $transaction->dp_percent.'%',
            default => 'DP '.(float) $transaction->dp_percent.'%',
        };
    }

    private function roleLabel(User $user): string
    {
        return match ($this->enumValue($user->role)) {
            'seller' => 'penjual',
            'buyer' => 'pembeli',
            'admin' => 'admin',
            default => 'pengguna',
        };
    }

    private function documentLabel(mixed $type): string
    {
        return $type instanceof TransactionDocumentType ? $type->label() : (string) $type;
    }

    private function vehicleDocumentLabel(VehicleDocument $document): string
    {
        return $document->type instanceof VehicleDocumentType ? $document->type->label() : (string) $document->type;
    }

    private function enumLabel(mixed $value): string
    {
        return $value instanceof BackedEnum && method_exists($value, 'label') ? $value->label() : (string) $this->enumValue($value);
    }

    private function enumValue(mixed $value): ?string
    {
        return $value instanceof BackedEnum ? (string) $value->value : ($value === null ? null : (string) $value);
    }

    private function rupiah(mixed $value): string
    {
        return 'Rp'.number_format((float) $value, 0, ',', '.');
    }
}
