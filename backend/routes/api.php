<?php

use App\Http\Controllers\Admin\BuyerKycController;
use App\Http\Controllers\Admin\PayoutReconciliationController;
use App\Http\Controllers\Admin\SellerKycController;
use App\Http\Controllers\Admin\ShipmentReviewController;
use App\Http\Controllers\Admin\TransactionReviewController;
use App\Http\Controllers\Admin\VehicleReviewController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Buyer\BuyerProfileController;
use App\Http\Controllers\Buyer\CheckoutController;
use App\Http\Controllers\BuyerProfileDocumentController;
use App\Http\Controllers\Catalog\VehicleCatalogController;
use App\Http\Controllers\NavbarSummaryController;
use App\Http\Controllers\Payments\MockPaymentController;
use App\Http\Controllers\Payments\XenditWebhookController;
use App\Http\Controllers\Seller\SellerBankAccountController;
use App\Http\Controllers\Seller\SellerProfileController;
use App\Http\Controllers\Seller\VehicleController;
use App\Http\Controllers\Seller\VehicleDocumentUploadController;
use App\Http\Controllers\Seller\VehicleInsuranceController;
use App\Http\Controllers\Seller\VehicleVinCheckController;
use App\Http\Controllers\SellerProfileDocumentController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\ShipmentDocumentController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionDocumentController;
use App\Http\Controllers\VehicleDocumentController;
use App\Http\Controllers\VehicleInsuranceCertificateController;
use Illuminate\Support\Facades\Route;

// Auth
Route::post('/auth/register', [RegisterController::class, 'store'])->name('auth.register');
Route::post('/auth/login', [LoginController::class, 'store'])->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [LogoutController::class, 'destroy'])->name('auth.logout');
    Route::get('/auth/me', [MeController::class, 'show'])->name('auth.me');

    // Profil — semua role
    Route::put('/auth/profile', [ProfileController::class, 'update'])->name('auth.profile.update');
    Route::put('/auth/profile/password', [ProfileController::class, 'updatePassword'])->name('auth.profile.password');
    Route::post('/auth/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('auth.profile.avatar');
    Route::put('/auth/profile/ktp', [ProfileController::class, 'updateKtp'])->name('auth.profile.ktp');

    // Ringkasan angka untuk badge navbar (cart, notifikasi, pesan, chat).
    Route::get('/navbar-summary', [NavbarSummaryController::class, 'show'])->name('navbar-summary.show');

    // Seller — KYC & listing CRUD
    Route::middleware('role:seller')->prefix('seller')->name('seller.')->group(function () {
        Route::get('/kyc', [SellerProfileController::class, 'show'])->name('kyc.show');
        Route::post('/kyc', [SellerProfileController::class, 'store'])->name('kyc.store');
        Route::put('/kyc', [SellerProfileController::class, 'update'])->name('kyc.update');
        Route::put('/bank-account', [SellerBankAccountController::class, 'update'])->name('bank-account.update');

        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->name('vehicles.show');
        Route::post('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::post('/vehicles/{vehicle}/submit-for-review', [VehicleController::class, 'submitForReview'])->name('vehicles.submit-for-review');

        // Extended vehicle docs (service history, inspection report, certificate of authenticity) — Sprint 8.
        Route::post('/vehicles/{vehicle}/documents', [VehicleDocumentUploadController::class, 'store'])->name('vehicles.documents.store');
        Route::delete('/vehicles/{vehicle}/documents/{document}', [VehicleDocumentUploadController::class, 'destroy'])->name('vehicles.documents.destroy');

        // Insurance policies per kendaraan (All Risk / TLO / Agreed Value).
        Route::get('/vehicles/{vehicle}/insurance', [VehicleInsuranceController::class, 'index'])->name('vehicles.insurance.index');
        Route::post('/vehicles/{vehicle}/insurance', [VehicleInsuranceController::class, 'store'])->name('vehicles.insurance.store');
        Route::delete('/vehicles/{vehicle}/insurance/{policy}', [VehicleInsuranceController::class, 'destroy'])->name('vehicles.insurance.destroy');

        // VIN history check (mock service, deterministic response).
        Route::get('/vehicles/{vehicle}/vin-checks', [VehicleVinCheckController::class, 'index'])->name('vehicles.vin-checks.index');
        Route::post('/vehicles/{vehicle}/vin-checks', [VehicleVinCheckController::class, 'store'])->name('vehicles.vin-checks.store');

        // Seller — tracking & serah-terima transaksi penjualan
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::post('/transactions/{transaction}/confirm-handover', [TransactionController::class, 'confirmHandover'])->name('transactions.confirm-handover');
        Route::post('/transactions/{transaction}/dispute', [TransactionController::class, 'dispute'])->name('transactions.dispute');
    });

    // Admin — KYC, listing review, & dashboard approval escrow
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/kyc', [SellerKycController::class, 'index'])->name('kyc.index');
        Route::patch('/kyc/{sellerProfile}', [SellerKycController::class, 'review'])->name('kyc.review');

        Route::get('/buyer-kyc', [BuyerKycController::class, 'index'])->name('buyer-kyc.index');
        Route::patch('/buyer-kyc/{buyerProfile}', [BuyerKycController::class, 'review'])->name('buyer-kyc.review');

        Route::get('/vehicles', [VehicleReviewController::class, 'index'])->name('vehicles.index');
        Route::get('/vehicles/{vehicle}', [VehicleReviewController::class, 'show'])->name('vehicles.show');
        Route::patch('/vehicles/{vehicle}', [VehicleReviewController::class, 'review'])->name('vehicles.review');

        Route::get('/transactions', [TransactionReviewController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionReviewController::class, 'show'])->name('transactions.show');
        Route::post('/transactions/{transaction}/approve-handover', [TransactionReviewController::class, 'approveHandover'])->name('transactions.approve-handover');
        Route::post('/transactions/{transaction}/approve-payout', [TransactionReviewController::class, 'approvePayout'])->name('transactions.approve-payout');
        Route::post('/transactions/{transaction}/disburse', [TransactionReviewController::class, 'disburse'])->name('transactions.disburse');
        Route::post('/transactions/{transaction}/mark-completed', [TransactionReviewController::class, 'markCompleted'])->name('transactions.mark-completed');
        Route::post('/transactions/{transaction}/resolve-dispute', [TransactionReviewController::class, 'resolveDispute'])->name('transactions.resolve-dispute');

        Route::get('/payouts/reconciliation', [PayoutReconciliationController::class, 'index'])->name('payouts.reconciliation');

        // Shipment state transitions — admin only (Epic 9).
        Route::post('/transactions/{transaction}/shipment/mark-logistics-prep', [ShipmentReviewController::class, 'markLogisticsPrep'])->name('shipments.mark-logistics-prep');
        Route::post('/transactions/{transaction}/shipment/mark-in-transit', [ShipmentReviewController::class, 'markInTransit'])->name('shipments.mark-in-transit');
        Route::post('/transactions/{transaction}/shipment/mark-customs-clearance', [ShipmentReviewController::class, 'markCustomsClearance'])->name('shipments.mark-customs-clearance');
        Route::post('/transactions/{transaction}/shipment/mark-delivered', [ShipmentReviewController::class, 'markDelivered'])->name('shipments.mark-delivered');
    });

    // Buyer — checkout, pembayaran DP, & tracking serah-terima
    Route::middleware('role:buyer')->prefix('buyer')->name('buyer.')->group(function () {
        // KYC buyer + Proof of Funds — wajib untuk transaksi bernilai tinggi (Epic 6).
        Route::get('/kyc', [BuyerProfileController::class, 'show'])->name('kyc.show');
        Route::post('/kyc', [BuyerProfileController::class, 'store'])->name('kyc.store');
        Route::post('/kyc/update', [BuyerProfileController::class, 'update'])->name('kyc.update');

        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::post('/transactions/{transaction}/refresh-status', [CheckoutController::class, 'refreshStatus'])->name('transactions.refresh-status');
        Route::post('/transactions/{transaction}/cancel', [CheckoutController::class, 'cancel'])->name('transactions.cancel');
        Route::post('/transactions/{transaction}/confirm-handover', [TransactionController::class, 'confirmHandover'])->name('transactions.confirm-handover');
        Route::post('/transactions/{transaction}/dispute', [TransactionController::class, 'dispute'])->name('transactions.dispute');
        Route::post('/vehicles/{vehicle}/checkout', [CheckoutController::class, 'store'])->name('vehicles.checkout');
    });

    // Simulasi pembayaran offline (gateway "mock") — lihat App\Payments\MockGateway.
    Route::prefix('payments/mock')->name('payments.mock.')->group(function () {
        Route::get('/{reference}', [MockPaymentController::class, 'show'])->name('show');
        Route::post('/{reference}/pay', [MockPaymentController::class, 'pay'])->name('pay');
        Route::post('/{reference}/fail', [MockPaymentController::class, 'fail'])->name('fail');
    });

    // E-signature: buyer/seller menandatangani transaksi via canvas signature pad (Epic 7B).
    Route::post('/transactions/{transaction}/sign', [SignatureController::class, 'store'])->name('transactions.sign');

    // Shipment / logistik cross-border (Epic 9). Buyer & seller boleh view/init/update/upload docs.
    // Transisi state di-handle admin di /admin/... di atas.
    Route::get('/transactions/{transaction}/shipment', [ShipmentController::class, 'show'])->name('shipments.show');
    Route::post('/transactions/{transaction}/shipment', [ShipmentController::class, 'store'])->name('shipments.store');
    Route::post('/transactions/{transaction}/shipment/update', [ShipmentController::class, 'update'])->name('shipments.update');
    Route::post('/transactions/{transaction}/shipment/documents', [ShipmentDocumentController::class, 'store'])->name('shipments.documents.store');
});

// Dokumen privat (KTP/NPWP/STNK/BPKB + buyer KYC + transaction PDFs + insurance) — di luar
// `auth:sanctum` supaya bisa dibuka di tab baru tanpa masalah cookie SPA cross-port.
// Akses di-gate lewat `signed` middleware: URL wajib memuat HMAC signature dengan expiry
// dari `URL::temporarySignedRoute()`. Signed URL hanya di-generate di resource layer saat
// pemanggil API sudah lolos policy (owner/admin), jadi hanya pihak berwenang yang tahu
// URL-nya, dan expiry mencegah URL bocor jadi permanen.
Route::middleware('signed')->group(function () {
    Route::get('/seller-kyc/{sellerProfile}/documents/{type}', [SellerProfileDocumentController::class, 'show'])->name('seller-kyc.documents.show');
    Route::get('/buyer-kyc/{buyerProfile}/documents/{type}', [BuyerProfileDocumentController::class, 'show'])->name('buyer-kyc.documents.show');
    Route::get('/vehicles/{vehicle}/documents/{document}', [VehicleDocumentController::class, 'show'])->name('vehicles.documents.show');
    Route::get('/vehicles/{vehicle}/insurance/{policy}/certificate', [VehicleInsuranceCertificateController::class, 'show'])->name('vehicles.insurance.certificate');
    Route::get('/transactions/{transaction}/documents/{document}', [TransactionDocumentController::class, 'show'])->name('transaction-documents.show');
    Route::get('/shipments/{shipment}/documents/{document}', [ShipmentDocumentController::class, 'show'])->name('shipments.documents.show');
    Route::get('/shipments/{shipment}/cargo-certificate', [ShipmentDocumentController::class, 'cargoCertificate'])->name('shipments.cargo-certificate.show');
});

// Webhook Xendit — server-to-server, tidak lewat auth:sanctum (verifikasi pakai x-callback-token).
Route::post('/webhooks/xendit', XenditWebhookController::class)->name('webhooks.xendit');

// Katalog publik
Route::get('/vehicles', [VehicleCatalogController::class, 'index'])->name('catalog.index');
Route::get('/vehicles/{vehicle}', [VehicleCatalogController::class, 'show'])->name('catalog.show');
