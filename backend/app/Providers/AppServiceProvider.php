<?php

namespace App\Providers;

use App\Activity\ModelActivityObserver;
use App\Enums\PaymentGatewayDriver;
use App\Enums\PayoutMethod;
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
use App\Payments\MockGateway;
use App\Payments\PaymentGateway;
use App\Payments\XenditGateway;
use App\Payouts\DisbursementGateway;
use App\Payouts\ManualDisbursementGateway;
use App\Payouts\XenditDisbursementGateway;
use App\Promotions\PromoService;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function () {
            return match (PaymentGatewayDriver::from(config('payment.gateway'))) {
                PaymentGatewayDriver::Xendit => new XenditGateway,
                PaymentGatewayDriver::Mock => new MockGateway,
            };
        });

        // Satu instance per request: daftar promo berjalan cukup di-query sekali
        // walaupun katalog menampilkan puluhan kendaraan.
        $this->app->scoped(PromoService::class);

        $this->app->bind(DisbursementGateway::class, function () {
            return match (PayoutMethod::from(config('payout.driver'))) {
                PayoutMethod::Xendit => new XenditDisbursementGateway,
                PayoutMethod::Manual => new ManualDisbursementGateway,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Catch silently-dropped mass-assignments and typo'd attribute access early.
        // Lazy-loading is intentionally left unguarded: resources read single-model
        // relations (reviewer, seller) that don't need eager loading outside list endpoints.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());

        // Log aktivitas global: setiap proses (verifikasi, transaksi, kredit, payout,
        // promo, dst.) tercatat dari jalur mana pun perubahannya terjadi.
        foreach ([
            User::class, SellerProfile::class, BuyerProfile::class, Vehicle::class, VehicleDocument::class,
            Transaction::class, TransactionDocument::class, TransactionDocumentSignature::class, TransactionPayout::class,
            Shipment::class, ShipmentDocument::class, VehicleLoan::class, LoanInstallment::class,
            SellerBonus::class, Promo::class, PromoRedemption::class,
        ] as $model) {
            $model::observe(ModelActivityObserver::class);
        }

        // Override redirect target untuk guest — Laravel default panggil route('login')
        // yang tidak ada di app SPA ini (bukan blade), sehingga request browser-direct
        // ke /api/* (mis. admin klik "Lihat KTP" di tab baru saat sesi habis) crash
        // 500. Kembalikan null untuk request /api/* (biar AuthenticationException
        // di-render sebagai 401 JSON oleh handler di bootstrap/app.php), atau URL
        // login frontend untuk browser nav.
        Authenticate::redirectUsing(function (Request $request): ?string {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            $frontend = rtrim(explode(',', (string) env('FRONTEND_URL', 'http://localhost:3000'))[0], '/');

            return $frontend.'/login';
        });
    }
}
